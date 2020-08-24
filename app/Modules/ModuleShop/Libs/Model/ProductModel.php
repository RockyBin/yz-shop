<?php
/**
 * 产品模型
 */

namespace App\Modules\ModuleShop\Libs\Model;

use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class ProductModel extends BaseModel
{
    // 表名
    protected $table = 'tbl_product';

    public $timestamps = true;

    // 批量赋值的属性
    protected $fillable = ["site_id", "store_id", "type", "status", "name", "keyword", "describe", "detail", "big_images", "small_images","video","video_poster","price", "member_price", "market_price", "point_status", "freight_id", "base_sold_count","sort","sold_count", "warning_inventory", "serial_number", "params", "sell_at", 'supply_price', "sold_out_at", "change_at","after_sale_count","view_perm","buy_perm","buy_limit_status","buy_limit_type","buy_limit_num","min_buy_num","cloud_stock_status","after_sale_setting","supplier_member_id","supplier_price","verify_at", "submit_verify_at","verify_status","verify_reject_reason"];

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
                $builder->where('tbl_product.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }

    /**
     * 关联的分类表 中间表为tbl_product_relation_class
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productClass()
    {
        return $this->belongsToMany(ProductClassModel::class, 'tbl_product_relation_class', 'product_id', 'class_id')->withPivot('site_id');
    }

    /**
     * 关联的标签表 中间表为tbl_product_relation_label
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productLabel()
    {
        return $this->belongsToMany(ProductLabelModel::class, 'tbl_product_relation_label', 'product_id', 'label_id');
    }

    /**
     * 关联的浏览权限表 中间表为tbl_product_relation_perm
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function viewLevels()
    {
        return $this->belongsToMany(MemberLevelModel::class, 'tbl_product_relation_perm', 'product_id', 'level_id')->wherePivot('type','0');
    }

    /**
     * 关联的购买权限表 中间表为tbl_product_relation_perm
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function buyLevels()
    {
        return $this->belongsToMany(MemberLevelModel::class, 'tbl_product_relation_perm', 'product_id', 'level_id')->wherePivot('type','1');
    }

    /**
     * 关联的产品会员价格规则 中间表为tbl_product_skus
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productPriceMemberRule()
    {
        return $this->belongsToMany(ProductPriceRuleModel::class, 'tbl_product_skus', 'product_id', 'member_rule');
    }

    /**
     * 关联的产品分销价格规则 中间表为tbl_product_skus
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productPriceFenxiaoRule()
    {
        return $this->belongsToMany(ProductPriceRuleModel::class, 'tbl_product_skus', 'product_id', 'fenxiao_rule');
    }

    /**
     * 关联的产品订单分红价格规则 中间表为tbl_product_skus
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productPriceAgentOrderCommissionRule()
    {
        return $this->belongsToMany(ProductPriceRuleModel::class, 'tbl_product_skus', 'product_id', 'agent_order_commission_rule');
    }

    /**
     * 关联的产品代理销售奖价格规则 中间表为tbl_product_skus
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productPriceAgentSaleRewardRule()
    {
        return $this->belongsToMany(ProductPriceRuleModel::class, 'tbl_product_skus', 'product_id', 'agent_sale_reward_rule');
    }

	/**
     * 关联的产品区域代理价格规则 中间表为tbl_product_skus
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productPriceAreaAgentRule()
    {
        return $this->belongsToMany(ProductPriceRuleModel::class, 'tbl_product_skus', 'product_id', 'area_agent_rule');
    }

    /**
     * 关联的sku表
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productSkus()
    {
        return $this->hasMany(ProductSkusModel::class, 'product_id');
    }

    /**
     * 关联的sku name表
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productSkuName()
    {
        return $this->hasMany(ProductSkuNameModel::class, 'product_id');
    }

    /**
     * 关联的sku value
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productSkuValue()
    {
        return $this->hasMany(ProductSkuValueModel::class, 'product_id');
    }

    /**
     * 关联的评论表 产品评论
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productComment()
    {
        return $this->hasMany(ProductCommentModel::class, 'product_id');
    }

    /**
     * 关联产品会员价规则
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productMemberRule()
    {
        // product表的 member_rule 关联 tbl_product_price_rule 的id
        return $this->belongsTo(ProductPriceRuleModel::class, 'member_rule');
    }

    /**
     * 关联产品分销价规则
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productFenxiaoRule()
    {
        // product表的 fenxiao_rule 关联 tbl_product_price_rule 的id
        return $this->belongsTo(ProductPriceRuleModel::class, 'fenxiao_rule');
    }

    /**
     * 关联云仓价格规则
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productPriceCloudStockRule()
    {
        return $this->belongsToMany(ProductPriceRuleModel::class, 'tbl_product_skus', 'product_id', 'cloud_stock_rule');
    }

    /**
     * 关联的产品经销商销售奖价格规则 中间表为tbl_product_skus
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productPriceDealerSaleRewardRule()
    {
        return $this->belongsToMany(ProductPriceRuleModel::class, 'tbl_product_skus', 'product_id', 'dealer_sale_reward_rule');
    }

    /**
     * 关联产品所属的运费模板
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productFreight()
    {
        return $this->belongsTo(FreightTemplateModel::class, 'freight_id');
    }

    /**
     * 排序规则
     * @param $query
     * @param string $order 要排序的字段
     * @param string $rule 排序规则
     * @return mixed
     */
    public function scopeWithOrder($query, $order = 'updated_at', $rule = 'desc')
    {
        return $query->orderBy($order, $rule);
    }

    /**
     * 加入了该产品的购物车
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function shoppingCart()
    {
        return $this->hasMany(ShoppingCartModel::class, 'product_id');
    }

    /**
     * 获取该商品所属供应商
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function supplier()
    {
        return $this->belongsTo(SupplierModel::class, 'supplier_member_id', 'member_id');
    }
}
