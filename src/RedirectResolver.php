<?php

namespace Noo\CraftLocaleRedirect;

class RedirectResolver
{
    /**
     * @param  array<string, list<string>>  $localeUrlMap  Locale code => base URLs of all sites using that
     *                                                     locale, in site order (unfiltered).
     * @param  array<string, mixed>         $config        Plugin config (reads `fallback`, `only`, `exclude`,
     *                                                     `crossHostRedirects`).
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

        foreach ($localeUrlMap as $urls) {
            foreach ($urls as $url) {
                $prefix = strtolower(rtrim(self::pathOf($url), '/'));
                if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                    if ($path === $rawPath) {
                        return null;
                    }
                    return new RedirectDecision($hostInfo . $path, 301, false);
                }
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
        $currentHost = self::hostOf($currentSiteBaseUrl);

        // Project a base URL onto the request host: only its locale path
        // prefix is kept, so the visitor stays where they arrived. In
        // cross-host mode the base URL itself is the target, verbatim.
        $toRequestHost = fn (string $url): string => $crossHost
            ? $url
            : self::schemeAwareHostInfo($hostInfo, $url) . self::pathOf($url);

        if ($isLocaleMatch) {
            // Pick each locale's redirect target from the sites that use it:
            // the first one on the current site's host, if any. Locales served
            // only under other hosts are not offered in default mode -- the
            // redirect stays on the request host, where their prefix would
            // point to a page that does not exist. In cross-host mode they
            // stay available through their own base URL.
            $availableLocales = [];

            foreach (LocaleFilter::filter($localeUrlMap, $config) as $locale => $urls) {
                foreach ($urls as $url) {
                    if (self::isOnHost($url, $currentHost)) {
                        $availableLocales[$locale] = $url;
                        break;
                    }
                }

                if ($crossHost && $urls !== [] && ! isset($availableLocales[$locale])) {
                    $availableLocales[$locale] = $urls[0];
                }
            }

            $matchedLocale = (new BrowserLocaleMatcher)->match(
                array_keys($availableLocales),
                $acceptLanguage,
            );

            // An explicit `fallback` is an intentional override, so it is
            // honored verbatim (it may deliberately point elsewhere).
            if ($matchedLocale !== null) {
                $redirectUrl = $toRequestHost($availableLocales[$matchedLocale]);
            } elseif (isset($config['fallback'])) {
                $redirectUrl = $config['fallback'];
            } elseif ($crossHost || self::isOnHost($primarySiteBaseUrl, $currentHost)) {
                $redirectUrl = $toRequestHost($primarySiteBaseUrl);
            } else {
                // The primary site lives on another host, so its locale prefix
                // may not exist here; the current site is the one Craft
                // resolved for this host and is always a valid target.
                $redirectUrl = $toRequestHost($currentSiteBaseUrl);
            }
        } else {
            $redirectUrl = rtrim($toRequestHost($currentSiteBaseUrl), '/') . $path;
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
     * Extract the lowercased host (with port, if any) of a URL, or null
     * when it has none. The port is significant: sites differing only by
     * port serve different content.
     */
    private static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);

        return strtolower($host) . ($port === null ? '' : ':' . $port);
    }

    /**
     * Whether a base URL is served under the given host. A URL without a
     * host of its own (a relative base URL) is served on whatever host the
     * request hits, so it always counts as on-host.
     */
    private static function isOnHost(string $url, ?string $host): bool
    {
        $urlHost = self::hostOf($url);

        return $urlHost === null || $urlHost === $host;
    }

    /**
     * The host info to build a same-host redirect from. When the request
     * arrived over http but the target site is registered as https on the
     * same host, upgrade the scheme -- never the reverse, and never for
     * other hosts (an alias or staging domain keeps the scheme it is
     * actually served on).
     */
    private static function schemeAwareHostInfo(string $hostInfo, string $url): string
    {
        if (
            str_starts_with($hostInfo, 'http://')
            && str_starts_with(strtolower($url), 'https://')
            && self::hostOf($url) === self::hostOf($hostInfo)
        ) {
            return 'https://' . substr($hostInfo, strlen('http://'));
        }

        return $hostInfo;
    }
}
