<?php

namespace App\Support;

class QuestionMediaUrl
{
    private const LEGACY_MEDIA_PREFIX = '/storage/media/';
    private const MEDIA_PREFIX = '/media/';
    private const FILENAME_PATTERN = '/\A[A-Za-z0-9]{20}\.(?:jpe?g|png|gif|webp|svg)\z/i';

    public static function normalizeMarkdown(?string $markdown): string
    {
        if ($markdown === null || $markdown === '') {
            return '';
        }

        return preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)([^)]*)\)/', function (array $matches): string {
            $url = self::normalizeUrl($matches[2]);

            return '!['.$matches[1].']('.$url.$matches[3].')';
        }, $markdown);
    }

    public static function normalizeUrl(string $url): string
    {
        $value = trim($url);

        if (str_starts_with($value, self::MEDIA_PREFIX)) {
            return $value;
        }

        $host = parse_url($value, PHP_URL_HOST);
        $path = parse_url($value, PHP_URL_PATH) ?: $value;

        if (! str_starts_with($path, self::LEGACY_MEDIA_PREFIX)) {
            return $value;
        }

        if ($host !== null && ! self::isAppHost($host)) {
            return $value;
        }

        $filename = substr($path, strlen(self::LEGACY_MEDIA_PREFIX));
        if (! preg_match(self::FILENAME_PATTERN, $filename)) {
            return $value;
        }

        return self::MEDIA_PREFIX.$filename;
    }

    private static function isAppHost(string $host): bool
    {
        $knownHosts = array_filter([
            'dsat.bkse.vn',
            parse_url((string) config('app.url'), PHP_URL_HOST),
            app()->bound('request') ? request()->getHost() : null,
        ]);

        return in_array($host, $knownHosts, true);
    }
}
