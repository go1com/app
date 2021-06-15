<?php

namespace go1\app\providers;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\Common\Cache\PredisCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Logging\LoggerChain;
use go1\app\App;
use go1\app\domain\profiler\DatabaseProfilerStorage;
use go1\app\domain\profiler\DoctrineDataCollector;
use go1\app\domain\profiler\ElasticSearchDataCollector;
use go1\app\domain\profiler\GuzzleDataCollector;
use go1\app\domain\profiler\GuzzleHistory;
use go1\app\domain\profiler\RabbitMqDataCollector;
use go1\jwt_middleware\JwtMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Memcached;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Predis\Client as PredisClient;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Redis;
use RuntimeException;
use Silex\Api\EventListenerProviderInterface;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Stopwatch\Stopwatch;
use function substr;

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
        $this->registerLogServices($c);
        $c->offsetExists('clientOptions') && $this->registerClientService($c);
        $this->registerProfilerServices($c);

        $c['middleware.jwt'] = function () { return new JwtMiddleware; };
        $c['middleware.core'] = function () { return new CoreMiddlewareProvider; };
    }

    private function registerCacheServices(Container $c)
    {
        $c['cache'] = function (Container $c) {
            $backend = $c['cacheOptions']['backend'];
            switch ($backend) {
                case 'array':
                case 'memcached':
                case 'filesystem':
                case 'redis':
                case 'predis':
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

        $c['cache.predis'] = function (Container $c) {
            if (!class_exists(PredisClient::class)) {
                throw new RuntimeException('Missing caching driver.');
            }

            [$hosts, $options] = $c['cache.predis.options'];

            return new PredisCache(new PredisClient($hosts, $options));
        };

        $c['cache.predis.options'] = function (Container $c) {
            $options = [];

            $masterDsn = $c['cacheOptions']['dsn'];
            $hosts = [$masterDsn];
            if (isset($c['cacheOptions']['replication'])) {
                $query = parse_url($masterDsn, PHP_URL_QUERY);
                $masterDsn .= $query ? '&alias=master' : '?alias=master';

                $replicationDsn = $c['cacheOptions']['replication']['dsn'];
                $hosts = [$masterDsn, $replicationDsn];
                $options += ['replication' => true];
            }

            if (isset($c['cacheOptions']['prefix'])) {
                $options += ['prefix' => $c['cacheOptions']['prefix']];
            }

            if (isset($c['cacheOptions']['parameters'])) {
                $options += ['parameters' => $c['cacheOptions']['parameters']];
            }

            return [$hosts, $options];
        };
    }

    private function registerLogServices(Container $c)
    {
        $c['logger'] = function (Container $c) {
            if (!$c->offsetExists('logOptions')) {
                return new NullLogger();
            }

            $logger = new Logger(isset($c['logOptions']['name']) ? $c['logOptions']['name'] : 'go1');

            // @see https://docs.datadoghq.com/tracing/connect_logs_and_traces/php/
            if (function_exists('\DDTrace\trace_id')) {
                $logger->pushProcessor(function ($record) {
                    $record['dd'] = [
                        'trace_id' => \DDTrace\trace_id(),
                        'span_id'  => \dd_trace_peek_span_id(),
                    ];

                    return $record;
                });
            }

            return $logger->pushHandler($c['logger.php_error']);
        };

        $c['logger.php_error'] = function (Container $c) {
            $logLevel = isset($c['logOptions']['level']) ? $c['logOptions']['level'] : LogLevel::ERROR;
            $handler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $logLevel);
            $handler->setFormatter(new JsonFormatter());

            return $handler;
        };
    }

    private function registerProfilerServices(Container $c)
    {
        $c['profiler.storage'] = function (Container $c) {
            return new DatabaseProfilerStorage($c['dbs']['profiler']);
        };

        $c['profiler.collectors.guzzle'] = function (Container $c) {
            return new GuzzleDataCollector($c['client.middleware.profiler']);
        };

        $c['profiler.collectors.db'] = function (Container $c) {
            $connections = array_map(function ($name) use ($c) {
                return $c['dbs'][$name];
            }, $c['dbs']->keys());

            return new DoctrineDataCollector($connections);
        };

        $c['profiler.collectors.es'] = function () {
            return new ElasticSearchDataCollector;
        };

        $c['profiler.collectors.mq'] = function () {
            return new RabbitMqDataCollector;
        };

        $c['stopwatch'] = function () {
            return new Stopwatch();
        };

        $c['profiler.collectors'] = function () {
            return [
                'config'    => function () { return new ConfigDataCollector('GO1', App::NAME . App::VERSION); },
                'request'   => function () { return new RequestDataCollector; },
                'exception' => function () { return new ExceptionDataCollector; },
                'events'    => function ($c) { return new EventDataCollector($c['dispatcher']); },
                'logger'    => function ($c) { return new LoggerDataCollector($c['logger']); },
                'time'      => function ($c) { return new TimeDataCollector(null, $c['stopwatch']); },
                'memory'    => function () { return new MemoryDataCollector; },
                'guzzle'    => function ($c) { return $c['profiler.collectors.guzzle']; },
                'db'        => function ($c) { return $c['profiler.collectors.db']; },
                'es'        => function ($c) { return $c['profiler.collectors.es']; },
                'mq'        => function ($c) { return $c['profiler.collectors.mq']; },
            ];
        };

        $c['profiler'] = function (Container $c) {
            $profiler = new Profiler($c['profiler.storage'], $c['logger']);

            foreach ($c['profiler.collectors'] as $collector) {
                $profiler->add($collector($c));
            }

            return $profiler;
        };

        if ($c->offsetExists('profiler.do') && $c->offsetGet('profiler.do')) {
            /** @var DoctrineDataCollector $collector */
            $collector = $c['profiler.collectors.db'];
            foreach ($c['dbs.options'] as $name => $params) {
                /** @var Connection $db */
                $db = $c['dbs'][$name];
                $loggerChain = new LoggerChain;
                $loggerChain->addLogger($logger = new DebugStack);
                $db->getConfiguration()->setSQLLogger($loggerChain);
                $collector->addLogger($name, $logger);
            }

            $c->extend('dispatcher', function ($dispatcher, $app) {
                return new TraceableEventDispatcher($dispatcher, $app['stopwatch'], $app['logger']);
            });

            $c->register(
                new class implements ServiceProviderInterface, EventListenerProviderInterface {
                    public function subscribe(Container $c, EventDispatcherInterface $dispatcher)
                    {
                        $dispatcher->addSubscriber(new ProfilerListener($c['profiler'], $c['request_stack'], null, false, false));
                        $dispatcher->addSubscriber($c['profiler']->get('request'));
                    }

                    public function register(Container $c)
                    {
                    }
                }
            );
        }
    }

    private function registerClientService(Container $c)
    {

        $c['client.middleware.profiler'] = function () {
            return new GuzzleHistory(new Stopwatch);
        };

        $c['client'] = function (Container $c) {
            /** @var App $c */
            $options = $c['clientOptions'];
            $options['headers']['User-Agent'] = 'GO1 ' . $c::NAME . '/' . $c::VERSION;

            if (!empty($_SERVER['HTTP_USER_AGENT'])) {
                $options['headers']['User-Agent'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 256);
            }

            $stack = HandlerStack::create(new CurlHandler);
            // Add user-defined header, mentioned in a.o. section 5 of RFC 2047. (https://tools.ietf.org/html/rfc2047#section-5)
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 7) == 'HTTP_X_') {
                    $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($name, $value) {
                        // Add header to request, follow by section Fielding of RFC 2616
                        // Example from `$_SERVER['HTTP_X_REQUEST_ID']` we will have the header name `X-Request-Id`
                        // @see: http://php.net/manual/en/function.getallheaders.php#84262
                        return $request->withHeader(str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))), $value);
                    }));
                }
            }
            $options['handler'] = $stack;

            if ($c->offsetExists('profiler.do') && $c->offsetGet('profiler.do')) {
                $stack->push($c['client.middleware.profiler'], 'go1.profiler');
            }

            return new Client($options);
        };
    }
}
