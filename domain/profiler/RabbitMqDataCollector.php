<?php

namespace go1\app\domain\profiler;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class RabbitMqDataCollector extends DataCollector implements LoggerInterface
{
    use LoggerTrait;

    public function getName()
    {
        return 'mq';
    }

    public function getData()
    {
        $data = $this->data;

        foreach ($data as &$row) {
            $row['message'] = json_decode($row['message'], true);
        }

        return $data;
    }

    public function log($level, $message, array $context = [])
    {
        if ('debug' === $level) {
            $this->data[] = ['message' => $message, 'context' => $context];
        }
    }

    public function collect(Request $request, Response $response, Exception $exception = null)
    {
        return $this->data;
    }

    public function reset()
    {
        $this->data = [];
    }
}
