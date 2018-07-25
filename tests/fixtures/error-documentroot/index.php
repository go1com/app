<?php

use go1\app\App;

return call_user_func(function () {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $app = new App(require '../error-routes.config.php');

    return ('cli' === php_sapi_name()) ? $app : $app->run();
});
