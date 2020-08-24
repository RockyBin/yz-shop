<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/10/22
 * Time: 18:09
 */

namespace App\Modules\ModuleShop\Libs\Distribution;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use YZ\Core\Site\Site;

class UpgradeConditionIndirectDealTimes extends abstractCondition
{
    protected $name = "间推订单笔数";
    protected $onlyDistributor = true;

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
        return $this->getTypeName() . "满 " . $this->value . " 笔";
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
        $level = $this->getDistributionLevel($params);
        if (!$level || $level == 1) {
            return false;
        }

        $siteId = Site::getCurrentSite()->getSiteId();
        $distributionSetting = (new DistributionSetting())->getBaseInfo();
        $calc_valid_condition = $distributionSetting['calc_upgrade_valid_condition'];
        $orderStatus =
            $calc_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
        $count = OrderMembersHistoryModel::query()
            ->from('tbl_order_members_history as oh')
            ->join('tbl_order as o', 'o.id', 'oh.order_id')
            ->where('oh.site_id', $siteId)
            ->where('oh.member_id', $memberId)
            ->where('oh.has_commission', 1)
            ->whereIn('o.status', $orderStatus)
            ->whereBetween('oh.level', [2, $level])
            ->where('oh.type', 0)
            ->count();

        return $count >= $this->value;
    }
}