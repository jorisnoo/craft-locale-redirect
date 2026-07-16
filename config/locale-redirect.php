<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Exclude Sites
    |--------------------------------------------------------------------------
    |
    | An array of site handles that should NOT trigger the locale redirect.
    | Useful for excluding locale-specific sites that already have a prefix.
    | For example: ['english', 'german']
    |
    */

    'excludeSites' => [],

    /*
    |--------------------------------------------------------------------------
    | Exclude Locales
    |--------------------------------------------------------------------------
    |
    | An array of locale codes to exclude from being redirect targets.
    | For example: ['de', 'it']
    |
    */

    'exclude' => [],

    /*
    |--------------------------------------------------------------------------
    | Only Locales
    |--------------------------------------------------------------------------
    |
    | An array of locale codes to restrict redirection to. When set, only
    | these locales will be considered as redirect targets. Takes precedence
    | over "exclude" if both are set.
    |
    */

    'only' => [],

    /*
    |--------------------------------------------------------------------------
    | Fallback URL
    |--------------------------------------------------------------------------
    |
    | The URL to redirect to when no browser locale matches. When null,
    | the primary Craft site URL is used.
    |
    */

    'fallback' => null,

    /*
    |--------------------------------------------------------------------------
    | Cross-Host Redirects
    |--------------------------------------------------------------------------
    |
    | By default, redirects stay on the host the request came in on and only
    | use the locale path prefixes from the site base URLs. Enable this when
    | your locales live on separate domains (e.g. example.de / example.fr) to
    | redirect to the configured site base URLs verbatim, host included.
    |
    */

    'crossHostRedirects' => false,

];
