<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use YZ\Core\Model\MemberParentsModel;

/**
 * 团队总人数类型的分销商等级升级条件
 */
class UpgradeConditionTotalTeam extends abstractCondition
{
    protected $name = "分销团队总人数";
    protected $onlyDistributor = true;
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getDesc()
    {
        return $this->getTypeName() . "满 " . $this->value . " 人";
    }

    /**
     * 判断某分销商是否满足此分销条件（包括自己）
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->beforeCheckUpgrade($params)) {
            return false;
        }
        $level = $this->getDistributionLevel($params);
        if (!$level) {
            return false;
        }
        $count = MemberParentsModel::query()
            ->from('tbl_member_parents as mp')
            ->join('tbl_member as m', 'm.id', 'mp.member_id')
            ->where('mp.parent_id', $memberId)
            ->where('m.status', 1)
            ->whereBetween('mp.level', [1, $level])
            ->count();
        return $count + 1 >= $this->value;
    }
}
