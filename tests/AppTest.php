<?php

namespace go1\app\tests;

use go1\app\App;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AppTest extends TestCase
{
    protected function getApp(): App
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
        $res = $app->handle(Request::create('/'));
        $time = json_decode($res->getContent())->time;

        $this->assertTrue($res instanceof JsonResponse);
        $this->assertTrue($time >= $app['time']);
    }

    public function testConfigPathApp()
    {
        $_ENV['_DOCKER_FOO'] = 'bar';
        $app = new App(__DIR__ . '/fixtures/demo-app.config.php');
        $this->assertEquals('bar', $app['foo']);
    }
}
