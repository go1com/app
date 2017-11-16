<?php

namespace go1\app\domain\profiler;

use GraphAware\Bolt\Result\Result;
use GraphAware\Neo4j\Client\ClientBuilder;
use GraphAware\Neo4j\Client\Event\FailureEvent;
use GraphAware\Neo4j\Client\Event\PostRunEvent;
use GraphAware\Neo4j\Client\Event\PreRunEvent;
use GraphAware\Neo4j\Client\Neo4jClientEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @author Xavier Coureau <xavier@pandawan-technology.com>
 * @see https://github.com/neo4j-contrib/neo4j-symfony/blob/master/Collector/Neo4jDataCollector.php
 */
class Neo4jDataCollector extends DataCollector
{
    private $nbQueries = 0;
    private $statements = [];
    private $statementsHash = [];

    public function getName()
    {
        return 'neo4j';
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data['time'] = $this->getElapsedTime();
        $this->data['nb_queries'] = $this->nbQueries;
        $this->data['statements'] = $this->statements;
        $this->data['failed_statements'] = array_filter($this->getStatements(), function ($statement) {
            return !isset($statement['success']) || !$statement['success'];
        });
    }

    public function getQueryCount()
    {
        return $this->data['nb_queries'];
    }

    public function getStatements()
    {
        return $this->data['statements'];
    }

    public function getElapsedTime()
    {
        $time = 0;

        foreach ($this->statements as $statement) {
            if (!isset($statement['start_time'], $statement['end_time'])) {
                continue;
            }

            $time += $statement['end_time'] - $statement['start_time'];
        }

        return $time;
    }

    public function attachEventListeners(ClientBuilder &$clientBuilder)
    {
        $clientBuilder->registerEventListener(Neo4jClientEvents::NEO4J_PRE_RUN, [$this, 'onPreRun']);
        $clientBuilder->registerEventListener(Neo4jClientEvents::NEO4J_POST_RUN, [$this, 'onPostRun']);
        $clientBuilder->registerEventListener(Neo4jClientEvents::NEO4J_ON_FAILURE, [$this, 'onFailure']);
    }

    public function onPreRun(PreRunEvent $event)
    {
        // record event
        foreach ($event->getStatements() as $statement) {
            $statementText = $statement->text();
            $statementParams = json_encode($statement->parameters());
            $tag = $statement->getTag() ?: -1;

            // Make sure we do not record the same statement twice
            if (isset($this->statementsHash[$statementText][$statementParams][$tag])) {
                return;
            }

            $idx = $this->nbQueries++;
            $this->statements[$idx] = [
                'start_time' => microtime(true) * 1000,
                'end_time' => microtime(true) * 1000, // same
                'nb_results' => 0,
                'query' => $statementText,
                'parameters' => $statementParams,
                'tag' => $statement->getTag(),
                'statistics' => [],
            ];
            $this->statementsHash[$statementText][$statementParams][$tag] = $idx;
        }
    }

    public function onPostRun(PostRunEvent $event)
    {
        foreach ($event->getResults() as $statementResult) {
            $scheme = 'Http';
            if ($statementResult instanceof Result) {
                $scheme = 'Bolt';
            }

            $statement = $statementResult->statement();
            $statementText = $statement->text();
            $statementParams = $statement->parameters();
            $encodedParameters = json_encode($statementParams);
            $tag = $statement->getTag() ?: -1;

            if (!isset($this->statementsHash[$statementText][$encodedParameters][$tag])) {
                $idx = $this->nbQueries++;
                $this->statements[$idx]['start_time'] = null;
                $this->statementsHash[$idx] = $idx;
            } else {
                $idx = $this->statementsHash[$statementText][$encodedParameters][$tag];
            }

            $statementStatistics = $statementResult->summarize()->updateStatistics();
            $data = [
                'contains_updates'      => $statementStatistics->containsUpdates(),
                'nodes_created'         => $statementStatistics->nodesCreated(),
                'nodes_deleted'         => $statementStatistics->nodesDeleted(),
                'relationships_created' => $statementStatistics->relationshipsCreated(),
                'relationships_deleted' => $statementStatistics->relationshipsDeleted(),
                'properties_set'        => $statementStatistics->propertiesSet(),
                'labels_added'          => $statementStatistics->labelsAdded(),
                'labels_removed'        => $statementStatistics->labelsRemoved(),
                'indexes_added'         => $statementStatistics->indexesAdded(),
                'indexes_removed'       => $statementStatistics->indexesRemoved(),
                'constraints_added'     => $statementStatistics->constraintsAdded(),
                'constraints_removed'   => $statementStatistics->constraintsRemoved(),
            ];
            $this->statements[$idx] = array_merge($this->statements[$idx], [
                'end_time'   => microtime(true) * 1000,
                'nb_results' => $statementResult->size(),
                'statistics' => $data,
                'scheme'     => $scheme,
                'success'    => true,
            ]);
        }
    }

    public function onFailure(FailureEvent $event)
    {
        $exception = $event->getException();
        $idx = $this->nbQueries - 1;
        $this->statements[$idx] = array_merge($this->statements[$idx], [
            'end_time' => microtime(true) * 1000,
            'exceptionCode' => method_exists($exception, 'classification') ? $exception->classification() : '',
            'exceptionMessage' => method_exists($exception, 'getMessage') ? $exception->getMessage() : '',
            'success' => false,
        ]);
    }
}
