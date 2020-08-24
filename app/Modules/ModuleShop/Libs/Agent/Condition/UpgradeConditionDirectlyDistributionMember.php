<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;

/**
 * 直推分销人数升级条件
 */
class UpgradeConditionDirectlyDistributionMember extends abstractCondition
{
    protected $value = '';
    protected $name = "直推分销商合计数量满";

    public function __construct($value)
    {
        $this->value = $value;
        $this->textValue = $this->value->member_count;
    }

    public function enabled()
    {
        if (!$this->value->distribution_level_id || !$this->value->member_count) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getTitle()
    {
        $levelText = DistributionLevel::getLevelName($this->value->distribution_level_id);
        return '直推 ' . $levelText . " 分销商合计数量 满 " . $this->value->member_count . " 人";
    }

    /**
     * 判断是否满足此代理升级条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->checkIsAgent($params)) {
            return false;
        }
        if (strpos($this->value->distribution_level_id, ',')) {
            // 此处agent_level_id 有多个值 用逗号隔开,
            $value = explode(',', $this->value->distribution_level_id);
        } else {
            $value = [$this->value->distribution_level_id];
        }
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
            ->where('p.level', '=', 1)
            ->count();
        return $count >= $this->value->member_count;
    }
}
