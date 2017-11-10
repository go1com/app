<?php

namespace go1\app\domain\profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpKernel\DataCollector\RequestDataCollector as OriginalRequestDataCollector;

class RequestDataCollector extends OriginalRequestDataCollector
{
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        parent::collect($request, $response, $exception);

        $sensorKeys = ['password', 'private-key', 'private_key'];
        $dataKeys = ['request_server', 'request_headers'];

        foreach ($dataKeys as $dataKey) {
            foreach ($this->data[$dataKey] as $key => $value) {
                foreach ($sensorKeys as $sensorKey) {
                    if (strstr(strtolower($key), $sensorKey)) {
                        $this->data[$dataKey][$key] = '***';
                        break;
                    }
                }
            }
        }
    }
}