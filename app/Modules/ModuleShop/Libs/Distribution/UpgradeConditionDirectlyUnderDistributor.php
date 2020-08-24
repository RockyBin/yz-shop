<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use YZ\Core\Site\Site;

/**
 * 直属下级分销商数量类型的分销商等级升级条件
 */
class UpgradeConditionDirectlyUnderDistributor extends abstractCondition
{
    protected $name = "直推分销商合计数量";

    public function __construct($value)
    {
        $this->value = [
            'member_count' => $value['member_count'],
            'distribution_level_id' => explode(',', $value['distribution_level_id'])
        ];
    }

    /**
     * 获取此升级条件的说明文本
     *
     * @return string
     */
    public function getDesc()
    {
        $levelText = DistributionLevel::getLevelName($this->value['distribution_level_id']);
        return "直推 " . $levelText . " 分销商合计数量 满 " . $this->value['member_count'] . " 人";
    }

    /**
     * 判断某分销商是否满足此分销条件
     *
     * @param  int $memberId 分销商id
     * @param  array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->beforeCheckUpgrade($params)) {
            return false;
        }
        $value = $this->value['distribution_level_id'];
        $siteId = Site::getCurrentSite()->getSiteId();

        $count = DistributorModel::query()->from('tbl_distributor as dis')
            ->join('tbl_member_parents as mp', function ($join) use ($memberId) {
                $join->on('dis.member_id', 'mp.member_id')
                    ->where('mp.parent_id', $memberId)
                    ->where('mp.level', 1);
            })
            ->where('dis.site_id', $siteId)
            ->where('dis.status', Constants::DistributorStatus_Active)
            ->where('dis.is_del', 0)
            ->whereIn('dis.level', $value)
            ->count();
        return $count >= $this->value['member_count'];
    }
}
