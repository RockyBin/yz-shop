<?php
/**
 * 产品sku的value模型
 */
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;
use Illuminate\Database\Eloquent\Builder;

class ProductSkuValueModel extends BaseModel
{
    protected $table = 'tbl_product_sku_value';
    // 不可被批量赋值的属性
    protected $guarded = [];
    public $timestamps = true;
    /**
     * 所有会被触发的关联。
     *
     * @var array
     */
    protected $touches = ['product'];
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
                $builder->where('tbl_product_sku_value.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }
    /**
     * 该sku value所属的name
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function skuName()
    {
        return $this->belongsTo(ProductSkusModel::class, 'sku_name_id');
    }

    /**
     * 所属的产品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}
