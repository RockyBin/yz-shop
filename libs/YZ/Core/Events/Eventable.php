<?php

namespace YZ\Core\Events;

use Illuminate\Contracts\Events\Dispatcher;

trait Eventable
{
    public $dispatcher = null;
    /**
     * Set the event dispatcher instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function setEventDispatcher(Dispatcher $dispatcher = null)
    {
        if(!$dispatcher) $dispatcher = new \Illuminate\Events\Dispatcher();
        $this->dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     *
     * @return void
     */
    public function unsetEventDispatcher()
    {
        $this->dispatcher = null;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    public function registerEvent($event, $callback)
    {
        if (!isset($this->dispatcher)) $this->setEventDispatcher();
        if (isset($this->dispatcher)) {
            $name = static::class;
            $this->dispatcher->listen("{$event}: {$name}", $callback);
        }
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    protected function fireEvent($event, $halt = true)
    {
        if (! isset($this->dispatcher)) {
            return true;
        }
        $method = $halt ? 'until' : 'fire';
        $args = array_slice(func_get_args(),2);
        return $this->dispatcher->{$method}(
            "{$event}: ".static::class, $args ? $args : $this
        );
    }
}