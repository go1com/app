<?php

namespace go1\app;

use PHPUnit_Framework_TestCase;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AppTest extends PHPUnit_Framework_TestCase
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
}
