<?php

use Noo\CraftLocaleRedirect\BrowserLocaleMatcher;

beforeEach(function () {
    $this->matcher = new BrowserLocaleMatcher;
});

it('returns null for empty accept-language header', function () {
    expect($this->matcher->match(['en', 'de'], ''))->toBeNull();
});

it('returns null for empty available locales', function () {
    expect($this->matcher->match([], 'en-US,en;q=0.9'))->toBeNull();
});

it('matches an exact locale', function () {
    expect($this->matcher->match(['en', 'de', 'fr'], 'de'))->toBe('de');
});

it('matches a locale from a regional variant', function () {
    expect($this->matcher->match(['en', 'de'], 'de-CH,de;q=0.9,en;q=0.8'))->toBe('de');
});

it('respects quality values and returns the highest priority match', function () {
    expect($this->matcher->match(['en', 'fr'], 'fr;q=0.8,en;q=0.9'))->toBe('en');
});

it('returns null when no locale matches', function () {
    expect($this->matcher->match(['en', 'de'], 'ja,zh;q=0.9'))->toBeNull();
});

it('handles wildcard language tag', function () {
    expect($this->matcher->match(['en'], '*'))->toBeNull();
});

it('matches the first available locale when quality is equal', function () {
    $result = $this->matcher->match(['fr', 'de'], 'fr,de');
    expect($result)->toBe('fr');
});

it('handles whitespace in the header', function () {
    expect($this->matcher->match(['en', 'de'], ' de-AT , en ; q=0.5 '))->toBe('de');
});
