<?php

namespace go1\app\tests;

use go1\app\AppTest;
use go1\app\domain\TerminateAwareJsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TerminateAwareResponseTest extends AppTest
{
    private $shouldBeTrue = false;

    public function test()
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
}
