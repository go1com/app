<?php

namespace go1\app\providers;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcacheCache;
use go1\jwt_middleware\JwtMiddleware;
use Memcache;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use PHPUnit_Framework_TestCase;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RuntimeException;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;

class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        // Clean routing. Documentation: http://silex.sensiolabs.org/doc/providers/service_controller.html
        $c->register(new ServiceControllerServiceProvider());

        // Auto register doctrine DBAL service provider if the app needs it. Documentation: http://silex.sensiolabs.org/doc/providers/doctrine.html
        $c->offsetExists('dbOptions') && $c->register(new DoctrineServiceProvider(), ['db.options' => $c['dbOptions']]);

        // Custom services
        $c->offsetExists('cacheOptions') && $this->registerCacheServices($c);
        $c->offsetExists('logOptions') && $this->registerLogServices($c);

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

        $c['logger.php_error'] = function () {
            return new ErrorLogHandler();
        };
    }
}
