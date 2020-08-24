<?php

namespace App\Modules\ModuleShop\Libs\Statistics;

/**
 *
 * @author Administrator
 */
interface StatisticsInterface
{
    /**
     * 根据type获取相关的statistics模型，若不存在，需要建立
     */
    public function getModel($type);
    /**
     * 主要负责计算过程
     */
    public function calc();
    /**
     * 根据type来设定Time的值，
     * 对于Time的要求是，假如需要存某季度的话，存入的是某季度开始的第一天。例如第三季度，存入的则是2019年7月1日的时间戳
     */
    public function setTime();

    /**
     * 主要负责保存的过程
     */
    public function  save();

}
