<?php

namespace go1\app\domain;

use Symfony\Component\HttpFoundation\JsonResponse;

class TerminateAwareJsonResponse extends JsonResponse
{
    private $callbacks = [];

    public function terminate(callable $callback)
    {
        $this->callbacks[] = $callback;
    }

    public function terminateCallbacks()
    {
        return $this->callbacks;
    }
}
