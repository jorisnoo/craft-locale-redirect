# Craft Locale Redirect

A Craft CMS module that automatically redirects visitors from `/` to their locale-specific home route based on their browser language preferences.

## Features

- Detects the visitor's preferred language from the `Accept-Language` header
- Matches it against your Craft multi-site locales
- Redirects from `/` to the best-matching locale home URL (e.g. `/en`, `/fr`, `/de`)
- Preserves query parameters through the redirect
- Configurable locale exclusions and restrictions
- Zero configuration required for basic usage

## Requirements

- PHP 8.2+
- Craft CMS 5

## Installation

```bash
composer require jorisnoo/craft-locale-redirect
```

Then register the module in your `config/app.php`:

```php
return [
    'modules' => [
        'locale-redirect' => \Noo\CraftLocaleRedirect\Module::class,
    ],
    'bootstrap' => ['locale-redirect'],
];
```

## How It Works

When a visitor hits your site's root URL (`/`), the module:

1. Reads the `Accept-Language` header from the browser
2. Fetches all configured Craft site locales and their URLs
3. Finds the best match between browser preferences and available locales
4. Issues a `302` redirect to the matched locale's home URL

If no browser language matches, the module uses `fallback` or the primary site's URL (the current site's URL when the primary site is on another domain). Query parameters are preserved, except for Craft's routing parameter, normally `p`.

On other site GETs the module also issues **permanent (301) redirects**: `/News/Foo` becomes `/de/News/Foo` if the current site lives at `/de/`, and `/DE/News/Foo` becomes `/de/News/Foo`. Only the configured prefix is canonicalized; case, encoded characters and trailing slashes below it are preserved. Set `canonicalizePaths` to `false` to negotiate the root only.

Crawler files (`robots.txt`, `sitemap.xml`, `sitemap-*.xml`), `/.well-known/`, `/favicon.ico`, and Craft's CP resources are left alone. `excludePaths` adds mount-relative paths or entire subtrees (e.g. `['/api', '/status.txt']`). The module does not redirect console, control panel, action, preview, or explicit front-controller requests.

Same-origin redirects use a path-only `Location`: aliases and staging domains stay on their own origin, and request host headers never supply the redirect authority. Enforce HTTPS at the web server or proxy. By default only locales available on the current site's configured host are offered; `crossHostRedirects` enables configured foreign domains.

If a locale is served at the root, point its language-switcher link to `/?localeRedirect=off` (including the mount in a subdirectory install). `bypassParam` changes the parameter name; an empty string disables it. Root responses vary on `Accept-Language`, even when no redirect occurs. Exclude the root from static/full-page caches that bypass PHP; intermediary caches must respect `Vary` and the bypass query string.

## Configuration

The module works out of the box with no configuration. To customize behavior, create a `config/locale-redirect.php` file in your Craft project:

```php
<?php

return [
    'excludeSites' => [],
    'exclude' => [],
    'only' => [],
    'fallback' => null,
    'crossHostRedirects' => false,
    'canonicalizePaths' => true,
    'excludePaths' => [],
    'bypassParam' => 'localeRedirect',
];
```

### Exclude Sites

Prevent the redirect from triggering on specific sites (by handle). Useful when locale-specific sites already have their own prefix and shouldn't redirect further:

```php
'excludeSites' => ['english', 'german'],
```

### Exclude Locales

Prevent specific locales from being redirect targets. Filters use the primary language subtag: `de-CH` and `de_DE` both select `de`, while `fil` is distinct from `fi`.

```php
'exclude' => ['de', 'it'],
```

### Restrict to Specific Locales

Only allow redirection to specific locales. Takes precedence over `exclude` when both are set:

```php
'only' => ['en', 'fr'],
```

### Fallback URL

The URL to redirect to when no browser locale matches. Use an absolute URL, an origin-relative path, or a path relative to the application's root. A fallback to the current page is ignored.

```php
'fallback' => '/en',
```

### Cross-Host Redirects

By default, redirects stay on the host the request came in on and only use the locale path prefixes from your site base URLs. If your locales live on separate domains instead (e.g. `example.de` and `example.fr`), enable cross-host redirects so the module targets the configured site base URLs verbatim, host included:

```php
'crossHostRedirects' => true,
```

With this enabled, a request on an alias or staging domain can redirect to a registered domain. Configure explicit trusted site base URLs (or environment variables), not a dynamic `@web` alias, for inter-domain redirects. When multiple sites share a primary language subtag, the site on the current configured host wins; otherwise the first-defined site wins.

## Branching and Versioning

This package follows [Semantic Versioning](https://semver.org/). Only Craft 5 is supported.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
