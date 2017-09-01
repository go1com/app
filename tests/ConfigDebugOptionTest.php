<?php

namespace go1\app\tests;

use DomainException;
use go1\app\App;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ConfigDebugOptionTest extends TestCase
{
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
