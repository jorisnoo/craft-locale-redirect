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
            || $request->getFullUri() !== ''
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

        if ($this->isBot($request->getHeaders()->get('User-Agent', ''))) {
            return;
        }

        $localeUrlMap = $this->getLocaleUrlMap();
        $localeUrlMap = $this->filterLocales($localeUrlMap, $config);

        $matcher = new BrowserLocaleMatcher;
        $matchedLocale = $matcher->match(
            array_keys($localeUrlMap),
            $request->getHeaders()->get('Accept-Language', ''),
        );

        $fallbackUrl = $config['fallback'] ?? Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
        $redirectUrl = $matchedLocale !== null ? $localeUrlMap[$matchedLocale] : $fallbackUrl;

        Craft::$app->getResponse()->redirect($redirectUrl, 302)->send();
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

    /**
     * @param  array<string, string>  $localeUrlMap
     * @return array<string, string>
     */
    private function filterLocales(array $localeUrlMap, array $config): array
    {
        $only = $config['only'] ?? [];

        if (! empty($only)) {
            return array_intersect_key($localeUrlMap, array_flip($only));
        }

        $exclude = $config['exclude'] ?? [];

        if (! empty($exclude)) {
            return array_diff_key($localeUrlMap, array_flip($exclude));
        }

        return $localeUrlMap;
    }

    private function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }

        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
            'facebookexternalhit', 'embedly', 'quora link preview',
            'outbrain', 'pinterest', 'semrush', 'ahrefs',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
