<?php

namespace go1\app;

use Doctrine\DBAL\Connection;
use go1\app\tests\mocks\MockConnection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function testInsertQuery()
    {
        $insert = function (Connection $connection) {
            $connection->insert('some_table', ['key_1' => 'value_1']);
        };

        $insert($connection = new MockConnection());

        // Check executed queries
        $this->assertStringContainsString('INSERT INTO some_table (key_1) VALUES (?)', $connection->getLog('pdo'));
        $this->assertEquals(['value_1'], $connection->getLog('state'));
    }

    public function testUpdateQuery()
    {
        $update = function (Connection $connection) {
            $connection->update(
                'some_table',
                ['key_1' => 'value_2'],
                ['id' => 555]
            );
        };

        $update($connection = new MockConnection());

        // Check executed queries
        $this->assertStringContainsString('UPDATE some_table SET key_1 = ? WHERE id = ?', $connection->getLog('pdo'));
        $this->assertEquals(['value_2', 555], $connection->getLog('state'));
    }

    public function testQuery()
    {
        $query = function (Connection $connection) {
            $connection->executeQuery('SELECT 1 WHERE 1 = ?', [1]);
        };

        $query($connection = new MockConnection());

        // Check executed queries
        $this->assertStringContainsString('SELECT 1', $connection->getLog('pdo'));
        $this->assertEquals([1], $connection->getLog('state'));
    }

    public function testQueryBuilder()
    {
        $builder = function (Connection $connection) {
            $q = $connection->createQueryBuilder();
            $q
                ->select('u.*')
                ->from('users', 'u')
                ->where('u.uid = :uid')
                ->setParameter(':uid', 999)
                ->execute();
        };

        $builder($connection = new MockConnection());

        // Check executed queries
        $this->assertStringContainsString('SELECT u.* FROM users u WHERE u.uid = ?', $connection->getLog('pdo'));
        $this->assertEquals([1, 999, 2], $connection->getLog('state', 'values')[0]);
    }
}
