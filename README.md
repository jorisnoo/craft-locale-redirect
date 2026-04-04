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

If no match is found, the visitor is redirected to the primary site's URL (or a configured fallback). Query parameters are preserved through the redirect.

## Configuration

The module works out of the box with no configuration. To customize behavior, create a `config/locale-redirect.php` file in your Craft project:

```php
<?php

return [
    'excludeSites' => [],
    'exclude' => [],
    'only' => [],
    'fallback' => null,
];
```

### Exclude Sites

Prevent the redirect from triggering on specific sites (by handle). Useful when locale-specific sites already have their own prefix and shouldn't redirect further:

```php
'excludeSites' => ['english', 'german'],
```

### Exclude Locales

Prevent specific locales from being redirect targets:

```php
'exclude' => ['de', 'it'],
```

### Restrict to Specific Locales

Only allow redirection to specific locales. Takes precedence over `exclude` when both are set:

```php
'only' => ['en', 'fr'],
```

### Fallback URL

The URL to redirect to when no browser locale matches. Defaults to the primary site URL:

```php
'fallback' => '/en',
```

## Branching and Versioning

This package follows [Semantic Versioning](https://semver.org/). Only Craft 5 is supported.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
