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
            'de' => 'http://example.test/de/',
            'en' => 'http://example.test/en/',
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
        'localeUrlMap' => ['en' => 'http://example.test/'],
    ]);

    expect($decision)->toBeNull();
});

it('ignores sites with no URL path prefix when checking for a locale match', function () {
    $decision = resolve([
        'rawPath' => '/some-path',
        'currentAbsoluteUrl' => 'http://example.test/some-path',
        'currentSiteBaseUrl' => 'http://example.test/de/',
        'localeUrlMap' => [
            'en' => 'http://example.test/',
            'de' => 'http://example.test/de/',
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

it('preserves the host info when canonicalizing case', function () {
    $decision = resolve([
        'rawPath' => '/DE/',
        'hostInfo' => 'https://staging.example.test:8443',
        'currentAbsoluteUrl' => 'https://staging.example.test:8443/DE/',
    ]);

    expect($decision->url)->toBe('https://staging.example.test:8443/de/');
});
