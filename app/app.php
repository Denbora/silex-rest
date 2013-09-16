<?php

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use MJ\Doctrine\Service\ExtractorService;
use MJ\Doctrine\Service\HydratorService;
use MJ\Doctrine\Service\RepositoryService;
use MJ\Doctrine\Service\ResolverService;
use MJ\Doctrine\Service\PrepareService;

$app = new Application();
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider(), array(
    'twig.path'    => array(__DIR__.'/../app/templates'),
    'twig.options' => array('cache' => __DIR__.'/../app/cache/twig'),
));
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    // add custom globals, filters, tags, ...

    return $twig;
}));

$app->register(new DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_mysql',
        'charset'  => 'UTF8',
        'master' => array('user' => 'root', 'password' => 'root', 'host' => 'localhost', 'dbname' => 'silexrest'),
        'slaves' => array(
            array('user' => 'root', 'password' => 'root', 'host' => 'localhost', 'dbname' => 'silexrest'),
        ),
        'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection'
    )
));

$app->register(new DoctrineOrmServiceProvider, array(
    "orm.proxies_dir" => __DIR__."/../app/cache/Doctrine/Proxies",
    "orm.em.options" => array(
        "mappings" => array(
            array(
                "type" => "annotation",
                "namespace" => "MJ\\Doctrine\\Entity",
                "path" => __DIR__."/../src/MJ/Doctrine/Entity",
                "use_simple_annotation_reader" => false
            )
        ),
    ),
    "orm.default_cache" => "array"
));

$app['hydrator'] = $app->share(function($app) {
    return new DoctrineHydrator($app['orm.em']);
});

$app['doctrine.extractor'] = $app->share(function($app) {
    return new ExtractorService($app['hydrator'], $app['orm.em']);
});

$app['doctrine.hydrator'] = $app->share(function($app) {
    return new HydratorService($app['hydrator'], $app['orm.em']);
});

$app['doctrine.repository'] = $app->share(function($app) {
    return new RepositoryService($app['orm.em']);
});

$app['doctrine.resolver'] = $app->share(function() {
    return new ResolverService();
});

$app['doctrine.prepare'] = $app->share(function($app) {
    return new PrepareService($app['hydrator'], $app['orm.em']);
});

Doctrine\Common\Annotations\AnnotationRegistry::registerLoader(array($loader, 'loadClass'));

return $app;
