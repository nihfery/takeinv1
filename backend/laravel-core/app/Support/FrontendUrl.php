<?php

namespace App\Support;

use Illuminate\Http\Request;

class FrontendUrl
{
    public static function provider(?Request $request = null): string
    {
        return self::localizeLoopbackUrl(
            (string) config('services.frontend.provider_url', 'http://127.0.0.1:5173'),
            $request
        );
    }

    public static function customer(?Request $request = null): string
    {
        return self::localizeLoopbackUrl(
            (string) config('services.frontend.customer_url', 'http://127.0.0.1:5174'),
            $request
        );
    }

    private static function localizeLoopbackUrl(string $url, ?Request $request = null): string
    {
        $url = rtrim($url, '/');
        $request ??= request();

        $requestHost = $request?->getHost();
        $parts = parse_url($url);
        $configuredHost = $parts['host'] ?? null;

        if (
            ! $requestHost
            || ! $configuredHost
            || self::isLoopbackHost($requestHost)
            || ! self::isLoopbackHost($configuredHost)
        ) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? $request->getScheme();
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return "{$scheme}://{$requestHost}{$port}{$path}{$query}{$fragment}";
    }

    private static function isLoopbackHost(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), ['127.0.0.1', 'localhost', '::1'], true);
    }
}
