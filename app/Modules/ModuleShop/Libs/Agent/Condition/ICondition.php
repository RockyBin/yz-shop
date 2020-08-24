<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

/**
 *
 * @author Administrator
 */
interface ICondition
{
    public function enabled();
    /**
     * 获取此升级条件的类型名称
     * @return string
     */
    public function getTypeName();

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getDesc();

    /**
     * 返回升级条件的文案
     * @return string
     */
    public function getTitle();
    /**
     * 判断某代理商是否满足此分销条件
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     */
    public function canUpgrade($memberId, $params = []);
}
