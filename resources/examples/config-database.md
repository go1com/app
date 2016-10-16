## Single connection

```php
<?php

return call_user_function() {
  return [
    'db.options' => [
      'driver'        => 'pdo_mysql',
      'dbname'        => getenv('RDS_DB_NAME'),
      'host'          => getenv('RDS_HOSTNAME'),
      'user'          => getenv('RDS_USERNAME'),
      'password'      => getenv('RDS_PASSWORD'),
      'port'          => getenv('RDS_PORT'),
      'driverOptions' => [1002 => 'SET NAMES utf8'],
    ]
  ];
};
```

## Multiple connections

```php
<?php

return call_user_function() {
  return [
    'dbs.options' => [
      'default' => [
        'driver'        => 'pdo_mysql',
        'dbname'        => getenv('RDS_DB_NAME'),
        'host'          => getenv('RDS_HOSTNAME'),
        'user'          => getenv('RDS_USERNAME'),
        'password'      => getenv('RDS_PASSWORD'),
        'port'          => getenv('RDS_PORT'),
        'driverOptions' => [1002 => 'SET NAMES utf8'],
      ],
      'slave' => [
        'driver'        => 'pdo_mysql',
        'dbname'        => getenv('SLAVE_DB_NAME'),
        'host'          => getenv('SLAVE_HOSTNAME'),
        'user'          => getenv('SLAVE_USERNAME'),
        'password'      => getenv('SLAVE_PASSWORD'),
        'port'          => getenv('SLAVE_PORT'),
        'driverOptions' => [1002 => 'SET NAMES utf8'],
      ]
    ]
  ];
};
```
