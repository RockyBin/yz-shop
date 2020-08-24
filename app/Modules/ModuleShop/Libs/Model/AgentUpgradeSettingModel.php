<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 *  代理升级设置
 * Class AgentUpgradeSettingModel
 * @package App\Modules\Model
 */
class AgentUpgradeSettingModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_agent_upgrade_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'status',
        'order_valid_condition',
        'auto_upgrade'
    ];

}