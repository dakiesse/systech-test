<?php

namespace Syncer\Actor;

class Configurator
{
    /** @var array */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function name()
    {
        return $this->config['name'];
    }

    public function relationType()
    {
        return $this->config['type'];
    }

    public function srcConfig()
    {
        return $this->config['src'];
    }

    public function destConfig()
    {
        return $this->config['dest'];
    }

    public function srcTableName()
    {
        return $this->config['src']['table'];
    }

    public function destTableName()
    {
        return $this->config['src']['table'];
    }

    public function srcIdField()
    {
        return $this->config['src']['id_field'];
    }

    public function destIdField()
    {
        return $this->config['src']['id_field'];
    }

    public function srcSyncFields()
    {
        return $this->config['src']['sync_fields'];
    }

    public function destSyncFields()
    {
        return $this->config['dest']['sync_fields'];
    }

    public function srcSyncFieldsAsString()
    {
        return implode(', ', $this->config['src']['sync_fields']);
    }

    public function destSyncFieldsAsString()
    {
        return implode(', ', $this->config['dest']['sync_fields']);
    }

    public function srcCreatedField()
    {
        return $this->config['src']['created_field'];
    }

    public function destCreatedField()
    {
        return $this->config['dest']['created_field'];
    }

    public function srcUpdatedField()
    {
        return $this->config['src']['updated_field'];
    }

    public function destUpdatedField()
    {
        return $this->config['dest']['updated_field'];
    }

    public function srcStateFields()
    {
        return [
            $this->config['src']['created_field'],
            $this->config['src']['updated_field']
        ];
    }

    public function destStateFields()
    {
        return [
            $this->config['dest']['created_field'],
            $this->config['dest']['updated_field']
        ];
    }
}