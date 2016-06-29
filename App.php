<?php

namespace go1\app;

use go1\app\providers\CoreServiceProvider;
use Silex\Application;

class App extends Application
{
    const NAME = 'go1';

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
    }
}
