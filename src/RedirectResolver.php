<?php

namespace Noo\CraftLocaleRedirect;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class RedirectResolver
{
    /**
     * Crawler files (robots.txt and XML sitemaps) are served at their exact
     * path. They are not pages, so they are neither moved under a locale
     * prefix nor lowercased: a sitemap named after a camelCase site handle
     * must keep its case to resolve.
     */
    private const CRAWLER_FILE_PATTERN = '~/(robots\.txt|sitemap(-[^/]+)?\.xml)$~i';

    /**
     * @param  array<string, list<string>>  $localeUrlMap  Locale code => base URLs of all sites using that
     *                                                     locale, in site order (unfiltered).
     * @param  array<string, mixed>         $config
     * @param  string                       $rawPath      Encoded request path, including any application mount.
     */
    public function resolve(
        string $rawPath,
        string $hostInfo,
        string $baseUrl,
        string $currentSiteBaseUrl,
        string $primarySiteBaseUrl,
        string $acceptLanguage,
        array $localeUrlMap,
        array $config = [],
    ): ?RedirectDecision {
        $mount = rtrim($baseUrl, '/');
        if (!str_starts_with($rawPath . '/', $mount . '/')
            || str_starts_with($rawPath, '//') || str_contains($rawPath, '\\')
            || preg_match('/[\x00-\x20\x7f?#]/', $rawPath)) {
            return null;
        }
        $path = substr($rawPath, strlen($mount)) ?: '/';
        $isLocaleMatch = $path === '/';
        if (!$isLocaleMatch && (!($config['canonicalizePaths'] ?? true)
            || preg_match(self::CRAWLER_FILE_PATTERN, $path)
            || $path === '/favicon.ico' || str_starts_with($path . '/', '/.well-known/')
            || $path === '/dist' || str_starts_with($path, '/dist/'))) {
            return null;
        }
        foreach ($config['excludePaths'] ?? [] as $excluded) {
            if (str_starts_with($path . '/', rtrim($excluded, '/') . '/')) {
                return null;
            }
        }

        // A relative Location retains the browser's origin without reflecting any
        // request or forwarded host. Absolute targets are reserved for configured domains.
        $crossHost = (bool) ($config['crossHostRedirects'] ?? false);
        $currentHost = self::hostOf($currentSiteBaseUrl);
        $target = static fn (string $url): string => $crossHost && !self::isOnHost($url, self::hostOf($hostInfo))
            ? $url : self::pathOf($url);

        if (!$isLocaleMatch) {
            $lowerPath = strtolower($rawPath);
            foreach ($localeUrlMap as $urls) {
                foreach ($urls as $url) {
                    $prefix = rtrim(self::pathOf($url), '/');
                    if ($prefix === '' || $prefix === $mount || !str_starts_with($lowerPath . '/', strtolower($prefix) . '/')) {
                        continue;
                    }
                    $canonical = $prefix . substr($rawPath, strlen($prefix));
                    return $canonical === $rawPath ? null : new RedirectDecision($canonical, 301, false);
                }
            }
            $redirectUrl = rtrim($target($currentSiteBaseUrl), '/') . $path;
        } else {
            $availableLocales = [];
            foreach (LocaleFilter::filter($localeUrlMap, $config) as $locale => $urls) {
                foreach ($urls as $url) {
                    if (self::isOnHost($url, $currentHost)) {
                        $availableLocales[$locale] = $url;
                        break;
                    }
                }
                if ($crossHost && $urls !== [] && !isset($availableLocales[$locale])) {
                    $availableLocales[$locale] = $urls[0];
                }
            }
            $matched = (new BrowserLocaleMatcher)->match(array_keys($availableLocales), $acceptLanguage);
            if ($matched !== null) {
                $redirectUrl = $target($availableLocales[$matched]);
            } elseif (isset($config['fallback'])) {
                $redirectUrl = (string) UriResolver::resolve(new Uri($mount . '/'), new Uri($config['fallback']));
            } else {
                $redirectUrl = $target($crossHost || self::isOnHost($primarySiteBaseUrl, $currentHost)
                    ? $primarySiteBaseUrl : $currentSiteBaseUrl);
            }
        }

        // Scheme-only and query-only redirects cannot change the chosen route.
        // In particular, the client may already be on HTTPS behind a TLS terminator.
        if (str_starts_with($redirectUrl, '//') || str_contains($redirectUrl, '\\')
            || (self::isOnHost($redirectUrl, self::hostOf($hostInfo))
                && rtrim(self::pathOf($redirectUrl), '/') === rtrim($rawPath, '/'))) {
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
}
