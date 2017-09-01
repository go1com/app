<?php

namespace go1\app;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AppTest extends TestCase
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
        $res = $app->handle(Request::create('/'));
        $time = json_decode($res->getContent())->time;

        $this->assertTrue($res instanceof JsonResponse);
        $this->assertTrue($time >= $app['time']);
    }
}
