<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class AgentParentsModel extends BaseModel
{
    protected $table = 'tbl_agent_parents';
    protected $fillable = [
        'site_id',
        'member_id',
        'agent_level',
        'parent_id',
        'level',
    ];
}