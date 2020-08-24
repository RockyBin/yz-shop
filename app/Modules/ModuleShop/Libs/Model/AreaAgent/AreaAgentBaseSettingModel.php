<?php
/**
 * 区域代理基础设置模型
 * User: liyaohui
 * Date: 2020/5/18
 * Time: 16:17
 */

namespace App\Modules\ModuleShop\Libs\Model\AreaAgent;


use YZ\Core\Model\BaseModel;

class AreaAgentBaseSettingModel extends BaseModel
{
    protected $table = 'tbl_area_agent_base_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'status',
        'commision_grant_time',
        'commision_type',
        'internal_purchase',
        'internal_purchase_performance'
    ];
}