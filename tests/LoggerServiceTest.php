<?php

namespace go1\app\tests;

use Doctrine\DBAL\DBALException;
use go1\app\App;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

class LoggerServiceTest extends TestCase
{
    public function test()
    {
        $app = new App(['logOptions' => ['name' => 'go1.testing']]);
        $logger = $app['logger'];

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * @dataProvider logPHPProvider
     */
    public function testLogPHP($options, $expectedLevel)
    {
        $app = new App(['logOptions' => $options]);
        $phpErrorLog = $app['logger.php_error'];

        $this->assertInstanceOf(ErrorLogHandler::class, $phpErrorLog);
        $this->assertEquals($expectedLevel, $phpErrorLog->getLevel());
    }

    public function logPHPProvider()
    {
        return [
            [['level' => LogLevel::INFO], Logger::INFO],
            [[], Logger::ERROR],
        ];
    }

    public function testNull()
    {
        $app = new App();
        $this->assertTrue($app['logger'] instanceof NullLogger);
    }

    public function testDBALExceptionLog()
    {
        $app = new App([
            'routes' => [
                ['GET', '/', function () {
                    throw new DBALException('foo message');
                }],
            ],
        ]);

        $app['debug'] = false;
        $app['logger'] = function () {
            $logger = $this
                ->getMockBuilder(Logger::class)
                ->disableOriginalConstructor()
                ->setMethods(['error', 'pushHandler', 'addRecord'])
                ->getMock();

            $logger
                ->expects($this->once())
                ->method('error')
                ->with('foo message');

            return $logger;
        };

        $app->handle(Request::create('/'));
    }

    public function testErrorIsLogged()
    {
        $app = new App(['logOptions' => ['name' => 'qa']]);

        $app->extend('logger', function () use (&$logger) {
            return $logger = new class extends NullLogger
            {
                public $log = [];

                public function log($level, $message, array $context = [])
                {
                    $this->log[$level][] = $message;
                }

            };
        });

        $app->get('/testErrorIsLogged', function () {
            throw new RuntimeException('Something went wrong.');
        });

        $app->handle(Request::create('/testErrorIsLogged'));
        $this->assertStringContainsString('Something went wrong.', $logger->log['error'][0]);
    }
}
