<?php

namespace Syncer;

use Countable;
use Syncer\Actor\AbstractActor;

class ManagerActor implements Countable
{
    private $actors = [];

    public function add(AbstractActor $actor)
    {
        array_push($this->actors, $actor);
    }

    public function count()
    {
        return count($this->actors);
    }

    public function actors()
    {
        return $this->actors;
    }
}