<?php

namespace go1\app\tests;

use Doctrine\DBAL\Connection;
use go1\app\App;
use PHPUnit\Framework\TestCase;

class MultipleConnectionsTest extends TestCase
{
    public function testSingle()
    {
        $app = new App(['dbOptions' => ['driver' => 'pdo_sqlite', 'url' => 'sqlite://sqlite::memory:']]);

        $this->assertTrue($app['db'] instanceof Connection);
        $this->assertTrue($app['dbs']['default'] instanceof Connection);
    }

    public function testMultiple()
    {
        $app = new App([
            'dbOptions' => [
                'default' => ['driver' => 'pdo_sqlite', 'url' => 'sqlite://sqlite::memory:'],
                'extra'   => ['driver' => 'pdo_sqlite', 'url' => 'sqlite://sqlite::memory:'],
            ],
        ]);

        $this->assertTrue($app['dbs']['default'] instanceof Connection);
        $this->assertTrue($app['dbs']['extra'] instanceof Connection);
    }
}
