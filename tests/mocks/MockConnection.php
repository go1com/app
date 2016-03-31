<?php

namespace go1\app\tests\mocks;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class MockConnection extends Connection
{
    public static $logs;

    public static $pdoConnectionClass = Driver\PDOConnection::class;

    public function __construct(array $params = [], Driver $driver = null)
    {
        static::$logs = [];

        $stub = new MockTestCase();

        if (null === $driver) {
            $driver = $stub->getMockBuilder(AbstractMySQLDriver::class)->disableOriginalConstructor()->getMock();
            $driver
                ->expects($stub->any())
                ->method('getDatabasePlatform')
                ->willReturn((new MockTestCase())->getMockBuilder(SqlitePlatform::class)->getMock());
        }

        if (!isset($params['pdo'])) {
            $pdo = $stub
                ->getMockBuilder(static::$pdoConnectionClass)
                ->setMethods(['prepare', 'query'])
                ->disableOriginalConstructor()
                ->getMock();

            $pdo
                ->expects($stub->any())
                ->method('prepare')
                ->willReturnCallback($callback = function ($arguments) use ($stub, &$state) {
                    static::$logs['pdo'][] = $arguments;

                    $state = $stub
                        ->getMockBuilder(Driver\PDOStatement::class)
                        ->setMethods(['execute', 'bindValue'])
                        ->disableOriginalConstructor()
                        ->getMock();

                    $state->expects($stub->any())->method('execute')->willReturnCallback(function ($arguments) {
                        static::$logs['state'][] = $arguments;
                    });

                    $state->expects($stub->any())->method('bindValue')->willReturnCallback(function ($bindIndex, $value, $bindingType) {
                        static::$logs['state']['values'][] = [$bindIndex, $value, $bindingType];
                    });

                    return $state;
                });

            $pdo
                ->expects($stub->any())
                ->method('query')
                ->willReturnCallback($callback);

            $params['pdo'] = $pdo;
        }

        parent::__construct($params, $driver);
    }

    /**
     * @param string $type
     * @param int    $key
     * @return mixed
     */
    public function getLog($type, $key = 0)
    {
        return static::$logs[$type][$key];
    }
}
