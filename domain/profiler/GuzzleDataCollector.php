<?php

namespace go1\app\domain\profiler;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Stopwatch\StopwatchEvent;

class GuzzleDataCollector extends DataCollector
{
    private $history;

    public function __construct(GuzzleHistory $history)
    {
        $this->history = $history;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'guzzle';
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, Exception $exception = null)
    {
        $data = [];

        foreach ($this->history->history() as $call) {
            /** @var \GuzzleHttp\Psr7\Request $req */
            /** @var \GuzzleHttp\Psr7\Response $res */
            /** @var StopwatchEvent $watch */
            $req = $call['request'];
            $res = $call['response'];
            $watch = $call['stopwatch'];

            $data[] = [
                'stopwatch' => $watch,
                'request'   => $req = [
                    'headers' => $req->getHeaders(),
                    'method'  => $req->getMethod(),
                    'uri'     => $req->getUri(),
                    'body'    => $req->getBody()->getContents(),
                ],
                'response'  => $res = [
                    'statusCode'   => $res->getStatusCode(),
                    'reasonPhrase' => $res->getReasonPhrase(),
                    'headers'      => $res->getHeaders(),
                    'body'         => $res->getBody()->getContents(),
                ],
            ];
        }

        $this->data = $data;
    }
}
