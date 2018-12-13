<?php

namespace go1\app;

use go1\app\App as GO1;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class DomainService extends GO1
{
    const NAME    = SERVICE_NAME;
    const VERSION = SERVICE_VERSION;

    public function __construct($values = [])
    {
        $serviceProviders = $values['serviceProviders'] ?? [];
        unset($values['serviceProviders']);
        parent::__construct($values);

        // register configured service providers
        foreach ($serviceProviders as $serviceProvider) {
            $serviceProvider instanceOf ServiceProviderInterface
                ? $this->register($serviceProvider)
                : $this->register($serviceProvider[0], $serviceProvider[1]);
        }

        // default endpoint
        $this->get('/', function () {
            return new JsonResponse(['service' => static::NAME, 'version' => static::VERSION, 'time' => time()]);
        });
    }
}
