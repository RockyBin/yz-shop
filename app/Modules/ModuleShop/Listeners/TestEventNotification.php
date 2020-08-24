<?php

namespace App\Modules\ModuleShop\Listeners;

use App\Modules\ModuleShop\Events\TestEventTrigger;

class TestEventNotification
{
    /**
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     *
     * @param  TestEventTrigger  $event
     * @return void
     */
    public function handle(TestEventTrigger $event)
    {
        echo "test event trigger, name is ".$event->name;
    }
}