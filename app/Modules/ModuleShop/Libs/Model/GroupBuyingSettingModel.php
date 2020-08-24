<?php
/**
 * 拼团设置模型
 * User: liyaohui
 * Date: 2020/4/2
 * Time: 16:20
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class GroupBuyingSettingModel extends BaseModel
{
    protected $table = 'tbl_group_buying_setting';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    /**
     * 获取该活动的所有团
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function group()
    {
        return $this->hasMany(GroupBuyingModel::class, 'group_buying_setting_id', 'id');
    }

    /**
     * 活动商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(GroupBuyingProductsModel::class, 'group_buying_setting_id', 'id');
    }

    /**
     * 活动商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function skus()
    {
        return $this->hasMany(GroupBuyingSkusModel::class, 'group_buying_setting_id', 'id');
    }
}