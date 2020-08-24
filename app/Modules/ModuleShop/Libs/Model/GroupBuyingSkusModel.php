<?php
/**
 * 拼团商品sku模型
 * User: liyaohui
 * Date: 2020/4/3
 * Time: 09:50
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class GroupBuyingSkusModel extends BaseModel
{
    protected $table = 'tbl_group_buying_skus';
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
     * 获取拼团商品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function groupProduct()
    {
        return $this->belongsTo(GroupBuyingProductsModel::class, 'group_product_id', 'id');
    }
}