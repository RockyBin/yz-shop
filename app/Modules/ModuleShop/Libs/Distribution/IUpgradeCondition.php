<?php
namespace App\Modules\ModuleShop\Libs\Distribution;

/**
 *
 * @author Administrator
 */
interface IUpgradeCondition {
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
     * 判断某分销商是否满足此分销条件
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     */
    public function canUpgrade($memberId, $params = []);
}
