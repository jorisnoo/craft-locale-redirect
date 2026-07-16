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

            // The locale map and the primary base URL are built from the sites'
            // configured base URLs, so they carry whatever host those sites are
            // registered under -- which need not be the host the request came in
            // on. Several sites can also share a locale path prefix across
            // different hosts (e.g. a per-edition multisite where every edition
            // has its own domain but the same `/de` and `/fr` prefixes), so the
            // map's host is not a reliable redirect target at all. Only the path
            // prefix identifies the locale; keep the visitor on the host they
            // arrived on. An explicit `fallback` is an intentional override, so
            // it is honored verbatim (it may deliberately point elsewhere).
            if ($matchedLocale !== null) {
                $redirectUrl = $hostInfo . self::pathOf($availableLocales[$matchedLocale]);
            } elseif (isset($config['fallback'])) {
                $redirectUrl = $config['fallback'];
            } else {
                $redirectUrl = $hostInfo . self::pathOf($primarySiteBaseUrl);
            }
        } else {
            // Keep the visitor on the current host here too: the current site's
            // base URL contributes only the locale path prefix, not its host.
            $redirectUrl = $hostInfo . rtrim(self::pathOf($currentSiteBaseUrl), '/') . $path;
        }

        // Normalize the current URL before comparing it to the redirect target,
        // which is built from the decoded path ($rawPath comes from a urldecoded
        // getPathInfo()). getAbsoluteUrl() keeps the client's percent-encoding
        // and its query string, so without stripping the query and decoding the
        // path a request that is already canonical (e.g. ?vd-scan=1, or an
        // umlaut slug like /m%C3%BCnchen) slips past this guard and loops forever.
        $currentUrlNormalized = rawurldecode(strtok($currentAbsoluteUrl, '?'));

        if (rtrim($redirectUrl, '/') === rtrim($currentUrlNormalized, '/')) {
            return null;
        }

        return new RedirectDecision($redirectUrl, $isLocaleMatch ? 302 : 301, $isLocaleMatch);
    }

    /**
     * Extract the path component of a URL, defaulting to '/'.
     */
    private static function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return ($path === null || $path === '') ? '/' : $path;
    }
}
