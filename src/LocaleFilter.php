<?php

namespace Noo\CraftLocaleRedirect;

class LocaleFilter
{
    /**
     * @param  array<string, string>  $localeUrlMap
     * @return array<string, string>
     */
    public static function filter(array $localeUrlMap, array $config): array
    {
        $only = $config['only'] ?? [];

        if (! empty($only)) {
            return array_intersect_key($localeUrlMap, array_flip($only));
        }

        $exclude = $config['exclude'] ?? [];

        if (! empty($exclude)) {
            return array_diff_key($localeUrlMap, array_flip($exclude));
        }

        return $localeUrlMap;
    }
}
