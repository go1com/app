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
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class App extends Application
{
    const NAME = 'go1';

    private $timerStart;

    /**
     * App constructor.
     *
     * @param string|array $values
     *   string Path to configuration file.
     *   array  Configuration values for the application.
     */
    public function __construct($values = [])
    {
        // Remove _DOCKER_  prefix from env variables.
        foreach ($_ENV as $k => &$v) {
            if (0 === strpos($k, '_DOCKER_') && !isset($_ENV[substr($k, 8)])) {
                putenv(substr($k, 8) . '=' . $v);
                $_ENV[substr($k, 8)] = $v;
            }
        }

        if (is_string($values)) {
            $values = require $values;
        }

        // Set default timezone.
        date_default_timezone_set(isset($values['timezone']) ? $values['timezone'] : 'UTC');

        // Helper variable to track spent time.
        $this->timerStart = microtime();

        // Make sure errors are hidden if debug is off.
        $debug = isset($values['debug']) ? $values['debug'] : false;
        $debug = class_exists('PHPUnit_Framework_MockObject_MockBuilder', false) ? true : $debug;
        $values['debug'] = $debug;
        error_reporting($debug ? E_ALL : 0);
        ini_set('display_errors', $debug);

        if ($debug) { //No need to install it with display errors disabled
            $this->installErrorHandler();
        }

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

    /**
     * New PHP7 Error subclasses are not caught by Silex. This is a problem in debug mode,
     * because PHP sends back a HTTP Status Code 200 if display_errors is enabled.
     *
     * This sets a 500 status code
     */
    protected function installErrorHandler()
    {
        set_exception_handler(function(\Throwable $e) {
            try {
                http_response_code(500);
                echo $e;
                if ($this->offsetExists('logger')) {
                    /** @var LoggerInterface $logger */
                    $logger = $this['logger'];
                    $logger->error($e->getMessage());
                }
            } finally {
                exit(1);
            }
        });
    }

    public function onError(Exception $e)
    {
        /** @var LoggerInterface $logger */
        $logger = $this['logger'];

        if ($e instanceof MethodNotAllowedHttpException) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        if ($this['debug']) {
            http_response_code(500);
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
