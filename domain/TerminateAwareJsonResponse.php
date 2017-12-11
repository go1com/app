<?php

namespace go1\app\domain;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @deprecated Use ActiveResponse instead.
 */
class TerminateAwareJsonResponse extends JsonResponse
{
    private $callbacks = [];

    public function __construct($data, $status = 200, array $callbacks = [], array $headers = [], $json = false)
    {
        parent::__construct($data, $status, $headers, $json);

        foreach ($callbacks as &$callback) {
            $this->terminate($callback);
        }
    }

    public function terminate(callable $callback)
    {
        $this->callbacks[] = $callback;
    }

    public function terminateCallbacks()
    {
        return $this->callbacks;
    }
}
