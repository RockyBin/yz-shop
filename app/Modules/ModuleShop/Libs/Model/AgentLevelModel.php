<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 *  代理升级设置
 * Class AgentUpgradeSettingModel
 * @package App\Modules\Model
 */
class AgentLevelModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_agent_level';
    protected $fillable = [
        'level',
        'apply_condition',
        'upgrade_condition'
    ];

}