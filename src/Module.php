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

        if (in_array($currentSite->handle, $config['excludeSites'] ?? [], true)) {
            return;
        }

        $decision = (new RedirectResolver)->resolve(
            rawPath: '/' . ltrim($request->getPathInfo(true), '/'),
            hostInfo: $request->getHostInfo(),
            currentAbsoluteUrl: $request->getAbsoluteUrl(),
            currentSiteBaseUrl: $currentSite->getBaseUrl(),
            primarySiteBaseUrl: Craft::$app->getSites()->getPrimarySite()->getBaseUrl(),
            acceptLanguage: $request->getHeaders()->get('Accept-Language', ''),
            localeUrlMap: $this->getLocaleUrlMap(),
            config: $config,
        );

        if ($decision === null) {
            return;
        }

        $url = $decision->url;
        $queryString = $request->getQueryString();
        if ($queryString !== null && $queryString !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }

        $response = Craft::$app->getResponse();
        if ($decision->varyByLocale) {
            $response->getHeaders()->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->getHeaders()->set('Vary', 'Accept-Language');
        }
        $response->redirect($url, $decision->statusCode)->send();
        Craft::$app->end();
    }

    /**
     * Map each locale to a site base URL. When a locale is served under
     * several hosts (e.g. a per-edition multisite), prefer the site on the
     * current site's host: only that one is a valid redirect target for this
     * request. Locales served solely under other hosts keep their foreign
     * entry so the resolver still recognizes their path prefixes as canonical.
     *
     * @return array<string, string>
     */
    private function getLocaleUrlMap(): array
    {
        $currentHost = self::hostOf(Craft::$app->getSites()->getCurrentSite()->getBaseUrl());

        $map = [];
        $onCurrentHost = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $locale = strtolower(substr($site->language, 0, 2));
            $baseUrl = $site->getBaseUrl();

            if ($baseUrl === null) {
                continue;
            }

            $isCurrentHost = self::hostOf($baseUrl) === $currentHost;

            if (! isset($map[$locale]) || ($isCurrentHost && ! $onCurrentHost[$locale])) {
                $map[$locale] = $baseUrl;
                $onCurrentHost[$locale] = $isCurrentHost;
            }
        }

        return $map;
    }

    /**
     * Extract the lowercased host of a URL, or null when it has none.
     */
    private static function hostOf(?string $url): ?string
    {
        $host = $url === null ? null : parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }

}
