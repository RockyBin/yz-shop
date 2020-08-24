<?php
/**
 * 经销商表
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

class DealerModel extends BaseModel
{
    protected $table = 'tbl_dealer';
    protected $primaryKey = 'member_id';
    protected $guarded = ['member_id', 'site_id'];
    public $incrementing = false;

    public function member()
    {
        return $this->belongsTo(MemberModel::class, $this->primaryKey);
    }

    /**
     * 经销商等级
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dealerLevel()
    {
        return $this->belongsTo(DealerLevelModel::class, 'id', 'dealer_apply_level');
    }
}

