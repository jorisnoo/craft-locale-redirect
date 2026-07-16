<?php

use Noo\CraftLocaleRedirect\RedirectDecision;
use Noo\CraftLocaleRedirect\RedirectResolver;

function resolve(array $overrides = []): ?RedirectDecision
{
    return (new RedirectResolver)->resolve(
        rawPath: $overrides['rawPath'] ?? '/',
        hostInfo: $overrides['hostInfo'] ?? 'http://example.test',
        currentAbsoluteUrl: $overrides['currentAbsoluteUrl'] ?? 'http://example.test/',
        currentSiteBaseUrl: $overrides['currentSiteBaseUrl'] ?? 'http://example.test/de/',
        primarySiteBaseUrl: $overrides['primarySiteBaseUrl'] ?? 'http://example.test/de/',
        acceptLanguage: $overrides['acceptLanguage'] ?? '',
        localeUrlMap: $overrides['localeUrlMap'] ?? [
            'de' => ['http://example.test/de/'],
            'en' => ['http://example.test/en/'],
        ],
        config: $overrides['config'] ?? [],
    );
}

it('returns null when path is already on the canonical locale prefix', function () {
    expect(resolve(['rawPath' => '/de/news/foo']))->toBeNull();
    expect(resolve(['rawPath' => '/en/about']))->toBeNull();
});

it('returns null when path is the locale prefix itself (with or without trailing slash)', function () {
    expect(resolve(['rawPath' => '/de']))->toBeNull();
    expect(resolve(['rawPath' => '/de/']))->toBeNull();
});

it('canonicalizes uppercase locale prefix to lowercase with 301', function () {
    $decision = resolve(['rawPath' => '/DE/news/foo']);

    expect($decision)->toBeInstanceOf(RedirectDecision::class);
    expect($decision->url)->toBe('http://example.test/de/news/foo');
    expect($decision->statusCode)->toBe(301);
    expect($decision->varyByLocale)->toBeFalse();
});

it('canonicalizes mixed case in path below the locale prefix to lowercase', function () {
    $decision = resolve(['rawPath' => '/de/News/Foo']);

    expect($decision->url)->toBe('http://example.test/de/news/foo');
    expect($decision->statusCode)->toBe(301);
});

it('preserves trailing slash when canonicalizing case', function () {
    $decision = resolve(['rawPath' => '/De/']);

    expect($decision->url)->toBe('http://example.test/de/');
});

it('redirects root to the locale matching Accept-Language with 302 and varyByLocale', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'de-CH,de;q=0.9,en;q=0.5',
    ]);

    expect($decision->url)->toBe('http://example.test/de/');
    expect($decision->statusCode)->toBe(302);
    expect($decision->varyByLocale)->toBeTrue();
});

it('redirects root to the primary base URL when no Accept-Language matches', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'ja',
        'primarySiteBaseUrl' => 'http://example.test/de/',
    ]);

    expect($decision->url)->toBe('http://example.test/de/');
});

it('honors a configured fallback URL when no Accept-Language matches', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'ja',
        'primarySiteBaseUrl' => 'http://example.test/de/',
        'config' => ['fallback' => 'http://example.test/en/'],
    ]);

    expect($decision->url)->toBe('http://example.test/en/');
});

it('redirects an unprefixed path to the current site base URL with the lowercased path', function () {
    $decision = resolve([
        'rawPath' => '/news/foo',
        'currentAbsoluteUrl' => 'http://example.test/news/foo',
    ]);

    expect($decision->url)->toBe('http://example.test/de/news/foo');
    expect($decision->statusCode)->toBe(301);
    expect($decision->varyByLocale)->toBeFalse();
});

it('lowercases mixed-case unprefixed paths and 301s in a single hop', function () {
    $decision = resolve([
        'rawPath' => '/News/Foo',
        'currentAbsoluteUrl' => 'http://example.test/News/Foo',
    ]);

    expect($decision->url)->toBe('http://example.test/de/news/foo');
    expect($decision->statusCode)->toBe(301);
});

