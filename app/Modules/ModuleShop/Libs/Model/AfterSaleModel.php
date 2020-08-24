<?php
/**
 * 售后主表模型
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class AfterSaleModel extends BaseModel
{
    protected $table = 'tbl_after_sale';
    protected $fillable = ["id","site_id","order_id","status","receive_status","product_quantity","total_money","type","reason","refuse_msg","content","refund_type","return_logistics_company","return_logistics_name","return_logistics_no", "total_refund_freight","member_id","is_all_after_sale","images","finance_id"];
    public $timestamps = true;
    protected $keyType = 'string';
    public $incrementing = false; // 主键不是自增 不加这个的话 主键为字符串的时候 新建的数据无法关联

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
                $builder->where('tbl_after_sale.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }

    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    public function items()
    {
        return $this->hasMany(AfterSaleItemModel::class, 'after_sale_id');
    }
}