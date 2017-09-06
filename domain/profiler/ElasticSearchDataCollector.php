<?php

namespace go1\app\domain\profiler;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class ElasticSearchDataCollector extends DataCollector implements LoggerInterface
{
    use LoggerTrait;

    private $debug;
    private $history;

    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    public function getName()
    {
        return 'es';
    }

    public function collect(Request $req, Response $res, Exception $exception = null)
    {
        return $this->history;
    }

    public function log($level, $message, array $context = [])
    {
        switch ($level) {
            case 'info':
                $this->history[$level][] = [$message, $context];
                break;

            case 'debug':
                if ($this->debug) {
                    $this->history[$level][] = [$message, $context];
                }
                break;
        }
    }
}
