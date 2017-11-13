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

        $sensorKeys = [
            'password',
            'private-key',
            'private_key',
            'secret',
            'app_debug',
            'cache_backend',
            'cache_host',
            'cache_port',
            'content_length',
            'content_type',
            'dev_db_host',
            'dev_db_password',
            'dev_db_username',
            'document_root',
            'document_uri',
            'env',
            'fcgi_role',
            'gateway_interface',
            'go1_db_host',
            'go1_db_name',
            'go1_db_password',
            'go1_db_port',
            'go1_db_slave',
            'go1_db_username',
            'home',
            'hostname',
            'http_authorization',
        ];
        $dataKeys = ['request_server', 'request_headers'];

        foreach ($dataKeys as $dataKey) {
            foreach ($this->data[$dataKey] as $key => $value) {
                foreach ($sensorKeys as $sensorKey) {
                    if (false !== strpos(strtolower($key), $sensorKey)) {
                        unset($this->data[$dataKey][$key]);
                        break;
                    }
                }
            }
        }
    }
}
