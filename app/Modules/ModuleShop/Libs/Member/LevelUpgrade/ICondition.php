<?php
/**
 * 升级条件接口
 */
namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

interface ICondition
{
    /**
     * 获取升级条件的文案
     * @return mixed
     */
    public function getNameText();

    /**
     * 判断某会员是否满足此条件
     * @param int $memberId 经销商会员id
     * @param array $params 额外的参数
     */
    public function canUpgrade($memberId, $params = []);
}