<?php

namespace go1\app;

use go1\jwt_middleware\JwtMiddleware;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class App extends Application
{
    public function __construct(array $values = [])
    {
        parent::__construct($values);

        $this->providers[] = new JwtMiddleware();

        // Convert json request to array
        $this->before(function (Request $request) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : []);
            }
        });

        $this->get('/', function () {
            return $this->json(['time' => isset($this['time']) ? $this['time'] : time()]);
        });
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
}
