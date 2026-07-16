<?php

namespace Noo\CraftLocaleRedirect;

class RedirectResolver
{
    /**
     * @param  array<string, string>  $localeUrlMap  Locale code => base URL (unfiltered). When a locale is
     *                                               served under several hosts, the entry should carry the
     *                                               current site's host (see Module::getLocaleUrlMap()).
     * @param  array<string, mixed>   $config        Plugin config (reads `fallback`, `only`, `exclude`,
     *                                               `crossHostRedirects`).
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

        // By default only the locale path prefix identifies a locale, and the
        // visitor is kept on the host they arrived on -- the base URLs carry
        // whatever host their site is registered under, which need not be the
        // host the request came in on (an alias or staging domain, or another
        // edition in a per-edition multisite). Setups that distinguish locales
        // by host instead of path prefix opt out via `crossHostRedirects` and
        // redirect to the configured site base URLs verbatim.
        $crossHost = (bool) ($config['crossHostRedirects'] ?? false);

        if ($isLocaleMatch) {
            $availableLocales = LocaleFilter::filter($localeUrlMap, $config);
            $currentHost = self::hostOf($currentSiteBaseUrl);

            if (! $crossHost) {
                // Only offer locales served under the current site's host: a
                // locale that only exists under another host would point to a
                // page that does not exist here. The unfiltered map still
                // drives the prefix bail check above, where foreign prefixes
                // are safe to honor.
                $availableLocales = array_filter(
                    $availableLocales,
                    fn (string $url): bool => self::hostOf($url) === $currentHost,
                );
            }

            $matchedLocale = (new BrowserLocaleMatcher)->match(
                array_keys($availableLocales),
                $acceptLanguage,
            );

            // An explicit `fallback` is an intentional override, so it is
            // honored verbatim (it may deliberately point elsewhere).
            if ($matchedLocale !== null) {
                $redirectUrl = $crossHost
                    ? $availableLocales[$matchedLocale]
                    : $hostInfo . self::pathOf($availableLocales[$matchedLocale]);
            } elseif (isset($config['fallback'])) {
                $redirectUrl = $config['fallback'];
            } elseif ($crossHost) {
                $redirectUrl = $primarySiteBaseUrl;
            } elseif (self::hostOf($primarySiteBaseUrl) === $currentHost) {
                $redirectUrl = $hostInfo . self::pathOf($primarySiteBaseUrl);
            } else {
                // The primary site lives on another host, so its locale prefix
                // may not exist here; the current site is the one Craft
                // resolved for this host and is always a valid target.
                $redirectUrl = $hostInfo . self::pathOf($currentSiteBaseUrl);
            }
        } else {
            $redirectUrl = $crossHost
                ? rtrim($currentSiteBaseUrl, '/') . $path
                : $hostInfo . rtrim(self::pathOf($currentSiteBaseUrl), '/') . $path;
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

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * Extract the lowercased host of a URL, or null when it has none.
     */
    private static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }
}
