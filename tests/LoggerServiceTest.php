<?php

namespace go1\app\tests;

use go1\app\App;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;

class LoggerServiceTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $app = new App(['logOptions' => ['name' => 'go1.testing']]);
        $logger = $app['logger'];

        $this->assertTrue($logger instanceof LoggerInterface);
    }

    public function testNull()
    {
        $app = new App();
        $this->assertNull($app['logger']);
    }
}
