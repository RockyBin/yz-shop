<?php
/**
 * 自购云仓商品
 * User: liyaohui
 * Date: 2019/11/30
 * Time: 11:26
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;

class ConditionSelfBuyProduct extends abstractCondition
{
    protected $name = '自购云仓商品满';
    protected $unit = '件';

    public function __construct($value, array $productIds)
    {
        $this->value = $value;
        $this->productIds = $productIds;
    }

    /**
     * 判断某经销商是否满足此条件
     * @param int $memberId 经销商会员id
     * @param array $params 额外的参数
     * @return false
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->productIds || !is_array($this->productIds)) {
            return false;
        }
        $productIds = $this->productIds;
        $count = CloudStockPurchaseOrderModel::query()
            ->from('tbl_cloudstock_purchase_order as o')
            ->leftJoin('tbl_cloudstock_purchase_order_item as oi', function ($query) use ($productIds) {
                $query->on('o.id', 'oi.order_id')
                    ->whereIn('oi.product_id', $productIds);
            })
            ->where('o.site_id', getCurrentSiteId())
            ->where('o.member_id', $memberId)
            ->whereIn('o.status', Constants::getCloudStockPurchaseOrderPayStatus())
            ->selectRaw('sum(oi.num) as product_count')
            ->value('product_count');
        $count = $count ?: 0;
        return $count >= $this->value;
    }
}