it('preserves trailing slash on unprefixed redirects', function () {
    $decision = resolve([
        'rawPath' => '/news/foo/',
        'currentAbsoluteUrl' => 'http://example.test/news/foo/',
    ]);

    expect($decision->url)->toBe('http://example.test/de/news/foo/');
});

it('returns null when the computed redirect equals the current absolute URL (loop prevention)', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'en',
        'currentAbsoluteUrl' => 'http://example.test/',
        'primarySiteBaseUrl' => 'http://example.test/',
        'currentSiteBaseUrl' => 'http://example.test/',
        'localeUrlMap' => ['en' => ['http://example.test/']],
    ]);

    expect($decision)->toBeNull();
});

it('returns null at root when only a query string differs from the canonical URL (loop prevention)', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'en',
        'currentAbsoluteUrl' => 'http://example.test/?vd-scan=1',
        'primarySiteBaseUrl' => 'http://example.test/',
        'currentSiteBaseUrl' => 'http://example.test/',
        'localeUrlMap' => ['en' => ['http://example.test/']],
    ]);

    expect($decision)->toBeNull();
});

it('returns null when an already-canonical unprefixed path carries a query string (loop prevention)', function () {
    $decision = resolve([
        'rawPath' => '/kalender/foo',
        'currentAbsoluteUrl' => 'http://example.test/kalender/foo?vd-scan=1',
        'currentSiteBaseUrl' => 'http://example.test/',
        'localeUrlMap' => ['de' => ['http://example.test/']],
    ]);

    expect($decision)->toBeNull();
});

it('still redirects to canonicalize an unprefixed path even when a query string is present', function () {
    $decision = resolve([
        'rawPath' => '/news/foo',
        'currentAbsoluteUrl' => 'http://example.test/news/foo?vd-scan=1',
        'currentSiteBaseUrl' => 'http://example.test/de/',
    ]);

    expect($decision->url)->toBe('http://example.test/de/news/foo');
    expect($decision->statusCode)->toBe(301);
});

it('returns null when the current URL is percent-encoded but already canonical (loop prevention)', function () {
    // getPathInfo() is urldecoded ("/münchen") while getAbsoluteUrl() stays
    // percent-encoded ("/m%C3%BCnchen"). The guard must compare like for like.
    $decision = resolve([
        'rawPath' => '/münchen',
        'currentAbsoluteUrl' => 'http://example.test/m%C3%BCnchen',
        'currentSiteBaseUrl' => 'http://example.test/',
        'localeUrlMap' => ['de' => ['http://example.test/']],
    ]);

    expect($decision)->toBeNull();
});

it('ignores sites with no URL path prefix when checking for a locale match', function () {
    $decision = resolve([
        'rawPath' => '/some-path',
        'currentAbsoluteUrl' => 'http://example.test/some-path',
        'currentSiteBaseUrl' => 'http://example.test/de/',
        'localeUrlMap' => [
            'en' => ['http://example.test/'],
            'de' => ['http://example.test/de/'],
        ],
    ]);

    expect($decision->url)->toBe('http://example.test/de/some-path');
});

it('uses the unfiltered locale map for the prefix bail check', function () {
    $decision = resolve([
        'rawPath' => '/de/news/foo',
        'currentAbsoluteUrl' => 'http://example.test/de/news/foo',
        'config' => ['exclude' => ['de']],
    ]);

    expect($decision)->toBeNull();
});

it('uses the filtered locale map for Accept-Language matching at root', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'de',
        'primarySiteBaseUrl' => 'http://example.test/en/',
        'config' => ['exclude' => ['de']],
    ]);

    expect($decision->url)->toBe('http://example.test/en/');
});

it('matches Accept-Language only against locales allowed by only filter', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'en',
        'config' => ['only' => ['en']],
    ]);

    expect($decision->url)->toBe('http://example.test/en/');
});

it('falls back to the primary base URL when only filter excludes the Accept-Language match', function () {
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'de',
        'primarySiteBaseUrl' => 'http://example.test/de/',
        'config' => ['only' => ['en']],
    ]);

    expect($decision->url)->toBe('http://example.test/de/');
});

