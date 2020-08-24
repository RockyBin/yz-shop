<?php
namespace App\Modules\ModuleShop\Events;

use Illuminate\Queue\SerializesModels;

class TestEventTrigger
{
    use SerializesModels;

    public $name;

    /**
     *
     * @param  string  $name
     * @return void
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}