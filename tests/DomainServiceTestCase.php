<?php

namespace go1\app\tests;

use Doctrine\DBAL\DriverManager;
use go1\app\DomainService;
use go1\util\schema\InstallTrait;
use go1\util\schema\UserSchema;
use go1\util\Service;
use go1\util\tests\QueueMockTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class DomainServiceTestCase extends TestCase
{
    use InstallTrait;
    use QueueMockTrait;

    protected $sqlite;
    protected $mockMqClient = true;

    protected function getApp(): DomainService
    {
        if (!defined('APP_ROOT')) {
            throw new RuntimeException('APP_ROOT is not defined');
        }

        /** @var DomainService $app */
        $app = require __DIR__ . '/../public/index.php';

        // mocking
        $app['dbs'] = $app->extend('dbs', function () { return $this->getDatabases(); });

        $this->mockMqClient && $this->mockMqClient($app);
        $this->appInstall($app);

        return $app;
    }

    protected function appInstall(DomainService $app)
    {
        $this->installGo1Schema($app['dbs']['go1'], $coreOnly = false);
        UserSchema::createViews($app['dbs']['go1'], Service::accountsName('qa'));
    }

    protected function getDatabases()
    {
        return [
            'default' => $this->sqlite = DriverManager::getConnection(['url' => 'sqlite://sqlite::memory:']),
            'go1'     => $this->sqlite,
        ];
    }
}