it('still bails on a locale prefix even when only filter is active', function () {
    $decision = resolve([
        'rawPath' => '/de/news/foo',
        'currentAbsoluteUrl' => 'http://example.test/de/news/foo',
        'config' => ['only' => ['en']],
    ]);

    expect($decision)->toBeNull();
});

it('matches locale prefix case-insensitively against canonical lowercase prefix', function () {
    $decision = resolve([
        'rawPath' => '/EN/about',
        'currentAbsoluteUrl' => 'http://example.test/EN/about',
    ]);

    expect($decision->url)->toBe('http://example.test/en/about');
});

it('keeps the visitor on an alias host when redirecting root to a locale', function () {
    // Request arrives on an alias/staging domain that is not one of the
    // configured site hosts. The locale redirect must stay on that host.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://staging.example.test',
        'currentAbsoluteUrl' => 'http://staging.example.test/',
        'acceptLanguage' => 'de-CH,de;q=0.9',
    ]);

    expect($decision->url)->toBe('http://staging.example.test/de/');
    expect($decision->statusCode)->toBe(302);
});

it('keeps the visitor on an alias host when falling back to the primary base URL', function () {
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://staging.example.test',
        'currentAbsoluteUrl' => 'http://staging.example.test/',
        'acceptLanguage' => 'ja',
        'primarySiteBaseUrl' => 'http://example.test/de/',
    ]);

    expect($decision->url)->toBe('http://staging.example.test/de/');
});

it('keeps the visitor on an alias host when canonicalizing an unprefixed path', function () {
    $decision = resolve([
        'rawPath' => '/news/foo',
        'hostInfo' => 'http://staging.example.test',
        'currentAbsoluteUrl' => 'http://staging.example.test/news/foo',
        'currentSiteBaseUrl' => 'http://example.test/de/',
    ]);

    expect($decision->url)->toBe('http://staging.example.test/de/news/foo');
    expect($decision->statusCode)->toBe(301);
});

it('redirects root to a locale on the current host in a per-edition multisite', function () {
    // Per-edition multisite: every edition lives on its own domain with its
    // own de/fr sites, so each locale maps to several base URLs. The resolver
    // picks the current host's entry, regardless of site order or where the
    // primary site lives.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://edition24.test',
        'currentAbsoluteUrl' => 'http://edition24.test/',
        'currentSiteBaseUrl' => 'http://edition24.test/de/',
        'primarySiteBaseUrl' => 'http://primary.test/de/',
        'acceptLanguage' => 'fr-CH,fr;q=0.9',
        'localeUrlMap' => [
            'de' => ['http://edition22.test/de/', 'http://edition24.test/de/'],
            'fr' => ['http://edition22.test/fr/', 'http://edition24.test/fr/'],
        ],
    ]);

    expect($decision->url)->toBe('http://edition24.test/fr/');
    expect($decision->statusCode)->toBe(302);
});

it('does not offer a locale served only under another host', function () {
    // `en` exists only on another edition's domain; redirecting to /en/ on
    // this host would land on a page that does not exist. Fall back instead.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://edition24.test',
        'currentAbsoluteUrl' => 'http://edition24.test/',
        'currentSiteBaseUrl' => 'http://edition24.test/de/',
        'primarySiteBaseUrl' => 'http://edition24.test/de/',
        'acceptLanguage' => 'en',
        'localeUrlMap' => [
            'de' => ['http://edition24.test/de/'],
            'en' => ['http://edition22.test/en/'],
        ],
    ]);

    expect($decision->url)->toBe('http://edition24.test/de/');
});

it('offers a locale with a relative base URL, which is served on every host', function () {
    // A root-relative base URL carries no host of its own, so it must not be
    // treated as foreign; its prefix works on the current host by definition.
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'fr',
        'localeUrlMap' => [
            'de' => ['http://example.test/de/'],
            'fr' => ['/fr/'],
        ],
    ]);

    expect($decision->url)->toBe('http://example.test/fr/');
    expect($decision->statusCode)->toBe(302);
});

