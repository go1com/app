<?php

namespace go1\app\tests;

use Doctrine\Common\Cache\CacheProvider;
use go1\app\App;
use PHPUnit_Framework_TestCase;

class CacheServiceTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $app = new App(['cacheOptions' => ['backend' => 'array']]);
        $cache = $app['cache'];

        $this->assertTrue($cache instanceof CacheProvider);
    }

    public function testNull()
    {
        $app = new App();
        $this->assertFalse($app->offsetExists('cache'));
    }
}
