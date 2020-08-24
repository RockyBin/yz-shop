<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 会员配置模块
 * Class MemberConfigModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class MemberConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_member_config';
    protected $primaryKey  = 'site_id';
    public $incrementing = false;
    protected $fillable = [
        'level_upgrade_period'
    ];
}