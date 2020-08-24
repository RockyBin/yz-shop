<?php
/**
 * 代理申请设置表
 * User: liyaohui
 * Date: 2019/6/26
 * Time: 14:20
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class AgentApplySettingModel extends BaseModel
{
    protected $table = 'tbl_agent_apply_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'agent_apply_status',
        'agent_apply_level',
        'company_show',
        'company_require',
        'business_license_show',
        'business_license_require',
        'business_license_file_show',
        'business_license_file_require',
        'idcard_show',
        'idcard_require',
        'idcard_file_show',
        'idcard_file_require',
        'applicant_show',
        'applicant_require',
        'mobile_show',
        'mobile_require',
        'sex_show',
        'sex_require',
        'address_show',
        'address_require',
        'remark_show',
        'remark_require',
        'agreement_show',
        'agreement',
        'extend_fields'
    ];
}