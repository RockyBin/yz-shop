<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberAddressModel;

/**
 * 订单数据库模型
 */
class OrderModel extends BaseModel {

    protected $table = 'tbl_order';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
    ];

    public function items() {
        return $this->hasMany('App\Modules\ModuleShop\Libs\Model\OrderItemModel','order_id');
    }

    /**
     * 获取该订单的地址模型
     * @return \LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection
     */
    public function address()
    {
        return MemberAddressModel::find($this->address_id);
    }

    /**
     * 订单售后记录
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function afterSale()
    {
        return $this->hasMany(AfterSaleModel::class, 'order_id');
    }

    public function afterSaleItems()
    {
        return $this->hasMany(AfterSaleItemModel::class, 'order_id');
    }
}