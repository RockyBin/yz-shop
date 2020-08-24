<?php
/**
 * 区域代理加盟设置模型
 * User: liyaohui
 * Date: 2020/5/18
 * Time: 16:31
 */

namespace App\Modules\ModuleShop\Libs\Model\AreaAgent;


use YZ\Core\Model\BaseModel;

class AreaAgentApplySettingModel extends BaseModel
{
    protected $table = 'tbl_area_agent_apply_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'status',
        'apply_level',
        'self_level',
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