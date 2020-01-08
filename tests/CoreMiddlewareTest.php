<?php
namespace go1\app\tests;

use go1\app\providers\CoreMiddlewareProvider;
use PHPUnit\Framework\TestCase;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CoreMiddlewareTest extends TestCase
{
    public function testHttpException()
    {
        $testSubject = new CoreMiddlewareProvider();

        $appMock = $this->createMock(Application::class);
        $appMock->method('offsetExists')->willReturn(false);

        $appMock
            ->method('error')
            ->withConsecutive([$this->callback(function ($val) {
                    $notFoundException = new NotFoundHttpException('foo');
                    $response = $val($notFoundException);
                    $this->assertInstanceOf(JsonResponse::class, $response);
                    $this->assertEquals($notFoundException->getStatusCode(), $response->getStatusCode());
                    return true;
                })],
                [$this->callback(function ($val) {
                    $e = new \Exception('foo');
                    $response = $val($e);
                    $this->assertInstanceOf(JsonResponse::class, $response);
                    $this->assertEquals(500, $response->getStatusCode());
                    return true;
                })]
            );

        $testSubject->boot($appMock);
    }
}
