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

    public function getName()
    {
        return 'es';
    }

    public function getData()
    {
        if (isset($this->data['info'])) {
            foreach ($this->data['info'] as &$info) {
                if (!isset($info[1])) {
                    continue;
                }

                if (isset($info[1]['uri']) && is_scalar($info[1]['uri'])) {
                    $uri = Request::create(urldecode($info[1]['uri']));
                    $info[1]['uri'] = [
                        'scheme' => $uri->getScheme(),
                        'host'   => $uri->getHost(),
                        'path'   => $uri->getPathInfo(),
                        'query'  => urldecode($uri->getQueryString()),
                    ];
                }

                $data[] = $info[1];
            }
        }

        return $data ?? [];
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
        }
    }
}
