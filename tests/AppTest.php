<?php

namespace go1\app;

use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AppTest extends PHPUnit_Framework_TestCase
{
    /**
     * @return App
     */
    protected function getApp()
    {
        return require __DIR__ . '/../public/index.php';
    }

    public function testAppInit()
    {
        $this->assertTrue($this->getApp() instanceof App, 'App can be initialised without issue.');
    }

    public function testDefaultRoute()
    {
        $app = $this->getApp();
        $app['time'] = time();

        /** @var JsonResponse $res */
        $res = $app->handle(Request::create('/'));

        $this->assertTrue($res instanceof JsonResponse);
        $this->assertJsonStringEqualsJsonString('{"time": "' . $app['time'] . '"}', $res->getContent());
    }
}
