<?php

namespace Noo\CraftLocaleRedirect;

use Craft;
use craft\web\Application;
use craft\web\Response;
use GuzzleHttp\Psr7\Uri;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@Noo/CraftLocaleRedirect', __DIR__);

        parent::init();

        $app = Craft::$app;
        if ($app instanceof Application) {
            // Craft handles CP resources during init, before this event runs.
            $app->on(Application::EVENT_BEFORE_REQUEST, fn () => $this->handleRedirect($app));
        }
    }

    private function handleRedirect(Application $app): void
    {
        $request = $app->getRequest();

        if (
            !$request->getIsSiteRequest()
            || $request->getIsActionRequest()
            || $request->getIsPreview()
            || $request->getIsLivePreview()
            || $request->getMethod() !== 'GET'
        ) {
            return;
        }

        $config = $app->getConfig()->getConfigFromFile('locale-redirect');
        $sites = $app->getSites();
        $currentSite = $sites->getCurrentSite();

        if (!is_array($config) || in_array($currentSite->handle, $config['excludeSites'] ?? [], true)) {
            return;
        }

        $currentSiteBaseUrl = $currentSite->getBaseUrl();
        $primarySiteBaseUrl = $sites->getPrimarySite()->getBaseUrl();

        // Without base URLs there is nothing to derive locale prefixes from,
        // and resolve() requires them.
        if ($currentSiteBaseUrl === null || $primarySiteBaseUrl === null) {
            return;
        }

        // getPathInfo() has already decoded the path, losing encoded separators
        // and literal plus signs. Keep the client-visible path and mount intact.
        $path = explode('?', $request->getUrl(), 2)[0];
        $base = rtrim($request->getBaseUrl(), '/');
        if (str_starts_with($path . '/', $request->getScriptUrl() . '/')) {
            return;
        }
        $response = $app->getResponse();
        if (rtrim($path, '/') === $base) {
            // Both the negotiated redirect and the root page vary by language.
            $response->getHeaders()->add('Vary', 'Accept-Language');
            $bypass = $config['bypassParam'] ?? 'localeRedirect';
            if ($bypass !== '' && $request->getQueryParam($bypass) === 'off') {
                return;
            }
        }

        $decision = (new RedirectResolver)->resolve(
            rawPath: $path,
            hostInfo: $request->getHostInfo(),
            baseUrl: $base,
            currentSiteBaseUrl: $currentSiteBaseUrl,
            primarySiteBaseUrl: $primarySiteBaseUrl,
            acceptLanguage: $request->getHeaders()->get('Accept-Language', ''),
            localeUrlMap: $this->getLocaleUrlMap($sites->getAllSites()),
            config: $config,
        );

        if ($decision === null) {
            return;
        }

        $url = new Uri($decision->url);
        $query = $request->getQueryStringWithoutPath();
        // Craft removes the first rewrite parameter. Remove any additional
        // copies without decoding or coalescing unrelated query parameters.
        $pathParam = $app->getConfig()->getGeneral()->pathParam;
        if ($query !== '' && $pathParam) {
            $query = implode('&', array_filter(explode('&', $query), static fn (string $pair): bool =>
                urldecode(explode('=', $pair, 2)[0]) !== $pathParam
            ));
        }
        if ($query !== '') {
            $url = $url->withQuery(($url->getQuery() === '' ? '' : $url->getQuery() . '&') . $query);
        }
        // Yii's redirect() converts relative Locations to absolute URLs using
        // the request host. Keep our origin-relative Location intact.
        $response->getHeaders()->set('Cache-Control', 'private, no-store');
        $header = $request->getIsAjax() ? ($request->getIsPjax() ? 'X-Pjax-Url' : 'X-Redirect') : 'Location';
        $response->getHeaders()->set($header, (string) $url);
        $response->format = Response::FORMAT_RAW;
        $response->setStatusCode($decision->statusCode);
        $app->end(0, $response);
    }

    /**
     * Map each locale to the base URLs of all sites using it, in site order.
     * The resolver decides per request which entry is a valid redirect
     * target; collecting every URL keeps this map request-independent and
     * lets the prefix bail check recognize all canonical prefixes.
     *
     * @param \craft\models\Site[] $sites
     * @return array<string, list<string>>
     */
    private function getLocaleUrlMap(array $sites): array
    {
        $map = [];

        foreach ($sites as $site) {
            $locale = LocaleFilter::primaryLanguage($site->getLanguage());
            $baseUrl = $site->getBaseUrl();

            if ($baseUrl !== null) {
                $map[$locale][] = $baseUrl;
            }
        }

        return $map;
    }
}
