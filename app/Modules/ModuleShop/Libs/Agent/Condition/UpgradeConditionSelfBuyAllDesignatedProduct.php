<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use  App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use  App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Site\Site;

/**
 * 自购指定商品升级条件
 */
class UpgradeConditionSelfBuyAllDesignatedProduct extends abstractCondition
{
    protected $name = "自购所有指定商品";

    public function __construct($value, $productId)
    {
        $this->value = $value;
        $this->productid = $productId;
    }

    public function enabled()
    {
        if (!$this->productid || $this->value != 1) {
            return false;
        } else {
            return true;
        }
    }

    public function getTitle()
    {
        return $this->name;
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
        $flag = false;
        if ($this->productid) {
            $productId = array_unique(explode(',', $this->productid));
            $agentUpgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
            $orderStatue = $agentUpgradeSetting->order_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
            $siteId = Site::getCurrentSite()->getSiteId();
            //如果代理升级是在维权期后的，需要确保没有全部售后
            $select = $agentUpgradeSetting->order_valid_condition == 1 ? 'oi.num > oi.after_sale_over_num' : '';
            $count = OrderModel::query()
                ->from('tbl_order as o')
                ->join('tbl_order_item as oi', function($join) use ($productId, $select) {
                    $join->on('oi.order_id', 'o.id')
                        ->whereIn('oi.product_id', $productId);
                    if ($select) {
                        $join->whereRaw($select);
                    }
                })
                ->where('o.member_id', $memberId)
                ->where('o.site_id', $siteId)
                ->whereIn('o.status', $orderStatue)
                ->pluck('oi.product_id')
                ->unique()->count();
            // 如果查询出来的数量等于需要购买的商品数量 则是全部都购买了
            return $count === count($productId);
        }
        return $flag;
    }
}
