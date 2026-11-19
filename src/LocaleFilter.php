<?php

namespace Noo\CraftLocaleRedirect;

class LocaleFilter
{
    public static function primaryLanguage(string $locale): string
    {
        return strtolower(substr($locale, 0, strcspn($locale, '-_')));
    }

    /**
     * @template T
     * @param array<string, T> $localeUrlMap
     * @param array<string, mixed> $config
     * @return array<string, T>
     */
    public static function filter(array $localeUrlMap, array $config): array
    {
        $only = $config['only'] ?? [];

        if (! empty($only)) {
            return array_intersect_key($localeUrlMap, array_flip(array_map(self::primaryLanguage(...), $only)));
        }

        $exclude = $config['exclude'] ?? [];

        if (! empty($exclude)) {
            return array_diff_key($localeUrlMap, array_flip(array_map(self::primaryLanguage(...), $exclude)));
        }

        return $localeUrlMap;
    }
}
