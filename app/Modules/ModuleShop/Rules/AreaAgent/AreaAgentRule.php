<?php

namespace App\Modules\ModuleShop\Rules\AreaAgent;


use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use Illuminate\Contracts\Validation\Rule;

class AreaAgentRule implements Rule
{
    protected $percentage = AreaAgentLevelModel::WHERE_DECIMAL;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {

        $this->percentage -= (float)$value;

        if ($this->percentage < 0)
        {
            $this->attribute = AreaAgentLevelModel::AREA_AGENT_ARRAY[$attribute] . ' -总的设置不能大于'. AreaAgentLevelModel::WHERE_DECIMAL;
            return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->attribute;
    }

    public static function __callStatic($method, $arguments)
    {
        return (new self)->{$method}(...$arguments);
    }
}
