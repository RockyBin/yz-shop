<?php
/**
 * 订单的额外优惠记录
 * User: liyaohui
 * Date: 2020/4/15
 * Time: 11:44
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class OrderItemDiscountModel extends BaseModel
{
    protected $table = 'tbl_order_item_discount';
    protected $primaryKey = 'id';
    protected $fillable = [
        'site_id',
        'item_id',
        'order_id',
        'discount_price'
    ];
}