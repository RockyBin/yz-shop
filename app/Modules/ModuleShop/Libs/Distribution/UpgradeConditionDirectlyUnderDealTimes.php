<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use YZ\Core\Site\Site;

/**
 * 直属下级成交次数类型的分销商等级升级条件
 */
class UpgradeConditionDirectlyUnderDealTimes extends abstractCondition
{
    protected $name = "直推订单笔数";

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
        return $this->getTypeName() . "满 " . $this->value . " 笔";
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
        $siteId = Site::getCurrentSite()->getSiteId();
        $DistributionSetting = (new DistributionSetting())->getInfo();
        $calc_valid_condition = $DistributionSetting['baseinfo']['calc_upgrade_valid_condition'];
        $orderStatus =
            $calc_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
        // 该条件会员也生效 所以要拿实时的数据去查询
        $count = OrderMembersHistoryModel::query()
            ->from('tbl_order_members_history as oh')
            ->join('tbl_order as o', 'o.id', 'oh.order_id')
            ->where('oh.site_id', $siteId)
            ->where('oh.member_id', $memberId)
            ->whereIn('o.status', $orderStatus)
            ->where('oh.level', 1)
            ->where('oh.type', 0)
            ->count();
//        $Distributior = (new Distributor($memberId))->getModel();
//        $time = $calc_valid_condition == 1 ? $Distributior->directly_under_deal_times : $Distributior->directly_under_buy_times;

        return $count >= $this->value;
    }
}
