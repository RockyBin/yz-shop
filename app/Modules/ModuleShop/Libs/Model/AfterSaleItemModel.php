<?php
/**
 * 售后的产品记录
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\Model;


use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class AfterSaleItemModel extends BaseModel
{
    protected $table = 'tbl_after_sale_item';
    protected $fillable = ['site_id', 'order_id', 'order_item_id', 'product_id', 'num', 'money', 'refund_freight', 'after_sale_id',"point_money","coupon_money"];

    /**
     * 全局查询作用域  查询时自动添加site_id 为当前站点id的条件
     */
    public static function boot()
    {
        parent::boot();

        $siteId = Site::getCurrentSite()->getSiteId();
        if ($siteId) {
            static::addGlobalScope('site_id', function (Builder $builder) use ($siteId) {
                //这里不重用 $siteId 变量就因为的 swoole 环境下，$siteId 对象会不准（因为boot()是静态方法导致）
                $builder->where('tbl_after_sale_item.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }

    public function afterSale()
    {
        return $this->belongsTo(AfterSaleModel::class, 'after_sale_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItemModel::class, 'order_item_id');
    }

    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }
}