<?php

namespace go1\app\domain\profiler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use go1\util\DB;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;

class DatabaseProfilerStorage implements ProfilerStorageInterface
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function install()
    {
        DB::install(
            $this->db, [
                function (Schema $schema) {
                    if (!$schema->hasTable('profiler_items')) {
                        $table = $schema->createTable('profiler_items');
                        $table->addColumn('token', Type::STRING);
                        $table->addColumn('ip', Type::STRING);
                        $table->addColumn('method', Type::STRING);
                        $table->addColumn('url', Type::STRING);
                        $table->addColumn('time', Type::INTEGER);
                        $table->addColumn('parent', Type::STRING, ['notnull' => false]);
                        $table->addColumn('status_code', Type::STRING);
                        $table->addColumn('data', Type::BLOB);
                        $table->addColumn('children', Type::STRING);
                        $table->setPrimaryKey(['token']);
                        $table->addIndex(['ip']);
                        $table->addIndex(['method']);
                        $table->addIndex(['url']);
                        $table->addIndex(['time']);
                        $table->addIndex(['parent']);
                        $table->addIndex(['status_code']);
                    }
                },
            ]
        );
    }

    public function find($ip, $url, $limit, $method, $start = null, $end = null)
    {
        $q = $this
            ->db
            ->createQueryBuilder()
            ->select('*')
            ->from('profiler_items')
            ->setMaxResults($limit)
            ->setFirstResult($start)
            ->orderBy('id', 'DESC');

        $ip && $q->andWhere('ip = :ip')->setParameter(':ip', $ip);
        $url && $q->andWhere('url = :url')->setParameter(':url', $url);
        $start && $q->andWhere('time >= :start')->setParameter(':start', $start);
        $end && $q->andWhere('time <= :end')->setParameter(':end', $end);
        $q = $q->execute();

        while ($row = $q->fetch(DB::OBJ)) {
            $row->data = unserialize($row->data);
            $row->children = unserialize($row->children);
            $rows[] = $row;
        }

        return $rows ?? [];
    }

    public function write(Profile $profile)
    {
        $fields = [
            'token'       => $profile->getToken(),
            'ip'          => $profile->getIp(),
            'method'      => $profile->getMethod(),
            'url'         => $profile->getUrl(),
            'time'        => $profile->getTime(),
            'parent'      => $profile->getParentToken(),
            'status_code' => $profile->getStatusCode(),
            'data'        => serialize($profile->getCollectors()),
            'children'    => serialize(
                array_map(
                    function (Profile $p) {
                        return $p->getToken();
                    },
                    $profile->getChildren()
                )
            ),
        ];

        $this->db->insert('profiler_items', $fields);
    }

    public function purge()
    {
        $this->db->delete('profiler_items', []);
    }

    public function read($token)
    {
        $items = $this->readMultiple([$token]);

        return $items ? $items[0] : null;
    }

    private function readMultiple(array $tokens)
    {
        $q = $this
            ->db
            ->executeQuery('SELECT * FROM profiler_items WHERE token IN (?)', [$tokens], [DB::STRINGS]);

        while ($row = $q->fetch(DB::OBJ)) {
            $rows[] = $this->createProfileFromData($row->token, $row);
        }

        return $rows ?? [];
    }

    protected function createProfileFromData($token, $data, $parent = null)
    {
        $profile = new Profile($token);
        $profile->setIp($data['ip']);
        $profile->setMethod($data['method']);
        $profile->setUrl($data['url']);
        $profile->setTime($data['time']);
        $profile->setStatusCode($data['status_code']);
        $profile->setCollectors($data['data']);

        if (!$parent && $data['parent']) {
            $parent = $this->read($data['parent']);
        }

        if ($parent) {
            $profile->setParent($parent);
        }

        if ($data->children) {
            foreach ($this->readMultiple(json_decode($data->children)) as $_) {
                $profile->addChild($_);
            }
        }

        return $profile;
    }
}
