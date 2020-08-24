<?php
/**
 * User: liyaohui
 * Date: 2019/10/22
 * Time: 17:50
 */

namespace App\Modules\ModuleShop\Libs\Distribution;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;

class UpgradeConditionIndirectUnderDistributor extends abstractCondition
{
    protected $name = "间推分销商合计数量";
    protected $onlyDistributor = true;

    public function __construct($value)
    {
        $this->value = [
            'member_count' => $value['member_count'],
            'distribution_level_id' => explode(',', $value['distribution_level_id'])
        ];
    }

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getDesc()
    {
        $levelText = DistributionLevel::getLevelName($this->value['distribution_level_id']);
        return '间推 ' .$levelText  . " 分销商合计数量 满 " . $this->value['member_count'] . " 人";
    }

    /**
     * 判断某分销商是否满足此分销条件
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
        if (!$level || $level == 1) {
            return false;
        }
        $value = $this->value['distribution_level_id'];
        $siteId = Site::getCurrentSite()->getSiteId();
        $count = MemberParentsModel::query()->from('tbl_member_parents as p')
            ->join('tbl_distributor as dis', function ($join) use ($value) {
                $join->on('dis.member_id', 'p.member_id')
                    ->where('dis.status', Constants::DistributorStatus_Active)
                    ->whereIn('dis.level', $value)
                    ->where('dis.is_del', 0);
            })
            ->where('p.site_id', $siteId)
            ->where('p.parent_id', $memberId)
            ->whereBetween('p.level', [2, $level])
            ->count();
        return $count >= $this->value['member_count'];
    }
}