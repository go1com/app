<?php

namespace go1\app\tests;

use go1\app\DomainService;
use go1\util\UtilCoreServiceProvider;
use Silex\Provider\SwiftmailerServiceProvider;

class CustomizeProviderTest extends AppTest
{
    public function test()
    {
        $this->getApp();
        $values['serviceProviders'] = [
            new UtilCoreServiceProvider,
            [
                new SwiftmailerServiceProvider, [
                'swiftmailer.options' => [
                    'host'     => 'MAIL_HOST',
                    'port'     => 'MAIL_PORT',
                    'username' => 'MAIL_USERNAME',
                    'password' => 'MAIL_PASSWORD',
                ],
            ],
            ],
        ];

        $app = new DomainService($values);
        $options = $app['swiftmailer.options'];
        $this->assertEquals('MAIL_HOST', $options['host']);
        $this->assertEquals('MAIL_PORT', $options['port']);
        $this->assertEquals('MAIL_USERNAME', $options['username']);
        $this->assertEquals('MAIL_PASSWORD', $options['password']);
    }
}
