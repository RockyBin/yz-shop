<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Member\Member;

/**
 * 直属下级会员数量类型的分销商等级升级条件
 */
class UpgradeConditionDirectlyUnderMember extends abstractCondition
{
    protected $name = "直推会员数量";

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 获取此升级条件的说明文本
     *
     * @return string
     */
    public function getDesc()
    {
        return $this->getTypeName() . "满 " . $this->value . " 人";
    }

    /**
     * 判断某分销商是否满足此分销条件
     *
     * @param  int   $memberId 分销商id
     * @param  array $params   额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->beforeCheckUpgrade($params)) {
            return false;
        }
        $member = new Member($memberId);
        return $member->getDirectlyMemberNum() >= $this->value;
    }
}
