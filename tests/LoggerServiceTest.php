<?php

namespace go1\app\tests;

use go1\app\App;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LoggerServiceTest extends PHPUnit_Framework_TestCase
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
        $this->assertNull($app['logger']);
    }
}
