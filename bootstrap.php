<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';

use Slim\Slim;
use API\Application;
use API\Middleware\TokenOverBasicAuth;
use Flynsarmy\SlimMonolog\Log\MonologWriter;

// Init application mode
if (empty($_ENV['SLIM_MODE'])) {
    $_ENV['SLIM_MODE'] = (getenv('SLIM_MODE'))
        ? getenv('SLIM_MODE') : 'development';
}


// Init and load configuration
$config = array();

$configFile = dirname(__FILE__) . '/share/config/'
    . $_ENV['SLIM_MODE'] . '.php';

if (is_readable($configFile)) {
    require_once $configFile;
} else {
    require_once dirname(__FILE__) . '/share/config/default.php';
}


// Create Application
$app = new Application($config['app']);

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
    $app->config(array(
        'log.enable' => true,
        'log.level' => \Slim\Log::WARN,
        'debug' => false
    ));
});

// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
    $app->config(array(
        'log.enable' => true,
        'log.level' => \Slim\Log::DEBUG,
        'debug' => false
    ));
});

// Get log writer
$log = $app->getLog();

// Init database
try {
    
    if (!empty($config['db'])) {
        \ORM::configure($config['db']['dsn']);
        if (!empty($config['db']['username'])
            && !empty($config['db']['password'])) {
            \ORM::configure('username', $config['db']['username']);
            \ORM::configure('password', $config['db']['password']);
        }
    }

} catch (\PDOException $e) {
    $log->error($e->getMessage());
}


// Cache Middleware (inner)
$app->add(new API\Middleware\Cache('/api/v1'));

// Parses JSON body
$app->add(new \Slim\Middleware\ContentTypes());

// Manage Rate Limit
$app->add(new API\Middleware\RateLimit('/api/v1'));

// JSON Middleware
$app->add(new API\Middleware\JSON('/api/v1'));

// Auth Middleware (outer)
$app->add(new API\Middleware\TokenOverBasicAuth(array('root' => '/api/v1')));
