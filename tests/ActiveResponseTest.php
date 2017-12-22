<?php

namespace go1\app\tests;

use go1\app\domain\ActiveResponse;
use go1\app\domain\TerminateAwareJsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveResponseTest extends AppTest
{
    private $shouldBeTrue = false;

    protected function setUp()
    {
        $this->shouldBeTrue = false;
    }

    public function testLegacy()
    {
        $app = $this->getApp();
        $app->post('/qa', function () {
            $res = new TerminateAwareJsonResponse(null, 444);

            $res->terminate(function (Request $req, Response $res) {
                $this->shouldBeTrue = true;
                $this->assertEquals('bar', $req->get('foo'));
                $this->assertEquals(444, $res->getStatusCode());
            });

            return $res;
        });

        $res = $app->handle($req = Request::create('/qa?foo=bar', 'POST'));
        $app->terminate($req, $res);
        $this->assertTrue($this->shouldBeTrue);
    }

    public function testTerminate()
    {
        $app = $this->getApp();
        $app->post('/qa', function () {
            $res = new ActiveResponse(null, 444);

            $res->terminate(function (Request $req, Response $res) {
                $this->shouldBeTrue = true;
                $this->assertEquals('bar', $req->get('foo'));
                $this->assertEquals(444, $res->getStatusCode());
            });

            return $res;
        });

        $res = $app->handle($req = Request::create('/qa?foo=bar', 'POST'));
        $app->terminate($req, $res);
        $this->assertTrue($this->shouldBeTrue);
    }

    public function testJsonFormat()
    {
        $app = $this->getApp();
        $app->post('/qa', function () {
            return new ActiveResponse(['foo' => 'bar'], 444);
        });

        $req = Request::create('/qa?foo=bar', 'POST');
        $req->headers->set('Accept', 'application/json');
        $res = $app->handle($req);

        $this->assertEquals('application/json', $res->headers->get('Content-Type'));
        $this->assertEquals('{"foo":"bar"}', $res->getContent());
    }

    public function testMessagePackResponseFormat()
    {
        $app = $this->getApp();
        $app->post('/qa', function () { return new ActiveResponse(['foo' => 'bar'], 444); });

        $req = Request::create('/qa?foo=bar', 'POST');
        $req->headers->set('Accept', 'application/x-msgpack, application/json');
        $res = $app->handle($req);

        $this->assertEquals('application/x-msgpack', $res->headers->get('Content-Type'));
        $this->assertEquals(msgpack_pack(['foo' => 'bar']), $res->getContent());
    }
}
