<?php
/**
 * 产品sku名称模型
 */
namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class ProductSkuNameModel extends BaseModel
{
    protected $table = 'tbl_product_sku_name';

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
            static::addGlobalScope('tbl_product_sku_name.site_id', function (Builder $builder) use ($siteId) {
                //这里不重用 $siteId 变量就因为的 swoole 环境下，$siteId 对象会不准（因为boot()是静态方法导致）
                $builder->where('site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }

    /**
     * 该sku name下面所有的value记录
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function skuValues()
    {
        return $this->hasMany(ProductSkuValueModel::class, 'sku_name_id');
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
