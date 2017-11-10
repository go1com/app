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
        $c = new RequestDataCollector();
        $req = Request::create('/');
        $req->headers->set('jwt-private-key', 'test');
        $req->server->set('GO1_DB_PASSWORD', 'db password');
        $req->server->set('HTTP_JWT_PRIVATE_KEY', 'private key');

        $res = Response::create();
        $c->collect($req, $res);
        $c->lateCollect();

        $this->assertEquals('***', $c->getRequestHeaders()->get('jwt-private-key'));
        $this->assertEquals('***', $c->getRequestServer()->get('GO1_DB_PASSWORD'));
        $this->assertEquals('***', $c->getRequestServer()->get('HTTP_JWT_PRIVATE_KEY'));
    }
}
