<?php
/**
 * 拼团逻辑 单个团
 * User: liyaohui
 * Date: 2020/4/6
 * Time: 15:04
*/

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class GroupBuyingModel extends BaseModel
{
    protected $table = 'tbl_group_buying';
    protected $primaryKey = 'id';
    public $incrementing = true;

    /**
     * 所属活动设置
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function setting()
    {
        return $this->belongsTo(GroupBuyingSettingModel::class, 'group_buying_setting_id', 'id');
    }
}