<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Http\Request;

class OpenApiPermissionResolver
{
    public function resolve(Request $request): array
    {
        $path = trim($request->path(), '/');
        if (! str_starts_with($path, 'open/')) {
            return ['scope' => null, 'permission' => null, 'resource' => null, 'action' => null, 'constraints' => []];
        }

        $segments = explode('/', $path);
        $resource = $segments[1] ?? '';
        $action = $segments[2] ?? '';

        $scope = null;
        $permission = null;
        $constraints = [
            'method' => strtoupper($request->method()),
            'path' => '/'.$path,
        ];

        if ($resource === 'content') {
            $table = $segments[3] ?? null;
            $id = $segments[4] ?? null;
            $constraints['table'] = $table;
            $constraints['resource_id'] = $id;
            if (in_array($action, ['list', 'detail', 'count'], true)) {
                $scope = ApiKey::SCOPE_CONTENT_READ;
                $permission = 'content.'.$action;
            } elseif (in_array($action, ['create', 'update', 'delete'], true)) {
                $scope = ApiKey::SCOPE_CONTENT_WRITE;
                $permission = 'content.'.$action;
            }
        } elseif ($resource === 'page') {
            $table = $segments[3] ?? null;
            $constraints['table'] = $table;
            if ($action === 'detail') {
                $scope = ApiKey::SCOPE_PAGE_READ;
                $permission = 'page.detail';
            } elseif ($action === 'update') {
                $scope = ApiKey::SCOPE_PAGE_WRITE;
                $permission = 'page.update';
            }
        } elseif ($resource === 'media') {
            $constraints['resource_id'] = $segments[3] ?? null;
            if (in_array($action, ['detail', 'list', 'by-tags', 'by-folder', 'search'], true)) {
                $scope = ApiKey::SCOPE_MEDIA_READ;
                $permission = match ($action) {
                    'by-tags' => 'media.byTags',
                    'by-folder' => 'media.byFolder',
                    default => 'media.'.$action,
                };
            } elseif (in_array($action, ['create', 'update', 'delete'], true)) {
                $scope = ApiKey::SCOPE_MEDIA_WRITE;
                $permission = 'media.'.$action;
            }
        } elseif ($resource === 'func') {
            $slug = $segments[2] ?? null;
            $scope = ApiKey::SCOPE_FUNCTION_INVOKE;
            $permission = 'function.invoke';
            $constraints['function'] = $slug;
        }

        return [
            'scope' => $scope,
            'permission' => $permission,
            'resource' => $resource ?: null,
            'action' => $action ?: null,
            'constraints' => array_filter($constraints, fn ($value) => $value !== null && $value !== ''),
        ];
    }
}
