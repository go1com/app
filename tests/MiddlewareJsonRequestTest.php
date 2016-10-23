<?php

namespace go1\app\tests;

use go1\app\App;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MiddlewareJsonRequestTest extends PHPUnit_Framework_TestCase
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
}
