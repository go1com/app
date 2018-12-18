<?php

namespace go1\app\tests;

use Doctrine\DBAL\DriverManager;
use go1\app\DomainService;
use go1\util\schema\InstallTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class DomainServiceTestCase extends TestCase
{
    use InstallTrait;

    protected $sqlite;

    protected function getApp(): DomainService
    {
        if (!defined('APP_ROOT')) {
            throw new RuntimeException('APP_ROOT is not defined');
        }

        /** @var DomainService $app */
        $app = require __DIR__ . '/../public/index.php';
        $app['dbs'] = $app->extend('dbs', function () { return $this->getDatabases(); });
        $this->appInstall($app);

        return $app;
    }

    protected function appInstall(DomainService $app)
    {
        $this->installGo1Schema($app['dbs']['go1'], $coreOnly = false);
    }

    protected function getDatabases()
    {
        return [
            'default' => $this->sqlite = DriverManager::getConnection(['url' => 'sqlite://sqlite::memory:']),
            'go1'     => $this->sqlite,
        ];
    }
}
