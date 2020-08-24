<?php
/**
 * 产品标签模型
 */
namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Model;

class ProductLabelModel extends Model
{
    protected $table = 'tbl_product_label';

    protected $guarded = [];

    /**
     * 获取属于该标签的所有产品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productList()
    {
        return $this->belongsToMany(ProductModel::class, 'tbl_product_relation_label', 'label_id', 'product_id');
    }
}
