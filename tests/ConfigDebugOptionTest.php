<?php

namespace go1\app\tests;

use PHPUnit\Framework\TestCase;

class ConfigDebugOptionTest extends TestCase
{
    private static $phpServer;

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass()/* The :void return type declaration that should be here would cause a BC issue */
    {
        self::$phpServer = proc_open('exec php -S 127.0.0.1:4455 -t tests/fixtures/error-documentroot &> /dev/null', [], $pipes = []);
        if (self::$phpServer !== false) {
            usleep(100 * 1000);
        }
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass()/* The :void return type declaration that should be here would cause a BC issue */
    {
        if (self::$phpServer !== false) {
            proc_terminate(self::$phpServer, 9);
        }
    }

    protected function setUp()
    {
        $this->assertNotFalse(self::$phpServer);
    }

    public function testItReturns500DebugOnAndErrorThrowable()
    {
        $fd = @fopen('http://127.0.0.1:4455/type-error', 'r');
        $this->assertFalse($fd, 'Request was expected to fail');
        $this->assertEquals('HTTP/1.0 500 Internal Server Error', $http_response_header[0]);
    }

    public function testItReturns500DebugOnException()
    {

        $fd = @fopen('http://127.0.0.1:4455/exception', 'r');
        $this->assertFalse($fd, 'Request was expected to fail');
        $this->assertEquals('HTTP/1.0 500 Internal Server Error', $http_response_header[0]);
    }

    public function testItReturns405DebugOnMethodNotAllowed()
    {
        $fd = @fopen('http://127.0.0.1:4455/not-allowed-method', 'r');
        $this->assertFalse($fd, 'Request was expected to fail');
        $this->assertEquals('HTTP/1.0 405 Method Not Allowed', $http_response_header[0]);
    }

}
