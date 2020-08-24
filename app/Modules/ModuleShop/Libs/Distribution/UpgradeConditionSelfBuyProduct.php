<?php
/**
 * User: liyaohui
 * Date: 2019/10/23
 * Time: 11:28
 */

namespace App\Modules\ModuleShop\Libs\Distribution;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Site\Site;

class UpgradeConditionSelfBuyProduct extends abstractCondition
{
    protected $name = "自购指定商品";

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
        $query = OrderModel::query()
            ->from('tbl_order as o')
            ->join('tbl_order_item as oi', 'oi.order_id', 'o.id')
            ->where('o.site_id', $siteId)
            ->where('o.member_id', $memberId)
            ->whereIn('o.status', $orderStatus)
            ->whereIn('oi.product_id', $this->productIds)
            ->selectRaw("SUM({$select}) AS total")
            ->first();

        return $query['total'] >= $this->value;
    }
}