App install
====

## Register the controller

```php
$app['me.app.ctrl.install'] = function(Container $c) {
  return new me\app\controller\InstallController($c['dbs']['default']);
};

$app->get('/install', 'me.app.ctrl.install');
```

## Define the controller

```php
<?php

namespace me\app\controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\HttpFoundation\JsonResponse;

class InstallController
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function get()
    {
        $schema = $this->db->getSchemaManager()->createSchema();
        if ($schema->hasTable('me_app')) {
            return new JsonResponse(['message' => 'Already installed.'], 400);
        }

        !$schema->hasTable('me_app') && $this->createMeAppTable($schema);

        foreach ($schema->toSql($this->db->getDatabasePlatform()) as $sql) {
            try {
                $this->db->executeQuery($sql);
            }
            catch (TableExistsException $e) {
            }
        }

        return new JsonResponse([], 200);
    }
    
    private function createMeAppTable($schema)
    {
        $line = $schema->createTable('rules_pipeline');
        $line->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $line->addColumn('event', 'string');
        $line->addColumn('description', 'string');
        $line->addColumn('status', 'boolean');
        $line->addColumn('weight', 'integer');
        $line->addColumn('created', 'integer', ['unsigned' => true]);
        $line->addColumn('updated', 'integer', ['unsigned' => true]);
        $line->addColumn('profile_id', 'integer', ['unsigned' => true]);
        $line->addColumn('queue', 'boolean', ['default' => false]);
        $line->setPrimaryKey(['id']);
        $line->addIndex(['event']);
        $line->addIndex(['weight']);
        $line->addIndex(['status']);
        $line->addIndex(['created']);
        $line->addIndex(['updated']);
        $line->addIndex(['profile_id']);
    }
}
```
