<?php
/**
 * 产品分类模型
 */
namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class ProductClassModel extends BaseModel
{
    protected $table = 'tbl_product_class';
    protected $guarded = [];

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
                $builder->where('tbl_product_class.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }
    /**
     * 获取属于该类的产品列表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productList()
    {
        return $this->belongsToMany(ProductModel::class, 'tbl_product_relation_class', 'class_id', 'product_id')->withPivot('site_id');
    }
}
