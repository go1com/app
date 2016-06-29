<?php

namespace go1\app\providers;

use Exception;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CoreMiddlewareProvider implements BootableProviderInterface
{
    public function boot(Application $app)
    {
        // Convert json request to array
        $app->before(function (Request $request) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : []);
            }
        });

        // On error.
        $app->error(function (Exception $e) use ($app) {
            if ($app->offsetExists('debug') && $app->offsetGet('debug')) {
                throw $e;
            }

            return new JsonResponse(['message' => $e->getMessage()], 500);
        });
    }
}
