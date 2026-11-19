<?php

use craft\config\GeneralConfig;
use craft\models\Site;
use craft\services\Config;
use craft\services\Sites;
use craft\web\Application;
use craft\web\Request;
use craft\web\Response;
use Noo\CraftLocaleRedirect\Module;

beforeAll(function () {
    require_once dirname(__DIR__) . '/vendor/craftcms/cms/lib/yii2/Yii.php';
    require_once dirname(__DIR__) . '/vendor/craftcms/cms/src/Craft.php';
});

beforeEach(function () {
    // Exercise Craft's request/response and Yii's event dispatcher without
    // requiring project config or a database. Only replace the project services,
    // resolved request flags, and the end-of-request process exit.
    $app = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods(['end'])->getMock();
    Craft::$app = $this->app = $app;
    $general = $this->createStub(GeneralConfig::class);
    $general->pathParam = 'route';
    $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->onlyMethods([
        'getIsPreview', 'getIsLivePreview', 'getIsSiteRequest', 'getIsActionRequest', 'getMethod', 'getQueryString',
    ])->getMock();
    $request->generalConfig = $general;
    $request->setBaseUrl('/shop');
    $request->setScriptUrl('/shop/index.php');
    $request->setHostInfo('http://alias.test');
    $request->setUrl('/shop/');
    $request->setQueryParams([]);
    $this->request = $request;
    $app->set('request', $request);
    $app->set('response', $this->response = new Response);
    $this->settings = [];
    $config = $this->createStub(Config::class);
    $config->method('getGeneral')->willReturn($general);
    $config->method('getConfigFromFile')->willReturnCallback(fn () => $this->settings);
    $app->set('config', $config);
    $this->currentSite = new Site(['handle' => 'german', 'language' => 'de-CH', 'baseUrl' => 'https://example.test/shop/de/']);
    $fil = new Site(['handle' => 'filipino', 'language' => 'fil-PH', 'baseUrl' => 'https://example.test/shop/fil/']);
    $sites = $this->createStub(Sites::class);
    $sites->method('getCurrentSite')->willReturn($this->currentSite);
    $sites->method('getPrimarySite')->willReturn($this->currentSite);
    $sites->method('getAllSites')->willReturn([$this->currentSite, $fil]);
    $app->set('sites', $sites);
    new Module('locale-redirect', $app);
});

afterEach(function () {
    Craft::$app = null;
});

it('defers to Craft resource processing during application init', function () {
    $this->request->setUrl('/shop/custom-resources/theme.css');
    $this->request->expects($this->never())->method('getIsSiteRequest');
    $this->app->trigger(Application::EVENT_INIT);
    expect($this->app->hasEventHandlers(Application::EVENT_BEFORE_REQUEST))->toBeTrue();
});

it('emits a private origin-relative redirect preserving path bytes and non-routing query fields', function () {
    $this->request->method('getIsSiteRequest')->willReturn(true);
    $this->request->method('getMethod')->willReturn('GET');
    $this->request->method('getQueryString')->willReturn('route=News&tag=a&route=News&tag=b');
    $this->request->setUrl('/shop/DE/News/a%3Fb/c++/100%25/a%2Fb?tag=a&tag=b');
    $this->app->expects($this->once())->method('end')->with(0, $this->response);
    $this->app->trigger(Application::EVENT_BEFORE_REQUEST);
    expect($this->response->getStatusCode())->toBe(301)
        ->and($this->response->getHeaders()->get('Location'))->toBe('/shop/de/News/a%3Fb/c++/100%25/a%2Fb?tag=a&tag=b')
        ->and($this->response->getHeaders()->get('Cache-Control'))->toBe('private, no-store');
});

it('leaves dist assets to Craft without swallowing similarly named pages', function (string $path, ?string $location) {
    $this->request->method('getIsSiteRequest')->willReturn(true);
    $this->request->method('getMethod')->willReturn('GET');
    $this->request->method('getQueryString')->willReturn('');
    $this->request->setUrl('/shop' . $path);
    $this->app->expects($location === null ? $this->never() : $this->once())->method('end');
    $this->app->trigger(Application::EVENT_BEFORE_REQUEST);
    expect($this->response->getHeaders()->get('Location'))->toBe($location);
})->with([
    ['/dist', null],
    ['/dist/assets/missing.js?v=1', null],
    ['/distribution', '/shop/de/distribution'],
]);

it('negotiates a three-letter language and retains other Vary headers', function () {
    $this->request->method('getIsSiteRequest')->willReturn(true);
    $this->request->method('getMethod')->willReturn('GET');
    $this->request->method('getQueryString')->willReturn('');
    $this->request->getHeaders()->set('Accept-Language', 'fil-PH');
    $this->settings = ['only' => ['fil_PH']];
    $this->response->getHeaders()->set('Vary', 'Origin');
    $this->app->trigger(Application::EVENT_BEFORE_REQUEST);
    expect($this->response->getStatusCode())->toBe(302)
        ->and($this->response->getHeaders()->get('Location'))->toBe('/shop/fil/')
        ->and($this->response->getHeaders()->get('Vary', null, false))->toBe(['Origin', 'Accept-Language']);
});

it('varies an actual root page whether negotiated or explicitly selected', function (bool $bypass) {
    $this->request->method('getIsSiteRequest')->willReturn(true);
    $this->request->method('getMethod')->willReturn('GET');
    $this->currentSite->setBaseUrl('https://example.test/shop/');
    $this->request->getHeaders()->set('Accept-Language', $bypass ? 'fil' : 'de');
    $this->settings = ['bypassParam' => 'choose'];
    $this->request->setQueryParams($bypass ? ['choose' => 'off'] : []);
    $this->app->expects($this->never())->method('end');
    $this->app->trigger(Application::EVENT_BEFORE_REQUEST);
    expect($this->response->getStatusCode())->toBe(200)
        ->and($this->response->getHeaders()->get('Vary'))->toBe('Accept-Language')
        ->and($this->response->getHeaders()->has('Location'))->toBeFalse();
})->with([true, false]);

it('leaves ineligible requests untouched', function (string $skip) {
    $this->request->method('getIsSiteRequest')->willReturn($skip !== 'cp');
    $this->request->method('getIsActionRequest')->willReturn($skip === 'action');
    $this->request->method('getIsPreview')->willReturn($skip === 'preview');
    $this->request->method('getIsLivePreview')->willReturn($skip === 'live');
    $this->request->method('getMethod')->willReturn($skip === 'post' ? 'POST' : 'GET');
    $this->settings = ['excludeSites' => $skip === 'excluded' ? ['german'] : []];
    if ($skip === 'script') {
        $this->request->setUrl('/shop/index.php');
    }
    $this->app->expects($this->never())->method('end');
    $this->app->trigger(Application::EVENT_BEFORE_REQUEST);
    expect($this->response->getHeaders()->has('Location'))->toBeFalse();
})->with(['cp', 'action', 'preview', 'live', 'post', 'excluded', 'script']);
