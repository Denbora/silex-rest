<?php
use Doctrine\Common\Annotations\AnnotationRegistry;
use Herrera\Wise\WiseServiceProvider;
use Silex\Application;

$loader = require_once __DIR__.'/../vendor/autoload.php';

$app = new Application();
$app['app_path'] = __DIR__.'/..';

$app->register(
    new WiseServiceProvider(),
    array(
        'wise.path' => __DIR__.'/../app/config',
        'cache.path' => __DIR__.'/../app/cache',
        'wise.options' => array(
            'type' => 'yml',
            'config' => array (
                'services' => 'services'
            ),
            'mode' => 'prod',
            'parameters' => $app
        )
    )
);

$app['config'] = $app['wise']->load('config.yml');

WiseServiceProvider::registerServices($app);
AnnotationRegistry::registerLoader(array($loader, 'loadClass'));

//$app['controllers']->requireHttps();

$app->mount('/{namespace}', new MJanssen\Provider\RestControllerProvider());

$app->error(function (\Exception $e, $code) use ($app) {
    $page = 404 == $code ? '404.html' : '500.html';
    return new Response($app['twig']->render($page, array('code' => $code)), $code);
});

$app->run();