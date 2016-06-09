<?php

namespace Syncer\Actor;

use Pixie\QueryBuilder\QueryBuilderHandler as QB;

abstract class AbstractActor
{
    /**
     * @var \Pixie\QueryBuilder\QueryBuilderHandler
     */
    protected $srcConnect;

    /**
     * @var \Pixie\QueryBuilder\QueryBuilderHandler
     */
    protected $destConnect;

    /**
     * @var \Pixie\QueryBuilder\QueryBuilderHandler
     */
    protected $stateConnect;

    /**
     * @var Configurator
     */
    protected $config;

    protected $startSyncAt;

    /**
     * @var \DateTime|null
     */
    protected $lastSyncedAt;

    public function __construct(QB $srcConnect, QB $destConnect, QB $stateConnect, Configurator $config)
    {
        $this->srcConnect = $srcConnect;
        $this->destConnect = $destConnect;
        $this->stateConnect = $stateConnect;
        $this->config = $config;

        $this->startSyncAt = date('Y-m-d H:i:s');
        $this->obtainLastSyncedAt();
    }

    protected function srcQueryBuilder()
    {
        return clone $this->srcConnect;
    }

    protected function destQueryBuilder()
    {
        return clone $this->destConnect;
    }

    protected function stateQueryBuilder()
    {
        return clone $this->stateConnect;
    }

    public function runSyncTo(callable $eachCallback = null)
    {
        if ($this->lastSyncedAt === null) {
            $this->initialSync($eachCallback);
        } else {
            $this->regularSync($eachCallback);
        }
    }

    abstract protected function initialSync(callable $eachCallback = null);

    abstract protected function regularSync(callable $eachCallback = null);

    public function configurator()
    {
        return $this->config;
    }

    protected function obtainLastSyncedAt()
    {
        $queryResult = $this->stateQueryBuilder()
            ->table('state_actors')
            ->where('name', '=', $this->config->name())
            ->first();

        $lastSyncedAt = $queryResult instanceof \stdClass ? $queryResult->synced_at : null;

        $this->lastSyncedAt = $lastSyncedAt;
    }

    protected function updateLastSyncedAt()
    {
        $this->stateQueryBuilder()
            ->table('state_actors')
            ->where('name', '=', $this->config->name())
            ->update(['synced_at' => $this->startSyncAt]);
    }
}