<?php
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}


date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$app = new \Slim\App(['settings' => ['displayErrorDetails' => true]]);

// Set up dependencies
require APP_ROOT.'/dependencies.php';

// Register routes
\App\Routers\RouterFactory::loadRouter($_SERVER['REQUEST_URI'], $app);
header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With');
// Run app
$app->run();
