<?php

require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('msgpack_pack')) {
    function msgpack_pack($data)
    {
        return '[MSG-PACK] ' . json_encode($data);
    }
}
