<?php
/**
 * 经销商申请设置表
 * User: liyaohui
 * Date: 2019/6/26
 * Time: 14:20
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class DealerApplySettingModel extends BaseModel
{
    protected $table = 'tbl_dealer_apply_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'status',
        'can_apply',
        'can_apply_level',
        'can_invite',
		'can_invite_level',
        'can_invite_setting',
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
        'extend_fields',
        'verify_process'
    ];
}