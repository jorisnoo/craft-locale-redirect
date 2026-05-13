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
