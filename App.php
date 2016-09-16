<?php

namespace go1\app;

use Doctrine\DBAL\DBALException;
use Exception;
use go1\app\providers\CoreServiceProvider;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

class App extends Application
{
    const NAME = 'go1';

    public function __construct(array $values = [])
    {
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

        $this->register(new CoreServiceProvider());
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

        $this->error(function (Exception $e) {
            if ($e instanceof DBALException) {
                $this['logger'] && $this['logger']->error($e->getMessage());
                return new JsonResponse(['message' => 'Database error #' . $e->getCode()], 500);
            }

            if ($e instanceof MethodNotAllowedException) {
                return new JsonResponse(['message' => $e->getMessage()], 404);
            }
        });
    }
}
