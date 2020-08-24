<?php
/**
 * User: liyaohui
 * Date: 2019/10/23
 * Time: 15:00
 */

namespace App\Modules\ModuleShop\Libs\Distribution;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use YZ\Core\Site\Site;

class UpgradeConditionDirectlyBuyProduct extends abstractCondition
{
    protected $name = "直推指定商品";

    public function __construct($value, array $productIds)
    {
        $this->value = $value;
        $this->productIds = $productIds;
    }

    /**
     * 获取此升级条件的说明文本
     *
     * @return string
     */
    public function getDesc()
    {
        return $this->getTypeName() . "满 " . $this->value . " 件";
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

        $siteId = Site::getCurrentSite()->getSiteId();
        $distributionSetting = (new DistributionSetting())->getBaseInfo();
        $calc_valid_condition = $distributionSetting['calc_upgrade_valid_condition'];
        $orderStatus = $calc_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
        $select = $calc_valid_condition == 1 ? 'oi.num - oi.after_sale_over_num' : 'oi.num';
        $query = OrderMembersHistoryModel::query()
            ->from('tbl_order_members_history as omh')
            ->join('tbl_order as o', 'o.id', 'omh.order_id')
            ->join('tbl_order_item as oi', 'oi.order_id', 'o.id')
            ->where('omh.site_id', $siteId)
            ->where('omh.member_id', $memberId)
            ->where('omh.type', 0)
            ->where('omh.level', 1)
            ->whereIn('o.status', $orderStatus)
            ->whereIn('oi.product_id', $this->productIds)
            ->selectRaw("sum({$select}) as total")
            ->first();

        return $query['total'] >= $this->value;
    }
}