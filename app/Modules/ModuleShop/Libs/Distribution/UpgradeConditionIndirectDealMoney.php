<?php
/**
 * User: liyaohui
 * Date: 2019/10/23
 * Time: 10:40
 */

namespace App\Modules\ModuleShop\Libs\Distribution;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Site\Site;

class UpgradeConditionIndirectDealMoney extends abstractCondition
{
    protected $name = "间推订单金额";
    protected $onlyDistributor = true;

    public function __construct($value)
    {
        $this->value = $value * 100;
    }

    /**
     * 获取此升级条件的说明文本
     *
     * @return string
     */
    public function getDesc()
    {
        return $this->getTypeName() . "满 " . moneyCent2Yuan($this->value) . " 元";
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
        $orderStatus = $calc_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
        $select = $calc_valid_condition == 1 ? 'sum( o.money + o.after_sale_money ) AS total' : 'sum(o.money) as total';
        $query = OrderModel::query()
            ->from('tbl_order as o')
            ->join('tbl_order_members_history as h', function ($join) use ($memberId, $level) {
                $join->on('h.order_id', 'o.id')
                    ->where('h.member_id', $memberId)
                    ->whereBetween('h.level', [2, $level])
                    ->where('h.has_commission', 1)
                    ->where('h.type', 0);
            })
            ->where('o.site_id', $siteId)
            ->whereIn('o.status', $orderStatus)
            ->selectRaw($select)
            ->first();

        return $query['total'] >= $this->value;
    }
}