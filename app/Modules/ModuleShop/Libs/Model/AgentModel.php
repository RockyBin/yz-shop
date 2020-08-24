<?php
/**
 * 代理表
 * User: liyaohui
 * Date: 2019/6/27
 * Time: 14:55
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

class AgentModel extends BaseModel
{
    protected $table = 'tbl_agent';
    protected $primaryKey = 'member_id';
    protected $guarded = ['member_id', 'site_id'];
    public $incrementing = false;

    public function member()
    {
        return $this->belongsTo(MemberModel::class, $this->primaryKey);
    }
}

