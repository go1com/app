<?php

namespace go1\app\tests;

use go1\app\App;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;

class MiddlewareJsonRequestTest extends PHPUnit_Framework_TestCase
{
    public function testJsonBodyToRequestObject()
    {
        $app = new App();
        $called = false;
        $request = Request::create('/call-me', 'POST', [], [], [], [], '{"foo": "bar"}');

        $app->post('/call-me', function (Request $request) use (&$called) {
            $called = true;

            $this->assertEmpty('bar', $request->request->get('foo'));
        });

        $app->handle($request);
        $this->assertTrue($called, 'Callback is executed.');
    }
}
