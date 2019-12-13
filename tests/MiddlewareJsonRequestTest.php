<?php

namespace go1\app\tests;

use go1\app\App;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MiddlewareJsonRequestTest extends TestCase
{
    public function testJsonBodyToRequestObject()
    {
        $app = new App();
        $app->post('/call-me', function (Request $req) use (&$called) {
            return new JsonResponse($req->request->all());
        });

        $req = Request::create('/call-me', 'POST', [], [], [], [], $body = '{"foo":"bar"}');
        $req->headers->set('Content-Type', 'application/json');
        $res = $app->handle($req);
        $this->assertEquals($body, $res->getContent());
    }

    public function testItReturnsJsonParsingError()
    {
        $app = new App();
        $app->post('/call-me', function (Request $req) use (&$called) {
            return new JsonResponse($req->request->all());
        });

        $req = Request::create('/call-me', 'POST', [], [], [], [], '{"foo":"bar"');
        $req->headers->set('Content-Type', 'application/json');
        $res = $app->handle($req);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $res->getStatusCode());
        $this->assertStringContainsString('Invalid JSON payload', $res->getContent());
    }

    public function testItHandlesEmptyBodyGracefully()
    {
        $app = new App();
        $app->post('/call-me', function (Request $req) use (&$called) {
            return new JsonResponse($req->request->all());
        });

        $req = Request::create('/call-me', 'POST', [], [], [], [], '');
        $req->headers->set('Content-Type', 'application/json');
        $res = $app->handle($req);
        $this->assertEquals(Response::HTTP_OK, $res->getStatusCode());
    }
}
