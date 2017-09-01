<?php

namespace go1\app\tests;

use Doctrine\Common\Cache\CacheProvider;
use go1\app\App;
use go1\app\tests\mocks\CustomCacheBackend;
use PHPUnit\Framework\TestCase;

class CacheServiceTest extends TestCase
{
    public function test()
    {
        $app = new App(['cacheOptions' => ['backend' => 'array']]);

        $this->assertTrue($app['cache'] instanceof CacheProvider);
    }

    public function testNull()
    {
        $app = new App;
        $this->assertFalse($app->offsetExists('cache'));
    }

    public function testCustomCacheBackend()
    {
        $app = new App([
            'cacheOptions' => ['backend' => 'custom'],
            'cache.custom' => function () {
                return new CustomCacheBackend;
            },
        ]);

        $this->assertTrue($app['cache'] instanceof CacheProvider);
    }
}
