<?php

namespace go1\app\tests;

use go1\app\domain\profiler\RequestDataCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestDataCollectorTest extends TestCase
{
    public function testSensorSensitiveData()
    {
        $sensorKeys = [
            'password',
            'private-key',
            'private_key',
            'secret',
            'app_debug',
            'cache_backend',
            'cache_host',
            'cache_port',
            'content_length',
            'content_type',
            'dev_db_host',
            'dev_db_password',
            'dev_db_username',
            'document_root',
            'document_uri',
            'env',
            'fcgi_role',
            'gateway_interface',
            'go1_db_host',
            'go1_db_name',
            'go1_db_password',
            'go1_db_port',
            'go1_db_slave',
            'go1_db_username',
            'home',
            'hostname',
            'http_authorization',
        ];

        $c = new RequestDataCollector();
        $req = Request::create('/');
        $req->headers->set('jwt-private-key', 'test');
        $req->server->set('GO1_DB_PASSWORD', 'db password');
        $req->server->set('HTTP_JWT_PRIVATE_KEY', 'private key');

        foreach ($sensorKeys as $sensorKey) {
            $req->server->set(strtoupper($sensorKey), 'test');
        }

        $res = Response::create();
        $c->collect($req, $res);
        $c->lateCollect();

        $this->assertFalse($c->getRequestHeaders()->has('jwt-private-key'));
        $this->assertFalse($c->getRequestServer()->has('GO1_DB_PASSWORD'));
        $this->assertFalse($c->getRequestServer()->has('HTTP_JWT_PRIVATE_KEY'));

        foreach ($sensorKeys as $sensorKey) {
            $this->assertFalse($c->getRequestServer()->has($sensorKey));
        }
    }
}
