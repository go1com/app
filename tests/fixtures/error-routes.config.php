<?php

return [
    'debug'  => true,
    'routes' => [
        ['GET', '/type-error', function () {
            $typedFn = function(int $dummy) {};
            $typedFn(null);
        }],
        ['GET', '/exception', function () {
            throw new \Exception('oopsie');
        }],
        ['POST', '/not-allowed-method', function () {

        }],
    ],
];
