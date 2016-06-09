<?php namespace Syncer\Actor;

class OneToOneActor extends AbstractActor
{
    protected function initialSync(callable $eachCallback = null)
    {
        $counter = 1;

        $srcData = $this->srcConnect->get();
        $this->destConnect->pdo()->beginTransaction();

        call_user_func_array($eachCallback, ['initialSync', 'before', $srcData]);

        foreach ($srcData as $row) {
            $row = (array)$row;

            $destFields = array_merge(
                $row,
                array_fill_keys($this->config->destStateFields(), $this->startSyncAt)
            );
            unset($destFields[$this->config->srcIdField()]);

            $srcId = $row[$this->config->srcIdField()];
            $destId = $this->destConnect->insert((array)$destFields);

            $this->insertHash($this->config->name(), $srcId, (array)$row, $destId, $destFields);

            call_user_func_array($eachCallback, ['initialSync', 'process', $counter++, count($srcData)]);
        }

        $this->destConnect->pdo()->commit();

        $this->updateLastSyncedAt();

        call_user_func_array($eachCallback, ['initialSync', 'after']);
    }

    protected function regularSync(callable $eachCallback = null)
    {
        $stateSyncedRows = $this->stateQueryBuilder()->table('state_rows')
            ->where('actor_name', '=', $this->config->name())
            ->get();

        // Remove / Update operations
        foreach ($stateSyncedRows as $stateSyncedRow) {
            $srcEntity = $this->srcQueryBuilder()
                ->where($this->config->srcIdField(), '=', $stateSyncedRow->src_id)
                ->first();

            $destEntity = $this->destQueryBuilder()
                ->where($this->config->destIdField(), '=', $stateSyncedRow->dest_id)
                ->first();

            $action = $this->definiteAction($stateSyncedRow, $srcEntity, $destEntity);

            if ($action !== 'nothing') {
                $this->doAction($action, $stateSyncedRow, $srcEntity, $destEntity);
            }
        }

        // Create operations
        $newSrcEntries = $this->srcQueryBuilder()
            ->where($this->config->srcCreatedField(), '>', $this->lastSyncedAt)
            ->get();

        foreach ($newSrcEntries as $newSrcEntry) {
            $this->actionSrcCreated($newSrcEntry);
        }

        $newDestEtries = $this->destQueryBuilder()
            ->where($this->config->destCreatedField(), '>', $this->lastSyncedAt)
            ->get();

        foreach ($newDestEtries as $newDestEntry) {
            $this->actionDestCreated($newDestEntry);
        }

        // Update synced_at for current actor
        $this->stateQueryBuilder()->table('state_actors')
            ->where('name', '=', $this->config->name())
            ->update([
                'synced_at' => $this->startSyncAt,
            ]);
    }

    private function prepareHashString($config, $fields)
    {
        $valuesForHash = [];

        $fieldsForHash = array_flatten([
            $config['sync_fields'],
            $config['created_field'],
            $config['updated_field']
        ]);

        array_walk($fields, function ($value, $key) use ($fieldsForHash, &$valuesForHash) {
            if (in_array($key, $fieldsForHash)) {
                $valuesForHash[] = $value;
            }
        });

        return md5(implode('|', $valuesForHash));
    }

    private function insertHash($actorName, $srcId, $srcFields, $destId, $destFields)
    {
        $srcHash = $this->prepareHashString($this->config->srcConfig(), $srcFields);
        $destHash = $this->prepareHashString($this->config->destConfig(), $destFields);

        $this->stateQueryBuilder()
            ->table('state_rows')
            ->insert([
                'actor_name' => $actorName,
                'src_id' => $srcId,
                'src_hash' => $srcHash,
                'dest_id' => $destId,
                'dest_hash' => $destHash,
            ]);
    }

    private function definiteAction($stateSyncedRow, $srcEntity, $destEntity)
    {
        if (in_array(null, [$srcEntity, $destEntity])) {
            return 'delete';
        }

        $srcHash = $this->prepareHashString($this->config->srcConfig(), $srcEntity);
        $destHash = $this->prepareHashString($this->config->destConfig(), $destEntity);

        if ($stateSyncedRow->src_hash !== $srcHash) {
            return 'srcUpdated';
        }

        if ($stateSyncedRow->dest_hash !== $destHash) {
            return 'destUpdated';
        }

        return 'nothing';
    }

