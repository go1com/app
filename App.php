<?php

namespace go1\app;

use Doctrine\DBAL\DBALException;
use Exception;
use go1\app\domain\ActiveResponse;
use go1\app\domain\TerminateAwareJsonResponse;
use go1\app\providers\CoreServiceProvider;
use Psr\Log\LoggerInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

class App extends Application
{
    const NAME = 'go1';

    private $timerStart;

    public function __construct(array $values = [])
    {
        // Set default timezone.
        date_default_timezone_set(isset($values['timezone']) ? $values['timezone'] : 'UTC');

        // Helper variable to track spent time.
        $this->timerStart = microtime();

        // Remove _DOCKER_  prefix from env variables.
        foreach ($_ENV as $k => &$v) {
            if (0 === strpos($k, '_DOCKER_')) {
                putenv(substr($k, 8) . '=' . $v);
            }
        }

        // Make sure errors are hidden if debug is off.
        $debug = isset($values['debug']) ? $values['debug'] : false;
        $debug = class_exists('PHPUnit_Framework_MockObject_MockBuilder', false) ? true : $debug;
        $values['debug'] = $debug;
        error_reporting($debug ? E_ALL : 0);
        ini_set('display_errors', $debug);

        if (isset($values['dbOptions'])) {
            if (isset($values['dbOptions']['driver'])) {
                $this['db.options'] = $values['dbOptions'];
            }
            else {
                $this['dbs.options'] = $values['dbOptions'];
            }

            unset($values['dbOptions']);
        }

        if (isset($values['routes'])) {
            $routes = $values['routes'];
            unset($values['routes']);
        }

        if (isset($values['events'])) {
            $events = $values['events'];
            unset($values['events']);
        }

        parent::__construct($values);

        $this->register(new CoreServiceProvider);
        $this->providers[] = $this['middleware.core'];
        $this->providers[] = $this['middleware.jwt'];

        if (!empty($events)) {
            foreach ($events as $event => $callback) {
                $this->on($event, $callback);
            }
        }

        if (!empty($routes)) {
            foreach ($routes as &$route) {
                list($method, $path, $callback) = $route;
                $this->match($path, $callback)->method($method);
            }
        }

        $this->error([$this, 'onError']);
    }

    public function spentTime()
    {
        return microtime() - $this->timerStart;
    }

    public function onError(Exception $e)
    {
        /** @var LoggerInterface $logger */
        $logger = $this['logger'];

        if ($this['debug']) {
            throw $e;
        }

        $logger->error($e->getMessage());

        if ($e instanceof DBALException) {
            $message = $this['debug'] ? $e->getMessage() : 'Database error #' . $e->getCode();

            return new JsonResponse(['message' => $message], 500);
        }

        if ($e instanceof MethodNotAllowedException) {
            return new JsonResponse(['message' => $e->getMessage()], 404);
        }
    }

    public function terminate(Request $req, Response $res)
    {
        parent::terminate($req, $res);

        $terminate = $res instanceof ActiveResponse;
        $terminate |= $res instanceof TerminateAwareJsonResponse;
        if ($terminate) {
            foreach ($res->terminateCallbacks() as &$callback) {
                $callback($req, $res);
            }
        }
    }
}
