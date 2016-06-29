GO1 App ![](https://travis-ci.org/go1com/app.svg?branch=master) [![Latest Stable Version](https://poser.pugx.org/go1/app/v/stable.svg)](https://packagist.org/packages/go1/app) [![License](https://poser.pugx.org/go1/app/license)](https://packagist.org/packages/go1/app)
====

Base microservice application.

## Cache service

The `cache` service will become `Doctrine\Common\Cache\ArrayCache` on testing.

```
# Memcache backend
$options = [
    'backend' => 'memcache',
    'host' => '127.0.0.1',
    'port' => '11211'
];

# File system backend
$options = [
    'backend'   => 'filesystem',
    'directory' => '/path/to/cache/',
];

$app = new go1\App(['cacheOptions' => $options]);

// Acces `cache` service, instance of `Doctrine\Common\Cache\CacheProvider`
$cache = $app['cache'];
```

## Logging service

```
$options = ['name' => 'go1'];
$app = new go1\App(['logOptions' => $options]);

// Access `logger` service, instance of `Psr\Log\LoggerInterface`
$logger = $app['logger'];
```
