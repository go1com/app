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

    public function testConfigPathDuplicateApp()
    {
        $_ENV['_DOCKER_BAR'] = 'bar';
        $_ENV['BAR'] = 'bar1';
        $app = new App(__DIR__ . '/fixtures/demo-app.config.php');
        $this->assertEquals('bar1', $app['bar']);
    }

    public function testRedis()
    {
        {
            $app = new App([
                'cacheOptions' => [
                    'backend'     => 'predis',
                    'dsn'         => 'tcp://master:6379',
                    'prefix'      => 'local:SERVICE_NAME',
                    'replication' => [
                        'dsn' => 'tcp://replication:6379',
                    ]
                ]
            ]);

            [$hosts, $options] = $app['cache.predis.options'];

            $this->assertEquals(['tcp://master:6379?alias=master', 'tcp://replication:6379'], $hosts);
            $this->assertEquals(['replication' => true, 'prefix' => 'local:SERVICE_NAME'], $options);
        }

        {
            $app = new App([
                'cacheOptions' => [
                    'backend'     => 'predis',
                    'dsn'         => 'tcp://master:6379?ssl[cafile]=private.pem&ssl[verify_peer]=1',
                    'prefix'      => 'local:SERVICE_NAME',
                    'replication' => [
                        'dsn' => 'tcp://replication:6379',
                    ]
                ]
            ]);

            [$hosts, $options] = $app['cache.predis.options'];

            $this->assertEquals(['tcp://master:6379?ssl[cafile]=private.pem&ssl[verify_peer]=1&alias=master', 'tcp://replication:6379'], $hosts);
            $this->assertEquals(['replication' => true, 'prefix' => 'local:SERVICE_NAME'], $options);
        }

    }
}
