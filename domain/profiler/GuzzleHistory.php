<?php

namespace go1\app\domain\profiler;

use GuzzleHttp\Handler\CurlHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class GuzzleHistory
{
    private $log = [];
    private $stopwatch;

    public function __construct(Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    public function __invoke(CurlHandler $handler)
    {
        return function (RequestInterface $req, $options) use ($handler) {
            $this->stopwatch->start($id = uniqid());
            $promise = $handler($req, $options);

            return $promise->then(
                function (ResponseInterface $res) use ($id, $req) {
                    $stopwatch = $this->stopwatch->stop($id);

                    $this->log[] = [
                        'request'   => $req,
                        'response'  => $res,
                        'stopwatch' => $stopwatch,
                    ];
                }
            );
        };
    }
}
