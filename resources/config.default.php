<?php

use go1\app\App;

return call_user_func(function () {
    date_default_timezone_set('UTC');

    if (!defined('SERVICE_NAME')) {
        define('SERVICE_NAME', 'example');
    }

    if ($debug = getenv('APP_DEBUG') ?: true) {
        error_reporting(E_ALL);
        ini_set('display_errors', true);
    }

    return [
        'debug'  => $debug,
        'routes' => [
            ['GET', '/', function (App $c) {
                return $c->json(['time' => isset($c['time']) ? $c['time'] : time()]);
            }],
        ],
    ];
});
