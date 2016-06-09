<?php

namespace Syncer;

use Pixie\Connection;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Syncer\Actor\Actor;
use Syncer\Actor\Configurator;
use Syncer\Actor\OneToOneActor;

class SyncProvider
{
    /** @var array Connections of databases */
    private $connections = [];

    /** @var */
    private $stateConnection;

    /** @var array Config */
    private $config;

    /** @var \Syncer\ManagerActor */
    private $manager;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->manager = new ManagerActor();


        $this->configureDatabaseConnections();
        $this->configureActors();
    }

    private function configureDatabaseConnections()
    {
        $this->stateConnection = new QueryBuilderHandler(new Connection(
            $this->config['state_connection']['driver'],
            $this->config['state_connection']['config']
        ));

        foreach ($this->config['connections'] as $name => $config) {
            $this->connections[$name] = new Connection($config['driver'], $config['config']);
        }
    }

    private function configureActors()
    {
        foreach ($this->config['mapping'] as $group) {
            foreach ($group['actors'] as $actorConfig) {
                $srcQueryBuilder = (new QueryBuilderHandler($this->connections[$group['src']]))
                    ->table($actorConfig['src']['table']);
                $destQueryBuilder = (new QueryBuilderHandler($this->connections[$group['dest']]))
                    ->table($actorConfig['src']['table']);

                $stateQueryBuilder = clone $this->stateConnection;

                $config = new Configurator($actorConfig);

                // Тут должен быть factory
                $this->manager->add(
                    new OneToOneActor($srcQueryBuilder, $destQueryBuilder, $stateQueryBuilder, $config)
                );
            }
        }
    }

    public function manager()
    {
        return $this->manager;
    }
}