it('does not offer a locale served on the same hostname but a different port', function () {
    // Sites differing only by port serve different content; /en/ exists only
    // on port 3000, so it must not be offered on the port-80 site.
    $decision = resolve([
        'rawPath' => '/',
        'acceptLanguage' => 'en',
        'primarySiteBaseUrl' => 'http://example.test/de/',
        'localeUrlMap' => [
            'de' => ['http://example.test/de/'],
            'en' => ['http://example.test:3000/en/'],
        ],
    ]);

    expect($decision->url)->toBe('http://example.test/de/');
});

it('still bails on a second prefix of a locale that has several sites on one host', function () {
    // Same 2-letter locale twice on the current host with different prefixes
    // (e.g. de-DE at /de/, de-CH at /ch/): both prefixes are canonical.
    expect(resolve([
        'rawPath' => '/ch/news',
        'currentAbsoluteUrl' => 'http://example.test/ch/news',
        'localeUrlMap' => [
            'de' => ['http://example.test/de/', 'http://example.test/ch/'],
        ],
    ]))->toBeNull();
});

it('upgrades an http request to a same-host https site URL', function () {
    // The sites are registered as https; a visitor arriving over plain http
    // on the same host is moved onto the configured scheme.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://example.test',
        'currentAbsoluteUrl' => 'http://example.test/',
        'currentSiteBaseUrl' => 'https://example.test/de/',
        'primarySiteBaseUrl' => 'https://example.test/de/',
        'acceptLanguage' => 'de',
        'localeUrlMap' => [
            'de' => ['https://example.test/de/'],
            'en' => ['https://example.test/en/'],
        ],
    ]);

    expect($decision->url)->toBe('https://example.test/de/');
});

it('does not force the registered scheme onto a different (alias) host', function () {
    // An http-only staging alias keeps its scheme; only the locale prefix is
    // taken from the https site URL.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://staging.example.test',
        'currentAbsoluteUrl' => 'http://staging.example.test/',
        'currentSiteBaseUrl' => 'https://example.test/de/',
        'primarySiteBaseUrl' => 'https://example.test/de/',
        'acceptLanguage' => 'de',
        'localeUrlMap' => [
            'de' => ['https://example.test/de/'],
            'en' => ['https://example.test/en/'],
        ],
    ]);

    expect($decision->url)->toBe('http://staging.example.test/de/');
});

it('falls back to the current site path when the primary site lives on another host', function () {
    // No Accept-Language match and the primary site is another edition, whose
    // locale prefix may not exist on this host. The current site is the one
    // Craft resolved for this host, so its prefix is always a valid target.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://edition24.test',
        'currentAbsoluteUrl' => 'http://edition24.test/',
        'currentSiteBaseUrl' => 'http://edition24.test/fr/',
        'primarySiteBaseUrl' => 'http://primary.test/de/',
        'acceptLanguage' => 'ja',
        'localeUrlMap' => [
            'fr' => ['http://edition24.test/fr/'],
            'de' => ['http://primary.test/de/'],
        ],
    ]);

    expect($decision->url)->toBe('http://edition24.test/fr/');
    expect($decision->statusCode)->toBe(302);
});

it('still bails on a locale prefix served only under another host', function () {
    // The bail check uses the unfiltered map: /en/... is canonical for some
    // edition, so it must not be rewritten under this edition's prefix.
    expect(resolve([
        'rawPath' => '/en/about',
        'hostInfo' => 'http://edition24.test',
        'currentAbsoluteUrl' => 'http://edition24.test/en/about',
        'currentSiteBaseUrl' => 'http://edition24.test/de/',
        'localeUrlMap' => [
            'de' => ['http://edition24.test/de/'],
            'en' => ['http://edition22.test/en/'],
        ],
    ]))->toBeNull();
});

it('redirects root across hosts when crossHostRedirects is enabled', function () {
    // Host-based locale setup: each locale lives on its own domain with no
    // path prefix. Opting in restores redirects to the site URLs verbatim.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://de.example.test',
        'currentAbsoluteUrl' => 'http://de.example.test/',
        'currentSiteBaseUrl' => 'http://de.example.test/',
        'primarySiteBaseUrl' => 'http://de.example.test/',
        'acceptLanguage' => 'en',
        'localeUrlMap' => [
            'de' => ['http://de.example.test/'],
            'en' => ['http://en.example.test/'],
        ],
        'config' => ['crossHostRedirects' => true],
    ]);

    expect($decision->url)->toBe('http://en.example.test/');
    expect($decision->statusCode)->toBe(302);
    expect($decision->varyByLocale)->toBeTrue();
});

