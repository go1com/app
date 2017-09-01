<?php

namespace go1\app\domain\profiler;

use Exception;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

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
    public function collect(Request $request, Response $response, Exception $exception = null)
    {
        $data = [
            'calls'       => [],
            'error_count' => 0,
            'methods'     => [],
            'total_time'  => 0,
        ];

        $aggregate = function (array $request, array $response, array $time, bool $error) use (&$data) {
            $method = $request['method'];
            if (!isset($data['methods'][$method])) {
                $data['methods'][$method] = 0;
            }

            $data['methods'][$method]++;
            $data['total_time'] += $time['total'];
            $data['error_count'] += (int) $error;
        };

        foreach ($this->history as $call) {
            $request = $this->collectRequest($call);
            $response = $this->collectResponse($call);
            $time = $this->collectTime($call);
            $error = $call->getResponse()->isError();

            $aggregate($request, $response, $time, $error);

            $data['calls'][] = [
                'request'  => $request,
                'response' => $response,
                'time'     => $time,
                'error'    => $error,
            ];
        }

        $this->data = $data;
    }

    public function getCalls(): array
    {
        return $this->data['calls'] ?? [];
    }

    public function countErrors(): int
    {
        return $this->data['error_count'] ?? 0;
    }

    public function getMethods(): array
    {
        return $this->data['methods'] ?? [];
    }

    public function getTotalTime(): int
    {
        return isset($this->data['total_time']) ? $this->data['total_time'] : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'guzzle';
    }

    private function collectRequest(RequestInterface $req)
    {
        return [
            'headers' => $req->getHeaders(),
            'method'  => $req->getMethod(),
            'host'    => $req->getUri()->getHost(),
            'port'    => $req->getUri()->getPort(),
            'path'    => $req->getUri()->getPath(),
            'query'   => $req->getUri()->getQuery(),
            'body'    => $req->getBody()->getContents(),
        ];
    }

    private function collectResponse(RequestInterface $req)
    {
        $response = $req->getResponse();
        $body = $response->getBody(true);

        return [
            'statusCode'   => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers'      => $response->getHeaders(),
            'body'         => $body,
        ];
    }

    private function collectTime(GuzzleRequestInterface $request)
    {
        $response = $request->getResponse();

        return [
            'total'      => $response->getInfo('total_time'),
            'connection' => $response->getInfo('connect_time'),
        ];
    }
}
