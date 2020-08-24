<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use  App\Modules\ModuleShop\Libs\Model\DistributorModel;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

/**
 * 团队中任意等级分销商合计数量升级条件
 */
class UpgradeConditionTeamArbitrarilyLevelDistributionMember extends abstractCondition
{
    protected $value = '';
    protected $name = "团队中分销商合计数量满";
    protected $onlyAgent = true;

    public function __construct($value)
    {
        $this->value = $value;
        $this->textValue = $this->value->member_count;
    }

    public function enabled()
    {
        if (
            !$this->value->distribution_level_id
            || !$this->value->member_count
        ) {
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
        return '团队 ' . $levelText . " 分销商合计数量 满 " . $this->value->member_count . " 人";
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
            // 此处distribution_level_id 有两个值 用逗号隔开,
            $value = explode(',', $this->value->distribution_level_id);
        } else {
            $value = [$this->value->distribution_level_id];
        }
        $siteId = Site::getCurrentSite()->getSiteId();
        //先把团队中的所有人拿出来
//        $sql = 'select group_concat(member_id) as member_id  from tbl_agent_parents where parent_id=' . $memberId;
//        $member_id = \DB::select($sql);
        //统计这些人里面那些符合该分销等级
        // 使用一条语句去查询
        $count = DistributorModel::query()->from('tbl_distributor as dis')
            ->join('tbl_agent_parents as agent', function ($join) use ($memberId) {
                $join->on('dis.member_id', 'agent.member_id')
                    ->where('agent.parent_id', $memberId);
            })
            ->where('dis.site_id', $siteId)
            ->where('dis.status', Constants::DistributorStatus_Active)
            ->where('dis.is_del', 0)
            ->whereIn('dis.level', $value)
            ->count();
        // 需要加上自己
        $self = DistributorModel::query()->where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('status', Constants::DistributorStatus_Active)
            ->where('is_del', 0)
            ->whereIn('level', $value)
            ->count();
        $count = $count + $self;
        $flag = $count >= $this->value->member_count;
//        Log::writeLog('agentLevelUpgradeType8888', 'member_id' .$memberId.'count:'.$count.'flag'.$flag);
        return $flag;
    }
}
