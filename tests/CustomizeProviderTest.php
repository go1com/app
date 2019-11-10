<?php

namespace go1\app\tests;

use go1\app\DomainService;
use go1\util\UtilCoreServiceProvider;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Provider\SwiftmailerServiceProvider;

class CustomizeProviderTest extends AppTest
{
    public function test()
    {
        $this->getApp();

        $dummyProvider1 = new class() implements ServiceProviderInterface {
            public function register(Container $pimple)
            {
                $pimple['foo'] = 'bar';
            }
        };

        $dummyProvider2 = new class() implements ServiceProviderInterface {
            public function register(Container $pimple)
            {
                $pimple['fizz'] = 'buzz';
            }
        };

        $values['serviceProviders'] = [
            $dummyProvider1,
            [
                $dummyProvider2, [
                'dummy.options' => [
                    'chris' => 'cross'
                ],
            ],
            ],
        ];

        $app = new DomainService($values);
        $this->assertEquals('bar', $app['foo']);
        $this->assertEquals('buzz', $app['fizz']);
        $this->assertNotEmpty($app['dummy.options'] ?? null);
        $this->assertEquals('cross', $app['dummy.options']['chris']);
    }
}