it('returns null in cross-host mode when the matched locale is the current host (loop prevention)', function () {
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://de.example.test',
        'currentAbsoluteUrl' => 'http://de.example.test/',
        'currentSiteBaseUrl' => 'http://de.example.test/',
        'primarySiteBaseUrl' => 'http://de.example.test/',
        'acceptLanguage' => 'de',
        'localeUrlMap' => [
            'de' => ['http://de.example.test/'],
            'en' => ['http://en.example.test/'],
        ],
        'config' => ['crossHostRedirects' => true],
    ]);

    expect($decision)->toBeNull();
});

it('falls back to the primary site URL verbatim in cross-host mode', function () {
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://de.example.test',
        'currentAbsoluteUrl' => 'http://de.example.test/',
        'currentSiteBaseUrl' => 'http://de.example.test/',
        'primarySiteBaseUrl' => 'http://en.example.test/',
        'acceptLanguage' => 'ja',
        'localeUrlMap' => [
            'de' => ['http://de.example.test/'],
            'en' => ['http://en.example.test/'],
        ],
        'config' => ['crossHostRedirects' => true],
    ]);

    expect($decision->url)->toBe('http://en.example.test/');
});

it('does not rewrite content paths in cross-host mode (loop prevention)', function () {
    // With no path prefix on the current site, an unprefixed path is already
    // canonical: the target equals the current URL, so nothing happens.
    $decision = resolve([
        'rawPath' => '/news/foo',
        'hostInfo' => 'http://de.example.test',
        'currentAbsoluteUrl' => 'http://de.example.test/news/foo',
        'currentSiteBaseUrl' => 'http://de.example.test/',
        'localeUrlMap' => [
            'de' => ['http://de.example.test/'],
            'en' => ['http://en.example.test/'],
        ],
        'config' => ['crossHostRedirects' => true],
    ]);

    expect($decision)->toBeNull();
});

it('stays put in cross-host mode when the visitor is already on a site serving their locale', function () {
    // Two sites share the 2-letter locale `de` on different domains. A
    // visitor already on one of them is not bounced to the other; the
    // current host's entry wins the tie-break.
    $decision = resolve([
        'rawPath' => '/',
        'hostInfo' => 'http://example.ch',
        'currentAbsoluteUrl' => 'http://example.ch/',
        'currentSiteBaseUrl' => 'http://example.ch/',
        'primarySiteBaseUrl' => 'http://example.de/',
        'acceptLanguage' => 'de',
        'localeUrlMap' => [
            'de' => ['http://example.de/', 'http://example.ch/'],
        ],
        'config' => ['crossHostRedirects' => true],
    ]);

    expect($decision)->toBeNull();
});

it('redirects to the site base URL verbatim in cross-host mode with prefixed sites', function () {
    // Mixed setup: locales differ by host and by path prefix. The unprefixed
    // path is canonicalized onto the current site's own base URL.
    $decision = resolve([
        'rawPath' => '/news/foo',
        'hostInfo' => 'http://example.de',
        'currentAbsoluteUrl' => 'http://example.de/news/foo',
        'currentSiteBaseUrl' => 'http://example.de/de/',
        'localeUrlMap' => [
            'de' => ['http://example.de/de/'],
            'fr' => ['http://example.fr/fr/'],
        ],
        'config' => ['crossHostRedirects' => true],
    ]);

    expect($decision->url)->toBe('http://example.de/de/news/foo');
    expect($decision->statusCode)->toBe(301);
});

it('preserves the host info when canonicalizing case', function () {
    $decision = resolve([
        'rawPath' => '/DE/',
        'hostInfo' => 'https://staging.example.test:8443',
        'currentAbsoluteUrl' => 'https://staging.example.test:8443/DE/',
    ]);

    expect($decision->url)->toBe('https://staging.example.test:8443/de/');
});
