<?php

namespace App;

use App\Middleware\NotFound;
use App\Middleware\PhpError;
use App\Middleware\SSOAuthMiddleWare;
use Dotenv\Dotenv;
use I18N\Lang;
use Slim\App;
use Slim\Container;
use Slim\Views\PhpRenderer;

$dotEnv = new Dotenv(PROJECT_ROOT);
$dotEnv->load();

// Language util init
Lang::init($_ENV['ACCEPT_LANGUAGES'], $_ENV['DEFAULT_LANGUAGE'], LANG_ROOT);
Lang::setLang(Lang::getHTTPAcceptLangs());

/** @var App $app */
$container = $app->getContainer();

// view
$container['view'] = new PhpRenderer(TEMP_ROOT . '/');

//notFoundHandler or notAllowedHandler
$container['notFoundHandler'] = $container['notAllowedHandler'] = function (Container $c) {
    return new NotFound();
};

// errorHandler or phpErrorHandler
$container['errorHandler'] = $container['phpErrorHandler'] = function (Container $c) {
    return new PhpError();
};

$app->add(new SSOAuthMiddleWare($container));

