<?php

namespace Noo\CraftLocaleRedirect;

use Craft;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@Noo/CraftLocaleRedirect', __DIR__);

        parent::init();

        Craft::$app->onInit(function () {
            $this->handleRedirect();
        });
    }

    private function handleRedirect(): void
    {
        $request = Craft::$app->getRequest();

        if (
            $request->getIsConsoleRequest()
            || ! $request->getIsSiteRequest()
            || $request->getIsActionRequest()
            || $request->getIsPreview()
            || $request->getIsLivePreview()
            || $request->getMethod() !== 'GET'
        ) {
            return;
        }

        $config = Craft::$app->config->getConfigFromFile('locale-redirect');
        $currentSite = Craft::$app->getSites()->getCurrentSite();

        // Check if this site should trigger redirects
        $excludeSites = $config['excludeSites'] ?? [];
        if (in_array($currentSite->handle, $excludeSites, true)) {
            return;
        }

        $localeUrlMap = $this->getLocaleUrlMap();
        $localeUrlMap = LocaleFilter::filter($localeUrlMap, $config);

        $path = '/' . ltrim($request->getPathInfo(), '/');

        // Bail if the request is already on a known locale prefix.
        foreach ($localeUrlMap as $url) {
            $prefix = rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
                return;
            }
        }

        $isLocaleMatch = $path === '/';

        if ($isLocaleMatch) {
            $matcher = new BrowserLocaleMatcher;
            $matchedLocale = $matcher->match(
                array_keys($localeUrlMap),
                $request->getHeaders()->get('Accept-Language', ''),
            );

            $fallbackUrl = $config['fallback'] ?? Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
            $redirectUrl = $matchedLocale !== null ? $localeUrlMap[$matchedLocale] : $fallbackUrl;
        } else {
            $redirectUrl = rtrim($currentSite->getBaseUrl(), '/') . $path;
        }

        // Prevent redirect loop when the target URL is the current URL
        $currentUrl = $request->getAbsoluteUrl();
        if (rtrim($redirectUrl, '/') === rtrim($currentUrl, '/')) {
            return;
        }

        // Preserve query parameters
        $queryString = $request->getQueryString();
        if ($queryString !== null && $queryString !== '') {
            $redirectUrl .= (str_contains($redirectUrl, '?') ? '&' : '?') . $queryString;
        }

        $response = Craft::$app->getResponse();
        if ($isLocaleMatch) {
            $response->getHeaders()->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->getHeaders()->set('Vary', 'Accept-Language');
        }
        $response->redirect($redirectUrl, $isLocaleMatch ? 302 : 301)->send();
        Craft::$app->end();
    }

    /** @return array<string, string> */
    private function getLocaleUrlMap(): array
    {
        $map = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $locale = strtolower(substr($site->language, 0, 2));

            if (! isset($map[$locale])) {
                $map[$locale] = $site->getBaseUrl();
            }
        }

        return $map;
    }

}
