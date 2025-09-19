<?php

namespace NcooDev\HormLogger\Support;

class DataObfuscator
{
    public static function obfuscate(array $data): array
    {
        $fieldsToObfuscate = config('horm.obfuscate_fields', []);

        if (empty($fieldsToObfuscate)) {
            return $data;
        }

        return self::obfuscateRecursive($data, $fieldsToObfuscate);
    }

    protected static function obfuscateRecursive(array $data, array $fieldsToObfuscate): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $fieldsToObfuscate, true)) {
                $data[$key] = self::mask($value);
            } elseif (is_array($value)) {
                $data[$key] = self::obfuscateRecursive($value, $fieldsToObfuscate);
            }
        }

        return $data;
    }

    protected static function mask($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '***REDACTED***';
        }

        $stringValue = (string) $value;
        $length = strlen($stringValue);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        if ($length <= 8) {
            return substr($stringValue, 0, 2).str_repeat('*', $length - 2);
        }

        return substr($stringValue, 0, 3).str_repeat('*', min($length - 6, 10)).substr($stringValue, -3);
    }

    public static function shouldExcludeIncomingUrl(string $url): bool
    {
        if (! config('horm.enabled', true)) {
            return true;
        }

        $excludedUrls = config('horm.excluded_incoming_urls', []);

        return self::matchesUrlPatterns($url, $excludedUrls, 'incoming');
    }

    public static function shouldExcludeOutgoingUrl(string $url): bool
    {
        if (! config('horm.enabled', true)) {
            return true;
        }

        $excludedUrls = config('horm.excluded_outgoing_urls', []);

        return self::matchesUrlPatterns($url, $excludedUrls, 'outgoing');
    }

    /**
     * @deprecated Use shouldExcludeIncomingUrl() or shouldExcludeOutgoingUrl() instead
     */
    public static function shouldExcludeUrl(string $url): bool
    {
        // Fallback to old behavior for backward compatibility
        $excludedUrls = config('horm.excluded_urls', []);
        if (! empty($excludedUrls)) {
            return self::matchesUrlPatterns($url, $excludedUrls, 'legacy');
        }

        // Default to incoming behavior
        return self::shouldExcludeIncomingUrl($url);
    }

    protected static function matchesUrlPatterns(string $url, array $excludedUrls, string $type): bool
    {
        if (empty($excludedUrls)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $cleanPath = ltrim($path, '/');
        $host = $parsedUrl['host'] ?? '';
        $scheme = $parsedUrl['scheme'] ?? '';

        foreach ($excludedUrls as $pattern) {
            // Check if pattern is a full URL (contains :// or starts with http/https)
            if (str_contains($pattern, '://') || str_starts_with($pattern, 'http')) {
                // Full URL pattern matching - escape special regex chars except * which becomes .*
                $regexPattern = preg_quote($pattern, '/');
                $regexPattern = str_replace('\*', '.*', $regexPattern);

                // Special handling for subdomain wildcards like *.example.com
                // Make them match both subdomain.example.com AND example.com
                if (str_contains($pattern, '*.')) {
                    $alternativePattern = str_replace('.*\.', '(?:.*\.)?', $regexPattern);
                    if (preg_match('/^'.$alternativePattern.'$/i', $url)) {
                        return true;
                    }
                    $urlWithoutQuery = $scheme.'://'.$host.$path;
                    if (preg_match('/^'.$alternativePattern.'$/i', $urlWithoutQuery)) {
                        return true;
                    }
                }

                if (preg_match('/^'.$regexPattern.'$/i', $url)) {
                    return true;
                }

                // Also check without query string for flexibility
                $urlWithoutQuery = $scheme.'://'.$host.$path;
                if (preg_match('/^'.$regexPattern.'$/i', $urlWithoutQuery)) {
                    return true;
                }
            } else {
                // Path-only pattern matching (existing logic)
                $regexPattern = str_replace(['/', '*'], ['\/', '.*'], $pattern);

                // Check both with and without leading slash
                if (preg_match('/^'.$regexPattern.'$/i', $cleanPath) ||
                    preg_match('/^'.$regexPattern.'$/i', $path)) {
                    return true;
                }
            }
        }

        return false;
    }
}
