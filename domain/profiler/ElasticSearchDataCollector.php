<?php

namespace go1\app\domain\profiler;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Serializable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class ElasticSearchDataCollector extends DataCollector implements LoggerInterface, Serializable
{
    use LoggerTrait;

    private $debug;

    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    public function getName()
    {
        return 'es';
    }

    public function getData()
    {
        return $this->data;
    }

    public function collect(Request $req, Response $res, Exception $exception = null)
    {
        return $this->data;
    }

    public function log($level, $message, array $context = [])
    {
        switch ($level) {
            case 'info':
                $this->data[$level][] = [$message, $context];
                break;

            case 'debug':
                if ($this->debug) {
                    # Too much data.
                    # $this->data[$level][] = [$message, $context];
                }
                break;
        }
    }
}
