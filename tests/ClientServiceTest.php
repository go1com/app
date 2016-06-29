<?php

namespace go1\app\tests;

use go1\app\App;
use GuzzleHttp\ClientInterface;
use PHPUnit_Framework_TestCase;

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
}
