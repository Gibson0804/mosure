<?php

namespace App\Services;

use App\Models\Media;
use App\Repository\MediaRepository;
use App\Support\SecureOutboundUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    private const LOCAL_PUBLIC_DISK = 'public';

    private const MANAGED_S3_DISK = 's3';

    private const LEGACY_COS_DISK = 'cos';

    /** @var MediaRepository */
    protected $mediaRepository;

    public function __construct(MediaRepository $mediaRepository)
    {
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * 获取当前配置的存储磁盘名称
     */
    public function getStorageDisk(): string
    {
        try {
            $configService = app(SystemConfigService::class);
            $cfg = $configService->getConfigRaw();
            $storage = isset($cfg['storage']) && is_array($cfg['storage']) ? $cfg['storage'] : [];

            $this->registerManagedObjectStorage($storage);

            $default = (string) ($storage['default'] ?? 'local');
            if ($default === self::MANAGED_S3_DISK || $default === self::LEGACY_COS_DISK) {
                return self::MANAGED_S3_DISK;
            }
        } catch (\Throwable $e) {
            Log::warning('获取存储配置失败，回退到本地存储: '.$e->getMessage());
        }

        return self::LOCAL_PUBLIC_DISK;
    }

    /**
     * 根据存储磁盘生成文件的完整公开 URL
     */
    public function getFileUrl(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? $this->getStorageDisk();

        return $this->storage($disk)->url($path);
    }

    /**
     * 批量移动媒体到目标文件夹
     *
     * @param  array<int,int>  $ids
     * @return int 受影响行数
     */
    public function batchMove(array $ids, int $toFolderId): int
    {
        if (empty($ids)) {
            return 0;
        }

        return Media::whereIn('id', $ids)->update(['folder_id' => $toFolderId]);
    }

    /**
     * 获取媒体列表
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getMediaList(array $filters = [])
    {
        $query = Media::query();

        if (array_key_exists('created_by', $filters)) {
            $query->where('created_by', (string) $filters['created_by']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('folder_id', $filters) && $filters['folder_id'] !== null && $filters['folder_id'] !== '') {
            // 支持逗号分隔的多个文件夹ID（用于父文件夹包含子文件夹的场景）
            $folderIds = is_array($filters['folder_id'])
                ? $filters['folder_id']
                : array_map('intval', explode(',', $filters['folder_id']));
            $query->whereIn('folder_id', $folderIds);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 标签筛选（支持单个标签或多个标签）
        if (! empty($filters['tag'])) {
            $tag = $filters['tag'];
            $query->whereJsonContains('tags', $tag);
        }

        // 多标签筛选（数组形式）
        if (! empty($filters['tags']) && is_array($filters['tags'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['tags'] as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        // 关联文件夹信息
        $query->with(['folder' => function ($q) {
            $q->select('id', 'name', 'mpath');
        }]);

        // 支持自定义每页数量
        $perPage = ! empty($filters['per_page']) ? min(100, max(1, (int) $filters['per_page'])) : 20;

        $result = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // 为每个媒体添加完整文件夹路径
        $result->getCollection()->transform(function ($media) {
            if ($media->folder) {
                $media->folder->full_path = $media->folder->full_path;
            }

            return $media;
        });

        return $result;
    }

    /**
     * 创建媒体文件
     */
    public function createMedia($file, $description = null, $customFilename = null, array $options = [])
    {
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: pathinfo($originalFilename, PATHINFO_EXTENSION);
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();

        $this->assertSafeUploadType((string) $extension, (string) $mimeType);

        // 处理自定义文件名：优先使用自定义文件名，否则使用原始文件名（保持原文件名）
        $originalNameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
        if ($customFilename) {
            $filename = $this->sanitizeFilename($customFilename).'.'.$extension;
        } else {
            $filename = $this->sanitizeFilename($originalNameWithoutExt).'.'.$extension;
        }

        // 确定文件类型
        $type = $this->determineFileType($mimeType, $extension);

        // 获取存储磁盘
        $disk = $this->getStorageDisk();
        $storagePath = 'media/'.$type;

        // 本地磁盘需要确保目录存在
        if ($disk === self::LOCAL_PUBLIC_DISK) {
            $directory = storage_path('app/public/'.$storagePath);
            if (! file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
        }

        // 存储文件
        try {
            if ($this->usesObjectStorage($disk)) {
                $path = $file->storePubliclyAs($storagePath, $filename, $disk);
            } else {
                $path = $file->storeAs($storagePath, $filename, $disk);
            }
        } catch (\Throwable $e) {
            Log::error('文件存储异常', [
                'disk' => $disk,
                'storage_path' => $storagePath,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('文件存储失败: '.$e->getMessage());
        }

        if (! $path) {
            Log::error('文件存储返回空路径', [
                'disk' => $disk,
                'storage_path' => $storagePath,
                'filename' => $filename,
            ]);
            throw new \Exception('文件存储失败: 返回空路径');
        }

        // 生成URL
        $url = $this->getFileUrl($path, $disk);

        // 获取元数据
        $metadata = $this->extractMetadata($file, $type);

        $saveData = [
            'filename' => $filename,
            'original_filename' => $originalNameWithoutExt,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'path' => $path,
            'disk' => $disk,
            'url' => $url,
            'size' => $size,
            'user_id' => Auth::id() ?: 1,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ];
        if (isset($options['created_by'])) {
            $saveData['created_by'] = (string) $options['created_by'];
        }
        if (isset($options['updated_by'])) {
            $saveData['updated_by'] = (string) $options['updated_by'];
        }
        if (isset($options['folder_id'])) {
            $saveData['folder_id'] = (int) $options['folder_id'] ?: null;
        }
        if (isset($options['tags'])) {
            $tags = $options['tags'];
            if (is_string($tags)) {
                $tags = array_values(array_filter(array_map('trim', explode(',', $tags)), fn ($v) => $v !== ''));
            }
            if (is_array($tags)) {
                $saveData['tags'] = array_values(array_map('strval', $tags));
            }
        }

        return Media::create($saveData);
    }

    /**
     * 从 URL 下载文件并创建媒体记录
     */
    public function createMediaFromUrl(string $url, ?string $description = null, array $options = []): ?Media
    {
        try {
            SecureOutboundUrl::assertAllowed($url);

            $response = Http::timeout(15)->withOptions(['allow_redirects' => false])->get($url);
            if (! $response->successful()) {
                Log::error('下载文件失败: '.$url);

                return null;
            }

            $contentLength = (int) ($response->header('Content-Length') ?? 0);
            if ($contentLength > 100 * 1024 * 1024) {
                throw new \RuntimeException('远程文件过大');
            }

            $content = $response->body();
            if (strlen($content) > 100 * 1024 * 1024) {
                throw new \RuntimeException('远程文件过大');
            }

            $mimeType = trim(strtok((string) ($response->header('Content-Type') ?? 'application/octet-stream'), ';')) ?: 'application/octet-stream';
            $extension = $this->getExtensionFromMime($mimeType) ?: pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $this->assertSafeUploadType((string) $extension, (string) $mimeType);
            $filename = Str::uuid()->toString();
            $customFilename = $options['custom_filename'] ?? $filename;

            $tempPath = tempnam(sys_get_temp_dir(), 'media_');
            file_put_contents($tempPath, $content);

            $file = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $filename.'.'.$extension,
                $mimeType,
                null,
                true
            );

            unset($options['custom_filename']);

            $media = $this->createMedia($file, $description, $customFilename, $options);

            @unlink($tempPath);

            return $media;
        } catch (\Throwable $e) {
            Log::error('从URL创建媒体失败: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
    }

    /**
     * 根据 MIME 类型获取文件扩展名
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/bmp' => 'bmp',
            'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/ogg' => 'ogg',
            'video/mp4' => 'mp4', 'video/webm' => 'webm',
            'application/pdf' => 'pdf',
        ];

        return $map[$mimeType] ?? '';
    }

    private function assertSafeUploadType(string $extension, string $mimeType): void
    {
        $ext = strtolower(trim($extension));
        $mime = strtolower(trim($mimeType));

        $blockedExtensions = [
            'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8',
            'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz',
            'js', 'mjs', 'cjs', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'sh', 'bash', 'zsh', 'bat', 'cmd',
            'exe', 'dll', 'msi', 'com', 'scr', 'jar',
        ];

        $blockedMimePrefixes = [
            'text/html', 'application/xhtml+xml', 'image/svg+xml',
            'application/javascript', 'text/javascript', 'application/x-javascript',
            'application/x-httpd-php', 'text/x-php', 'application/x-php',
            'application/x-msdownload', 'application/x-sh',
        ];

        if ($ext !== '' && in_array($ext, $blockedExtensions, true)) {
            throw new \RuntimeException('不允许上传可能执行脚本的文件类型');
        }

        foreach ($blockedMimePrefixes as $blockedMime) {
            if ($mime === $blockedMime || str_starts_with($mime, $blockedMime.';')) {
                throw new \RuntimeException('不允许上传可能执行脚本的文件类型');
            }
        }
    }

    /**
     * 获取媒体详情
     */
    public function getMediaById($id)
    {
        $media = Media::with(['folder' => function ($q) {
            $q->select('id', 'name', 'mpath');
        }])->findOrFail($id);

        // 添加完整文件夹路径
        if ($media->folder) {
            $media->folder->full_path = $media->folder->full_path;
        }
        $media->is_image = in_array($media->type, ['image']);
        $media->is_video = in_array($media->type, ['video']);

        return $media;
    }

    /**
     * 更新媒体信息
     */
    public function updateMedia($id, array $data)
    {
        $media = Media::findOrFail($id);

        // 处理文件名更新
        if (isset($data['filename']) && $data['filename'] !== $media->original_filename) {
            $extension = pathinfo($media->path, PATHINFO_EXTENSION);
            $newFilename = $this->sanitizeFilename($data['filename']).'.'.$extension;
            $newPath = 'media/'.$media->type.'/'.$newFilename;
            $disk = $media->disk ?? $this->getStorageDisk();

            // 重命名文件
            if ($this->storage($disk)->exists($media->path)) {
                $this->storage($disk)->move($media->path, $newPath);

                // 更新媒体记录
                $media->update([
                    'original_filename' => $data['filename'],
                    'filename' => $newFilename,
                    'path' => $newPath,
                    'url' => $this->getFileUrl($newPath, $disk),
                ]);
            }

            // 移除已处理的filename，避免重复更新
            unset($data['filename']);
        }

        // 更新其他字段
        if (! empty($data)) {
            // 处理 tags: 字符串转数组
            if (array_key_exists('tags', $data)) {
                $tags = $data['tags'];
                if (is_string($tags)) {
                    $tags = array_values(array_filter(array_map('trim', explode(',', $tags)), fn ($v) => $v !== ''));
                }
                if (is_array($tags)) {
                    $data['tags'] = array_values(array_map('strval', $tags));
                } else {
                    unset($data['tags']);
                }
            }
            $media->update($data);
        }

        return $media;
    }

    /**
     * 替换媒体文件（保持ID不变）
     */
    public function replaceMediaFile($id, $file)
    {
        $media = Media::findOrFail($id);
        $oldDisk = $media->disk ?? 'public';
        $newDisk = $this->getStorageDisk();

        // 删除旧文件
        if ($this->storage($oldDisk)->exists($media->path)) {
            $this->storage($oldDisk)->delete($media->path);
        }

        // 处理新文件
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: pathinfo($originalFilename, PATHINFO_EXTENSION);
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';

        // 确定媒体类型
        $type = $this->determineFileType($mimeType, $extension);

        // 生成新文件名
        $filename = $this->sanitizeFilename(pathinfo($originalFilename, PATHINFO_FILENAME)).'_'.time().'.'.$extension;
        $path = 'media/'.$type.'/'.$filename;

        // 存储新文件
        if ($this->usesObjectStorage($newDisk)) {
            $file->storePubliclyAs('media/'.$type, $filename, $newDisk);
        } else {
            $file->storeAs('media/'.$type, $filename, $newDisk);
        }

        // 更新数据库记录
        $media->update([
            'filename' => $filename,
            'original_filename' => pathinfo($originalFilename, PATHINFO_FILENAME),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'path' => $path,
            'disk' => $newDisk,
            'url' => $this->getFileUrl($path, $newDisk),
            'size' => $file->getSize(),
            'type' => $type,
        ]);

        return $media;
    }

    /**
     * 删除媒体文件
     */
    public function deleteMedia($id)
    {
        $media = Media::findOrFail($id);
        $disk = $media->disk ?? 'public';

        // 删除存储的文件
        if ($this->storage($disk)->exists($media->path)) {
            $this->storage($disk)->delete($media->path);
        }

        // 删除数据库记录
        return $media->delete();
    }

    /**
     * 批量删除媒体文件（删除物理文件 + 数据库记录）
     *
     * @param  array<int,int>  $ids
     * @return int 删除的条数
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        // 先获取记录以删除物理文件
        $items = $this->mediaRepository->getByIds($ids);
        foreach ($items as $media) {
            $disk = $media->disk ?? 'public';
            if ($media->path && $this->storage($disk)->exists($media->path)) {
                $this->storage($disk)->delete($media->path);
            }
        }

        // 再删除数据库记录
        return $this->mediaRepository->deleteByIds($ids);
    }

    /**
     * 根据MIME类型和扩展名确定文件类型
     */
    private function determineFileType($mimeType, $extension)
    {
        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        } elseif (strpos($mimeType, 'video/') === 0) {
            return 'video';
        } elseif (strpos($mimeType, 'audio/') === 0) {
            return 'audio';
        } elseif (in_array($extension, ['pdf'])) {
            return 'pdf';
        } elseif (in_array($extension, ['doc', 'docx'])) {
            return 'word';
        } elseif (in_array($extension, ['xls', 'xlsx'])) {
            return 'excel';
        } elseif (in_array($extension, ['ppt', 'pptx'])) {
            return 'ppt';
        } elseif (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'zip';
        } elseif (in_array($extension, ['html', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'cs', 'json', 'xml'])) {
            return 'code';
        } else {
            return 'document';
        }
    }

    /**
     * 提取文件的元数据
     */
    /**
     * 清理文件名，移除不安全的字符
     */
    private function sanitizeFilename($filename)
    {
        // 移除可能引起路径遍历的字符
        $filename = str_replace(['../', '..\\', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $filename);
        // 移除多余的空格和点
        $filename = trim($filename, ' .');
        // 如果清理后为空，则生成一个随机名称
        if (empty($filename)) {
            return Str::random(10);
        }

        return $filename;
    }

    private function storage(string $disk)
    {
        $normalizedDisk = $this->normalizeDiskName($disk);

        if ($this->usesObjectStorage($normalizedDisk) || $normalizedDisk === self::LEGACY_COS_DISK) {
            $this->registerManagedObjectStorage();
        }

        return Storage::disk($normalizedDisk);
    }

    private function normalizeDiskName(string $disk): string
    {
        return $disk === 'local' ? self::LOCAL_PUBLIC_DISK : $disk;
    }

    private function usesObjectStorage(string $disk): bool
    {
        $normalized = $this->normalizeDiskName($disk);

        return in_array($normalized, [self::MANAGED_S3_DISK, self::LEGACY_COS_DISK], true);
    }

    private function registerManagedObjectStorage(?array $storage = null): void
    {
        $storage = $storage ?? $this->loadStorageConfig();
        $diskConfig = $this->buildManagedObjectStorageConfig($storage);

        if ($diskConfig === null) {
            return;
        }

        config([
            'filesystems.disks.'.self::MANAGED_S3_DISK => array_merge(
                config('filesystems.disks.'.self::MANAGED_S3_DISK, []),
                $diskConfig
            ),
            'filesystems.disks.'.self::LEGACY_COS_DISK => array_merge(
                config('filesystems.disks.'.self::LEGACY_COS_DISK, []),
                $diskConfig
            ),
        ]);
    }

    private function loadStorageConfig(): array
    {
        $configService = app(SystemConfigService::class);
        $cfg = $configService->getConfigRaw();

        return isset($cfg['storage']) && is_array($cfg['storage']) ? $cfg['storage'] : [];
    }

    private function buildManagedObjectStorageConfig(array $storage): ?array
    {
        $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];

        if ($s3 === []) {
            return null;
        }

        $region = trim((string) ($s3['region'] ?? ''));
        if ($region === '') {
            $region = 'us-east-1';
        }

        $endpoint = trim((string) ($s3['endpoint'] ?? ''));
        $url = trim((string) ($s3['url'] ?? ''));

        if ($endpoint === '' && $url === '' && trim((string) ($s3['bucket'] ?? '')) === '') {
            return null;
        }

        return [
            'driver' => 's3',
            'key' => (string) ($s3['key'] ?? ''),
            'secret' => (string) ($s3['secret'] ?? ''),
            'region' => $region,
            'bucket' => (string) ($s3['bucket'] ?? ''),
            'endpoint' => $endpoint !== '' ? $endpoint : null,
            'url' => $url !== '' ? rtrim($url, '/') : null,
            'use_path_style_endpoint' => (bool) ($s3['use_path_style_endpoint'] ?? false),
            'throw' => true,
        ];
    }

    /**
     * 提取文件的元数据
     */
    private function extractMetadata($file, $type)
    {
        $metadata = [];

        if ($type === 'image') {
            try {
                $imageInfo = getimagesize($file->getPathname());
                if ($imageInfo) {
                    $metadata['width'] = $imageInfo[0];
                    $metadata['height'] = $imageInfo[1];
                }
            } catch (\Exception $e) {
                // 忽略错误
            }
        }

        return $metadata;
    }
}
