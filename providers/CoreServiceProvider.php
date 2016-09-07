<?php

namespace go1\app\providers;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\MemcachedCache;
use go1\jwt_middleware\JwtMiddleware;
use GuzzleHttp\Client;
use Memcache;
use Memcached;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use PHPUnit_Framework_TestCase;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use ReflectionClass;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;

class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        // Clean routing. Documentation: http://silex.sensiolabs.org/doc/providers/service_controller.html
        $c->register(new ServiceControllerServiceProvider());

        // Auto register doctrine DBAL service provider if the app needs it. Documentation: http://silex.sensiolabs.org/doc/providers/doctrine.html
        $c->offsetExists('db.options') && $c->register(new DoctrineServiceProvider(), ['db.options' => $c['db.options']]);
        $c->offsetExists('dbs.options') && $c->register(new DoctrineServiceProvider(), ['dbs.options' => $c['dbs.options']]);

        // Custom services
        $c->offsetExists('cacheOptions') && $this->registerCacheServices($c);
        $c->offsetExists('logOptions') && $this->registerLogServices($c);
        $c->offsetExists('clientOptions') && $this->registerClientService($c);

        $c['middleware.jwt'] = function () {
            return new JwtMiddleware();
        };

        $c['middleware.core'] = function () {
            return new CoreMiddlewareProvider();
        };
    }

    private function registerCacheServices(Container $c)
    {
        $c['cache'] = function (Container $c) {
            $backend = $c['cacheOptions']['backend'];
            switch ($backend) {
                case 'array':
                case 'memcache':
                case 'memcached':
                case 'filesystem':
                    return class_exists(PHPUnit_Framework_TestCase::class, false) ? $c["cache.array"] : $c["cache.{$backend}"];

                default:
                    throw new RuntimeException('Unsupported backend: ' . $c['cacheOptions']['backend']);
            }
        };

        $c['cache.array'] = function () {
            return new ArrayCache();
        };

        $c['cache.filesystem'] = function (Container $c) {
            return new FilesystemCache($c['cacheOptions']['directory']);
        };

        $c['cache.memcache'] = function (Container $c) {
            if (!class_exists('Memcache')) {
                throw new RuntimeException('Missing caching driver.');
            }

            $host = $c['cacheOptions']['host'];
            $port = $c['cacheOptions']['port'];
            $memcache = new Memcache();
            if (!$memcache->connect($host, $port)) {
                throw new RuntimeException('Can not connect to cache backend.');
            }

            $cache = new MemcacheCache();
            $cache->setMemcache($memcache);

            return $cache;
        };

        $c['cache.memcached'] = function (Container $c) {
            if (!class_exists('Memcached')) {
                throw new RuntimeException('Missing caching driver.');
            }

            $name = isset($c['cacheOptions']['name']) ? $c['cacheOptions']['name'] : null;
            $host = $c['cacheOptions']['host'];
            $port = $c['cacheOptions']['port'];
            $memcached = new Memcached($name);
            $memcached->addServer($host, $port);

            $cache = new MemcachedCache();
            $cache->setMemcached($memcached);

            return $cache;
        };
    }

    private function registerLogServices(Container $c)
    {
        $c['logger'] = function (Container $c) {
            $name = $c['logOptions']['name'];
            $debug = !empty($c['debug']);
            $testing = class_exists(PHPUnit_Framework_TestCase::class, false);

            $logger = new Logger($name);
            $logger->pushHandler($c['logger.syslog']);
            $debug && !$testing && $logger->pushHandler($c['logger.php_error']);

            return $logger;
        };

        $c['logger.syslog'] = function (Container $c) {
            return new SyslogHandler($c['logOptions']['name']);
        };

        $c['logger.php_error'] = function (Container $c) {
            $logLevel = isset($c['logOptions']['level']) ? $c['logOptions']['level'] : LogLevel::ERROR;

            return new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel);
        };
    }

    private function registerClientService(Container $c)
    {
        $c['client'] = function (Container $c) {
            return new Client($c['clientOptions']);
        };
    }
}
