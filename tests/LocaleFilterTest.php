<?php

use Noo\CraftLocaleRedirect\LocaleFilter;

beforeEach(function () {
    $this->localeUrlMap = [
        'en' => '/en',
        'fr' => '/fr',
        'de' => '/de',
    ];
});

it('returns all locales when no config is set', function () {
    $result = LocaleFilter::filter($this->localeUrlMap, []);

    expect($result)->toBe($this->localeUrlMap);
});

it('returns all locales when only and exclude are empty', function () {
    $result = LocaleFilter::filter($this->localeUrlMap, ['only' => [], 'exclude' => []]);

    expect($result)->toBe($this->localeUrlMap);
});

it('excludes specified locales', function () {
    $result = LocaleFilter::filter($this->localeUrlMap, ['exclude' => ['fr']]);

    expect($result)->toBe(['en' => '/en', 'de' => '/de']);
});

it('excludes multiple locales', function () {
    $result = LocaleFilter::filter($this->localeUrlMap, ['exclude' => ['fr', 'de']]);

    expect($result)->toBe(['en' => '/en']);
});

it('restricts to only specified locales', function () {
    $result = LocaleFilter::filter($this->localeUrlMap, ['only' => ['en', 'fr']]);

    expect($result)->toBe(['en' => '/en', 'fr' => '/fr']);
});

it('only takes precedence over exclude', function () {
    $result = LocaleFilter::filter($this->localeUrlMap, [
        'only' => ['en', 'fr'],
        'exclude' => ['fr'],
    ]);

    expect($result)->toBe(['en' => '/en', 'fr' => '/fr']);
});

it('returns empty array when only contains no matching locales', function () {
    $result = LocaleFilter::filter($this->localeUrlMap, ['only' => ['ja']]);

    expect($result)->toBe([]);
});
