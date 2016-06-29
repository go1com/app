<?php

namespace go1\app\tests;

use DomainException;
use go1\app\App;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;

class ConfigDebugOptionTest extends PHPUnit_Framework_TestCase
{
    public function testConfigDebugOff()
    {
        $app = new App(['debug' => false]);

        $app->get('/give-me-error', function () {
            throw new DomainException('Some error thrown.');
        });

        try {
            $app->handle(Request::create('/give-me-error'));
        }
        catch (DomainException $e) {
            $this->assertTrue(false, 'Exception should not be thrown if debug is off.');
        }
    }

    public function testConfigDebugOn()
    {
        $app = new App(['debug' => true]);

        $app->get('/give-me-error', function () {
            throw new DomainException('Some error thrown.');
        });

        try {
            /** @var JsonResponse $response */
            $response = $app->handle(Request::create('/give-me-error'));
            $json = json_decode($response->getContent());
            $this->assertEquals(500, $response->getStatusCode());
            $this->assertEquals('Some error thrown.', $json->message);
        }
        catch (DomainException $e) {
        }
    }
}
