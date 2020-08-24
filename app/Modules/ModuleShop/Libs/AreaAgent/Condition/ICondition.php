<?php

namespace App\Modules\ModuleShop\Libs\AreaAgent\Condition;

/**
 *
 * @author Administrator
 */
interface ICondition
{
    /**
     * 获取此条件的类型名称
     * @return string
     */
    public function getTypeName();

    /**
     * 获取此条件的说明文本
     * @return string
     */
    public function getDesc();

    /**
     * 返回成为条件的文案
     * @return string
     */
    public function getTitle();
    /**
     * 判断某区域代理是否满足此条件
     * @param int $memberId 会员id
     * @param array $params 额外的参数
     */
    public function canUpgrade($memberId, $params = []);
}
