<?php

namespace App\Support;

use Illuminate\Support\Str;

class SecureOutboundUrl
{
    /**
     * 校验对外访问 URL，阻断 SSRF 常见入口：
     * - 仅允许 http/https
     * - 禁止携带用户名密码
     * - 禁止 localhost / 内网 / 链路本地 / 保留地址
     * - 禁止解析到内网地址的域名
     */
    public static function assertAllowed(string $url): void
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048) {
            throw new \InvalidArgumentException('URL 不合法');
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new \InvalidArgumentException('URL 解析失败');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('仅允许 http/https 协议');
        }

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new \InvalidArgumentException('URL 不允许包含认证信息');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw new \InvalidArgumentException('URL 缺少主机名');
        }

        if (self::isBlockedHost($host)) {
            throw new \InvalidArgumentException('禁止访问本地或内网地址');
        }

        foreach (self::resolveHostIps($host) as $ip) {
            if (self::isBlockedIp($ip)) {
                throw new \InvalidArgumentException('禁止访问解析到内网的地址');
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private static function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        foreach ([DNS_A, DNS_AAAA] as $type) {
            try {
                $records = @dns_get_record($host, $type);
                if (! is_array($records)) {
                    continue;
                }

                foreach ($records as $record) {
                    $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                    if (is_string($ip) && $ip !== '') {
                        $ips[] = $ip;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $ips = array_values(array_unique($ips));

        if ($ips === []) {
            throw new \InvalidArgumentException('无法解析目标主机');
        }

        return $ips;
    }

    private static function isBlockedHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return true;
        }

        if (Str::endsWith($host, ['.local', '.internal', '.localhost', '.localdomain'])) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isBlockedIp($host);
        }

        return false;
    }

    private static function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (
            ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )
        ) {
            return true;
        }

        return in_array($ip, ['0.0.0.0', '::', '::1'], true);
    }
}
