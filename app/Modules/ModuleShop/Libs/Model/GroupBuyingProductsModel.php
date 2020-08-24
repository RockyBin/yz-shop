<?php
/**
 * 拼团商品模型
 * User: liyaohui
 * Date: 2020/4/2
 * Time: 16:25
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class GroupBuyingProductsModel extends BaseModel
{
    protected $table = 'tbl_group_buying_products';
    protected $primaryKey = 'id';
    public $incrementing = true;

    /**
     * 获取拼团的具体设置
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function setting()
    {
        return $this->belongsTo(GroupBuyingSettingModel::class, 'group_buying_setting_id', 'id');
    }

    /**
     * 关联的sku表
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productSkus()
    {
        return $this->hasMany(GroupBuyingSkusModel::class, 'group_product_id');
    }

    /**
     * 关联的sku name表
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productSkuName()
    {
        return $this->hasMany(ProductSkuNameModel::class, 'product_id','master_product_id');
    }

    /**
     * 关联的sku value
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productSkuValue()
    {
        return $this->hasMany(ProductSkuValueModel::class, 'product_id','master_product_id');
    }

    /**
     * 关联的分类表 中间表为tbl_product_relation_class
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productClass()
    {
        return $this->belongsToMany(ProductClassModel::class, 'tbl_product_relation_class', 'product_id', 'class_id','master_product_id')->withPivot('site_id');
    }
}