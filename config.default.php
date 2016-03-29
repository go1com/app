<?php

return call_user_func(function () {
    date_default_timezone_set('UTC');

    if ($debug = getenv('APP_DEBUG') ?: true) {
        error_reporting(E_ALL);
        ini_set('display_errors', true);
    }

    return [
        'debug' => $debug,
    ];
});
