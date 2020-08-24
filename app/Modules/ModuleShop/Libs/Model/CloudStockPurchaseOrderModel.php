<?php
/**
 * 云仓进货订单
 * User: liyaohui
 * Date: 2019/8/23
 * Time: 14:02
 */

namespace App\Modules\ModuleShop\Libs\Model;


use App\Modules\ModuleShop\Libs\Entities\CloudstockPurchaseOrderEntity;
use YZ\Core\Model\BaseModel;

class CloudStockPurchaseOrderModel extends BaseModel
{
    protected $table = 'tbl_cloudstock_purchase_order';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    public function item()
    {
        return $this->hasMany(CloudStockPurchaseOrderItemModel::class, 'order_id');
    }

    public function items()
    {
        return $this->hasMany(CloudStockPurchaseOrderItemModel::class, 'order_id');
    }

    public function hasManyOrderByMemberId(int $memberId): bool
    {
        return $this->newQuery()->where(CloudstockPurchaseOrderEntity::MEMBER_ID, '=', $memberId)->limit(2)->count() > 1 ? true : false;
    }
}