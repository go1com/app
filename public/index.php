<?php

use go1\app\DomainService;

return call_user_func(function () {
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__));
    }

    require_once APP_ROOT . '/vendor/autoload.php';
    $cnf = is_file(APP_ROOT . '/resources/config.php') ? APP_ROOT . '/config.php' : APP_ROOT . '/resources/config.default.php';
    $app = new DomainService($cnf);

    return ('cli' === php_sapi_name()) ? $app : $app->run();
});
