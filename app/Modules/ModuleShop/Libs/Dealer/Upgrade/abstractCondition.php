<?php
/**
 * 升级条件抽象类
 * User: liyaohui
 * Date: 2019/11/29
 * Time: 16:34
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


abstract class abstractCondition implements ICondition
{
    protected $value = null;
    protected $name = '';
    protected $productIds = 0;
    protected $unit = '人';// 满足条件的单位

    /**
     * 获取升级条件的文案
     * @return mixed
     */
    public function getNameText()
    {
        return $this->name . $this->value . $this->unit;
    }

}