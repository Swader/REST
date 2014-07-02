<?php
/**
 * Common configuration
 */

$config['db'] = array(
    'driver' => 'sqlite',
    'dbname' => $_ENV['SLIM_MODE'] . '.sqlite',
    'dbpath' => realpath(__DIR__ . '/../db')
);


$config['db']['dsn'] = sprintf(
    '%s:%s/%s',
    $config['db']['driver'],
    $config['db']['dbpath'],
    $config['db']['dbname']
);

$config['app']['mode'] = $_ENV['SLIM_MODE'];

// Cache TTL in seconds
$config['app']['cache.ttl'] = 60;

// Max requests per hour
$config['app']['rate.limit'] = 1000;

$config['app']['log.writer'] = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
    'handlers' => array(
        new \Monolog\Handler\StreamHandler(
            realpath(__DIR__ . '/../logs')
                .'/'.$_ENV['SLIM_MODE'] . '_' .date('Y-m-d').'.log'
        ),
    ),
));