    private function doAction($action, $stateEntity, $srcEntity, $destEntity)
    {
        if ($action === 'delete') {
            $this->actionDelete($stateEntity, $srcEntity, $destEntity);
        }

        if ($action === 'srcUpdated') {
            $this->actionSrcUpdated($stateEntity, $srcEntity, $destEntity);
        }

        if ($action === 'destUpdated') {
            $this->actionDestUpdated($stateEntity, $srcEntity, $destEntity);
        }
    }

    private function actionDelete($stateEntity, $srcEntity, $destEntity)
    {
        $srcEntity && $this->srcQueryBuilder()->where($this->config->srcIdField(), '=', $srcEntity->id)->delete();
        $destEntity && $this->destQueryBuilder()->where($this->config->destIdField(), '=', $destEntity->id)->delete();
        $this->stateQueryBuilder()->table('state_rows')->where('id', '=', $stateEntity->id)->delete();
    }

    private function actionSrcUpdated($stateEntity, $srcEntity, $destEntity)
    {
        $newDestEntity = clone $destEntity;
        $newDestEntity->{$this->config->destUpdatedField()} = $this->startSyncAt;

        foreach ($srcEntity as $field => $value) {
            if (in_array($field, $this->config->srcSyncFields())) {
                $newDestEntity->{$field} = $value;
            }
        }

        $newSrcHash = $this->prepareHashString($this->config->srcConfig(), $srcEntity);
        $newDestHash = $this->prepareHashString($this->config->destConfig(), $newDestEntity);

        $this->destQueryBuilder()
            ->where($this->config->destIdField(), '=', $destEntity->id)
            ->update((array)$newDestEntity);

        $this->stateQueryBuilder()
            ->table('state_rows')
            ->where('id', '=', $stateEntity->id)
            ->update([
                'src_hash' => $newSrcHash,
                'dest_hash' => $newDestHash,
            ]);
    }

    private function actionDestUpdated($stateEntity, $srcEntity, $destEntity)
    {
        $newSrcEntity = clone $srcEntity;
        $newSrcEntity->{$this->config->destUpdatedField()} = $this->startSyncAt;

        foreach ($destEntity as $field => $value) {
            if (in_array($field, $this->config->destSyncFields())) {
                $newSrcEntity->{$field} = $value;
            }
        }

        $newSrcHash = $this->prepareHashString($this->config->srcConfig(), $newSrcEntity);
        $newDestHash = $this->prepareHashString($this->config->destConfig(), $destEntity);

        $this->srcQueryBuilder()
            ->where($this->config->srcIdField(), '=', $srcEntity->id)
            ->update((array)$newSrcEntity);

        $this->stateQueryBuilder()
            ->table('state_rows')
            ->where('id', '=', $stateEntity->id)
            ->update([
                'src_hash' => $newSrcHash,
                'dest_hash' => $newDestHash,
            ]);
    }

    private function actionSrcCreated($existSrcEntity)
    {
        $newDestEntity = array_merge(
            (array)$existSrcEntity,
            array_fill_keys($this->config->destStateFields(), $this->startSyncAt)
        );
        unset($newDestEntity[$this->config->srcIdField()]);

        $newDestId = $this->destQueryBuilder()->insert($newDestEntity);

        $newSrcHash = $this->prepareHashString($this->config->srcConfig(), $existSrcEntity);
        $newDestHash = $this->prepareHashString($this->config->destConfig(), $newDestEntity);

        $this->stateQueryBuilder()
            ->table('state_rows')
            ->insert([
                'actor_name' => $this->config->name(),
                'src_id' => $existSrcEntity->id,
                'src_hash' => $newSrcHash,
                'dest_id' => $newDestId,
                'dest_hash' => $newDestHash,
            ]);
    }

    private function actionDestCreated($existDestEntity)
    {
        $newSrcEntity = array_merge(
            (array)$existDestEntity,
            array_fill_keys($this->config->destStateFields(), $this->startSyncAt)
        );
        unset($newSrcEntity[$this->config->destIdField()]);

        $newSrcId = $this->srcQueryBuilder()->insert($newSrcEntity);

        $newSrcHash = $this->prepareHashString($this->config->srcConfig(), $existDestEntity);
        $newDestHash = $this->prepareHashString($this->config->destConfig(), $newSrcEntity);

        $this->stateQueryBuilder()
            ->table('state_rows')
            ->insert([
                'actor_name' => $this->config->name(),
                'src_id' => $newSrcId,
                'src_hash' => $newSrcHash,
                'dest_id' => $existDestEntity->id,
                'dest_hash' => $newDestHash,
            ]);
    }
}
