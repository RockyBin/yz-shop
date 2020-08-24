<?php
/**
 * 团队经销商合计
 * User: liyaohui
 * Date: 2019/11/29
 * Time: 16:33
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;

class ConditionTeamDealerNum extends abstractCondition
{
    protected $name = '团队经销商等级人数满';

    public function __construct($value)
    {
        if (is_array($value)) {
            $this->value = [
                'dealer_level_id' => $value['dealer_level_id'],
                'member_count' => $value['member_count']
            ];
        } else {
            $this->value = [
                'dealer_level_id' => '',
                'member_count' => $value
            ];
        }
    }

    /**
     * 获取升级条件文案
     * @return mixed|string
     */
    public function getNameText()
    {
        $ids = $this->value['dealer_level_id'];
        if (!$ids || !is_array($ids)) {
            $levelName = '';
        } else {
            $levelName = DealerLevel::getLevelName($ids[0]);
        }
        if ($levelName) {
            $condName = "团队 {$levelName} 人数满";
        } else {
            $condName = $this->name;
        }

        return $condName . $this->value['member_count'] . $this->unit;
    }

    /**
     * 判断某经销商是否满足此条件
     * @param int $memberId 经销商会员id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $ids = $this->value['dealer_level_id'];
        if (!$ids || !is_array($ids)) {
            return false;
        }
        $count = DealerParentsModel::query()
            ->from('tbl_dealer_parents as dp')
            ->join('tbl_member as m', 'm.id', 'dp.member_id')
            ->where('dp.site_id', getCurrentSiteId())
            ->where('dp.parent_id', $memberId)
            ->where('m.status', Constants::MemberStatus_Active)
            ->where(function($query) use ($ids) {
                $query->where('m.dealer_level', $ids[0])
                    ->orWhere('m.dealer_hide_level', $ids[0]);
            })
            ->count();
        return $count >= $this->value['member_count'];
    }
}