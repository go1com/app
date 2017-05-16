<?php

namespace go1\app\tests;

use go1\app\App;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit_Framework_TestCase;
use Psr\Http\Message\RequestInterface;


class ClientServiceTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $app = new App(['clientOptions' => ['name' => 'go1.testing']]);
        $client = $app['client'];

        $this->assertTrue($client instanceof ClientInterface);
    }

    public function testNull()
    {
        $app = new App();
        $this->assertFalse($app->offsetExists('client'));
    }

    public function testRequestHandler()
    {
        $app = new App(['clientOptions' => []]);
        $_SERVER['HTTP_X_REQUEST_ID'] = 'Foo-Bar';

        // @see https://github.com/guzzle/guzzle/blob/master/tests/MiddlewareTest.php#L144
        $h = new MockHandler([
            function (RequestInterface $request, array $options) {
                $this->assertEquals('Foo-Bar', $request->getHeaderLine('X-Request-ID'));
                return new Response(200);
            }
        ]);

        $stack = new HandlerStack($h);
        $stack->push($app['client.handler.map-request']);

        $comp = $stack->resolve();
        $p = $comp(new Request('PUT', 'http://www.google.com'), []);
        $this->assertInstanceOf(PromiseInterface::class, $p);
    }
}
