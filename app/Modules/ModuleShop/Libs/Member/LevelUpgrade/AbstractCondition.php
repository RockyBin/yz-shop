<?php
/**
 * 升级条件抽象类
 */

namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

abstract class AbstractCondition implements ICondition
{
    protected $value = null;
    protected $name = '';
    protected $unit = '元';// 满足条件的单位

    /**
     * 获取升级条件的文案
     * @return mixed
     */
    public function getNameText()
    {
        return $this->name . $this->value . $this->unit;
    }
}