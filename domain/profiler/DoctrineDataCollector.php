<?php

namespace go1\app\domain\profiler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * DoctrineDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineDataCollector extends DataCollector
{
    private $loggers = [];

    /**
     * @var Connection[]
     */
    private $dbs;

    function __construct(array $dbs)
    {
        $this->dbs = $dbs;
    }

    public function addLogger(string $name, DebugStack $logger)
    {
        $this->loggers[$name] = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, Exception $exception = null)
    {
        foreach ($this->loggers as $name => $logger) {
            $queries[$name] = $this->sanitizeQueries($logger->queries, $name);
        }

        $this->data = ['queries' => $queries ?? []];
    }

    public function getQueryCount()
    {
        return array_sum(array_map('count', $this->data['queries']));
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
        }

        return $time;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'db';
    }

    private function sanitizeQueries($queries, $connectionName)
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($query, $connectionName);
        }

        return $queries;
    }

    private function sanitizeQuery($query, $connectionName)
    {
        $query['params'] = (array) $query['params'];
        foreach ($query['params'] as $j => &$param) {
            if (isset($query['types'][$j])) {
                $type = $query['types'][$j];
                if (is_string($type)) {
                    $type = Type::getType($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();
                    $param = $type->convertToDatabaseValue($param, $this->dbs[$connectionName]->getDatabasePlatform());
                }
            }

            list($param, $explainable) = $this->sanitizeParam($param);
            if (!$explainable) {
                $query['explainable'] = false;
            }
        }

        if ($query['sql'] instanceof QueryBuilder) {
            $query['sql'] = $query['sql']->getSQL();
        }

        return $query;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     *
     * @param mixed $var
     * @return array
     */
    private function sanitizeParam($var): array
    {
        if (is_object($var)) {
            return [sprintf('Object(%s)', get_class($var)), false];
        }

        if (is_array($var)) {
            $a = [];
            $original = true;
            foreach ($var as $k => $v) {
                list($value, $orig) = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return [$a, $original];
        }

        if (is_resource($var)) {
            return [sprintf('Resource(%s)', get_resource_type($var)), false];
        }

        return [$var, true];
    }
}
