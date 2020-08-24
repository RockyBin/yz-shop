<?php
/**
 * 直推经销商人数
 * User: liyaohui
 * Date: 2019/11/30
 * Time: 11:25
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;

class ConditionDirectlyDealerNum extends abstractCondition
{
    protected $name = '直推经销商人数满';

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
            $condName = "直推 {$levelName} 人数满";
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
        $count = MemberModel::query()->where('site_id', getCurrentSiteId())
            ->where('invite1', $memberId)
            ->where('status', Constants::MemberStatus_Active)
            ->where(function($query) use ($ids) {
                // 改为了单个
                $query->where('dealer_level', $ids[0])
                    ->orWhere('dealer_hide_level', $ids[0]);
            })
            ->count();
        return $count >= $this->value['member_count'];
    }
}