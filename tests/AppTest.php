<?php

namespace go1\app;

use DomainException;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AppTest extends \PHPUnit_Framework_TestCase
{
    public function testAppInit()
    {
        $app = require __DIR__ . '/../public/index.php';

        $this->assertTrue($app instanceof App, 'App can be initialised without issue.');

        return $app;
    }

    /**
     * @depends testAppInit
     */
    public function testDefaultRoute(App $app)
    {
        $app['time'] = time();

        /** @var JsonResponse $response */
        $response = $app->handle(Request::create('/'));

        $this->assertTrue($response instanceof JsonResponse);
        $this->assertJsonStringEqualsJsonString('{"time": "' . $app['time'] . '"}', $response->getContent());
    }

    /**
     * @depends testAppInit
     */
    public function testConfigDebugOff(App $app)
    {
        $app['debug'] = false;

        $app->get('/give-me-error', function () {
            throw new DomainException('Some error thrown.');
        });

        try {
            $app->handle(Request::create('/give-me-error'));
        }
        catch (DomainException $e) {
            $this->assertTrue(false, 'Exception should not be thrown if debug is off.');
        }
    }

    /**
     * @depends testAppInit
     */
    public function testConfigDebugOn(App $app)
    {
        $app['debug'] = false;

        $app->get('/give-me-error', function () {
            throw new DomainException('Some error thrown.');
        });

        try {
            /** @var JsonResponse $response */
            $response = $app->handle(Request::create('/give-me-error'));
            $json = json_decode($response->getContent());

            $this->assertEquals(500, $response->getStatusCode());
            $this->assertTrue($json->error);
            $this->assertEquals('FAILED', $json->status);
            $this->assertEquals('Some error thrown.', $json->message);
        }
        catch (DomainException $e) {
        }
    }

    /**
     * @depends testAppInit
     */
    public function testJsonBodyToRequestObject(App $app)
    {
        $called = false;
        $request = Request::create('/call-me', 'POST', [], [], [], [], '{"foo": "bar"}');

        $app->post('/call-me', function (Request $request) use (&$called) {
            $called = true;

            $this->assertEmpty('bar', $request->request->get('foo'));
        });

        $app->handle($request);
        $this->assertTrue($called, 'Callback is executed.');
    }

    public function testEventListener()
    {
        $triggered = false;

        $app = new App([
            'events' => [
                'foo' => function (Event $event) use (&$triggered) {
                    $triggered = ($event instanceof Event);
                },
            ],
        ]);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $app['dispatcher'];
        $dispatcher->dispatch('foo');

        $this->assertTrue($triggered, 'Our listener is triggered.');
    }
}
