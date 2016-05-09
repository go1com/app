<?php

namespace go1\app;

use Doctrine\DBAL\Connection;
use go1\jwt_middleware\JwtMiddleware;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class App extends Application
{
    public function __construct(array $values = [])
    {
        if (isset($values['routes'])) {
            $routes = $values['routes'];
            unset($values['routes']);
        }

        if (isset($values['events'])) {
            $events = $values['events'];
            unset($values['events']);
        }

        parent::__construct($values);

        // Clean routing
        // @see http://silex.sensiolabs.org/doc/providers/service_controller.html
        $this->register(new ServiceControllerServiceProvider());

        // Auto register doctrine DBAL service provider if the app needs it.
        // @see http://silex.sensiolabs.org/doc/providers/doctrine.html
        if (!empty($values['db.options'])) {
            $this->register(new DoctrineServiceProvider(), ['db.options' => $values['db.options']]);
        }

        // Use go1.jwt-middleware
        $this->providers[] = new JwtMiddleware();

        // Convert json request to array
        $this->before(function (Request $request) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : []);
            }
        });

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
    }

    public function boot()
    {
        $this->error(function (\Exception $e) {
            if ($this->offsetExists('debug') && $this->offsetGet('debug')) {
                throw $e;
            }

            return new JsonResponse(
                ['status' => 'FAILED', 'error' => true, 'message' => $e->getMessage()],
                500
            );
        });

        parent::boot();
    }

    public function terminate(Request $request, Response $response)
    {
        parent::terminate($request, $response);

        $this->closeDatabaseConnection();
    }

    private function closeDatabaseConnection($target = 'default')
    {
        if (class_exists(Connection::class, false)) {
            /** @var Connection $db */
            $db = $this['dbs'][$target];
            if ($db->isConnected()) {
                $db->close();
            }
        }
    }
}
