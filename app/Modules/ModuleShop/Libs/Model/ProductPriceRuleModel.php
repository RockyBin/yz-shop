<?php
/**
 * 产品算价规则模型 会员价 分销规则
 */
namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class ProductPriceRuleModel extends BaseModel
{
    protected $table = 'tbl_product_price_rule';
    public $timestamps = true;
    // 不可被批量赋值的属性
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
                $builder->where('tbl_product_price_rule.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }
}
