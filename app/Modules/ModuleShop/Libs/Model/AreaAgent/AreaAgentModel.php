<?php
/**
 * 代理表
 * User: liyaohui
 * Date: 2019/6/27
 * Time: 14:55
 */

namespace App\Modules\ModuleShop\Libs\Model\AreaAgent;


use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

class AreaAgentModel extends BaseModel
{
    protected $table = 'tbl_area_agent';
    protected $guarded = ['member_id', 'site_id'];

    public function member()
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }
}

