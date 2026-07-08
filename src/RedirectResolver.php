<?php

namespace Noo\CraftLocaleRedirect;

class RedirectResolver
{
    /**
     * @param  array<string, string>  $localeUrlMap  Locale code => base URL (unfiltered).
     * @param  array<string, mixed>   $config        Plugin config (reads `fallback`, `only`, `exclude`).
     */
    public function resolve(
        string $rawPath,
        string $hostInfo,
        string $currentAbsoluteUrl,
        string $currentSiteBaseUrl,
        string $primarySiteBaseUrl,
        string $acceptLanguage,
        array $localeUrlMap,
        array $config = [],
    ): ?RedirectDecision {
        $path = strtolower($rawPath);

        foreach ($localeUrlMap as $url) {
            $prefix = strtolower(rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/'));
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                if ($path === $rawPath) {
                    return null;
                }
                return new RedirectDecision($hostInfo . $path, 301, false);
            }
        }

        $isLocaleMatch = $path === '/';

        if ($isLocaleMatch) {
            $availableLocales = LocaleFilter::filter($localeUrlMap, $config);
            $matchedLocale = (new BrowserLocaleMatcher)->match(
                array_keys($availableLocales),
                $acceptLanguage,
            );

            $fallbackUrl = $config['fallback'] ?? $primarySiteBaseUrl;
            $redirectUrl = $matchedLocale !== null ? $availableLocales[$matchedLocale] : $fallbackUrl;
        } else {
            $redirectUrl = rtrim($currentSiteBaseUrl, '/') . $path;
        }

        // Compare against the current URL without its query string: the redirect
        // target is built from the path only, and Module re-appends the query
        // string afterwards. Without stripping it here, a query-string-only
        // request (e.g. ?vd-scan=1) slips past this guard and loops forever.
        $currentUrlWithoutQuery = strtok($currentAbsoluteUrl, '?');

        if (rtrim($redirectUrl, '/') === rtrim($currentUrlWithoutQuery, '/')) {
            return null;
        }

        return new RedirectDecision($redirectUrl, $isLocaleMatch ? 302 : 301, $isLocaleMatch);
    }
}
