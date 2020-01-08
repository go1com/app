<?php

namespace go1\app\providers;

use Exception;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CoreMiddlewareProvider implements BootableProviderInterface
{
    public function boot(Application $app)
    {
        // Convert json request to array
        $app->before(function (Request $request) {
            if (in_array($request->getMethod(), ['GET', 'HEAD']) || $request->getContentType() !== 'json') {
                return;
            }

            $content = $request->getContent();
            if ($content === '') {
                return;
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(
                    ['message' => 'Invalid JSON payload. ' . json_last_error_msg()],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            if (is_array($data)) {
                $request->request->replace($data);
            }
        });

        // On error.
        if (!$app->offsetExists('debug') || !$app->offsetGet('debug')) {
            $app->error(function (HttpException $e) {
                $httpStatusCode = $e->getStatusCode();
                $headers = $e->getHeaders();
                return new JsonResponse(['message' => $e->getMessage()], $httpStatusCode, $headers);
            });

            $app->error(function (Exception $e) {
                return new JsonResponse(['message' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            });
        }
    }
}
