<?php
/**
 * 云仓进货单的购物车模型
 */

namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

class CloudStockShopCartModel extends BaseModel
{
    protected $table = 'tbl_cloudstock_shop_cart';

    public $timestamps = true;

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
                $builder->where('tbl_cloudstock_shop_cart.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }

    /**
     * 该购物车所属会员
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }

    /**
     * 购物车内的产品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}