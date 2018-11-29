<?php

namespace go1\app\tests;

use go1\app\DomainService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class DomainServiceTestCase extends TestCase
{
    protected function getApp(): DomainService
    {
        if (!defined('APP_ROOT')) {
            throw new RuntimeException('APP_ROOT is not defined');
        }

        return require __DIR__ . '/../public/index.php';
    }
}
