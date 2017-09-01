<?php

namespace go1\app\providers;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\Common\Cache\RedisCache;
use go1\app\App;
use go1\app\domain\profiler\DatabaseProfilerStorage;
use go1\app\domain\profiler\GuzzleDataCollector;
use go1\app\domain\profiler\GuzzleHistory;
use go1\jwt_middleware\JwtMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Memcache;
use Memcached;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LogLevel;
use Redis;
use RuntimeException;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Stopwatch\Stopwatch;

class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        // Clean routing. Documentation: http://silex.sensiolabs.org/doc/providers/service_controller.html
        $c->register(new ServiceControllerServiceProvider);

        // Auto register doctrine DBAL service provider if the app needs it. Documentation: http://silex.sensiolabs.org/doc/providers/doctrine.html
        $c->offsetExists('db.options') && $c->register(new DoctrineServiceProvider, ['db.options' => $c['db.options']]);
        $c->offsetExists('dbs.options') && $c->register(new DoctrineServiceProvider, ['dbs.options' => $c['dbs.options']]);

        // Custom services
        $c->offsetExists('cacheOptions') && $this->registerCacheServices($c);
        $c->offsetExists('logOptions') && $this->registerLogServices($c);
        $c->offsetExists('clientOptions') && $this->registerClientService($c);

        $c['middleware.jwt'] = function () {
            return new JwtMiddleware;
        };

        $c['middleware.core'] = function () {
            return new CoreMiddlewareProvider;
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
                case 'redis':
                    return class_exists(TestCase::class, false) ? $c["cache.array"] : $c["cache.{$backend}"];

                default:
                    if ($c->offsetExists("cache.{$backend}")) {
                        return $c["cache.{$backend}"];
                    }

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

            $cache = new MemcacheCache;
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

        $c['cache.redis'] = function (Container $c) {
            if (!class_exists('Redis')) {
                throw new RuntimeException('Missing caching driver.');
            }

            $name = isset($c['cacheOptions']['name']) ? $c['cacheOptions']['name'] : null;
            $host = $c['cacheOptions']['host'];
            $port = $c['cacheOptions']['port'];
            $redis = new Redis($name);
            $redis->connect($host, $port);

            $cache = new RedisCache();
            $cache->setRedis($redis);

            return $cache;
        };
    }

    private function registerLogServices(Container $c)
    {
        $c['logger'] = function (Container $c) {
            $logger = new Logger(isset($c['logOptions']['name']) ? $c['logOptions']['name'] : 'go1');

            return $logger->pushHandler($c['logger.php_error']);
        };

        $c['logger.php_error'] = function (Container $c) {
            $logLevel = isset($c['logOptions']['level']) ? $c['logOptions']['level'] : LogLevel::ERROR;

            return new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel);
        };
    }

    private function registerClientService(Container $c)
    {
        $c['client.handler.map-request'] = function () {
            return Middleware::mapRequest(function (RequestInterface $request) {
                if (isset($_SERVER['HTTP_X_REQUEST_ID'])) {
                    return $request->withHeader('X-Request-ID', $_SERVER['HTTP_X_REQUEST_ID']);
                }

                return $request->withHeader('X-Request-ID', 'N/A');
            });
        };

        $c['client.history'] = function () {
            return new GuzzleHistory(new Stopwatch);
        };

        $c['client'] = function (Container $c) {
            $options = $c['clientOptions'];
            $stack = new HandlerStack;
            $stack->setHandler(new CurlHandler);
            $stack->push($c['client.handler.map-request']);
            $options['handler'] = $stack;

            if ($c->offsetExists('profiler.do')) {
                $stack = new HandlerStack;
                $stack->setHandler(new CurlHandler);
                $stack->push($c['client.history']);
            }

            $client = new Client($options);

            return $client;
        };

        if ($c->offsetExists('profiler.do')) {
            $c['profiler.storage'] = function (Container $c) {
                return new DatabaseProfilerStorage($c['dbs']['profiler']);
            };

            $c['profiler.collectors.guzzle'] = function (Container $c) {
                return new GuzzleDataCollector($c['client.history']);
            };

            $c['profiler.collectors'] = function (Container $c) {
                return [
                    'config'    => function () {
                        return new ConfigDataCollector('GO1', App::NAME . App::VERSION);
                    },
                    'request'   => function () {
                        return new RequestDataCollector;
                    },
                    'exception' => function () {
                        return new ExceptionDataCollector;
                    },
                    'events'    => function ($c) {
                        return new EventDataCollector($c['dispatcher']);
                    },
                    'logger'    => function ($c) {
                        return new LoggerDataCollector($c['logger']);
                    },
                    'time'      => function () {
                        return new TimeDataCollector(null, new Stopwatch);
                    },
                    'router'    => function () {
                        return new RouterDataCollector;
                    },
                    'memory'    => function () {
                        return new MemoryDataCollector;
                    },
                    'guzzle'    => function ($c) {
                        return $c['profiler.collectors.guzzle'];
                    },
                ];
            };

            $c['profiler'] = function (Container $c) {
                $profiler = new Profiler($c['profiler.storage'], $c['logger']);

                foreach ($c['profiler.collectors'] as $collector) {
                    $profiler->add($collector($c));
                }

                return $profiler;
            };
        }
    }
}
