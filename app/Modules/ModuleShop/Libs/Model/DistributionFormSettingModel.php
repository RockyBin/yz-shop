<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 分销商申请表单设置数据库模型
 */
class DistributionFormSettingModel extends BaseModel {

    protected $table = 'tbl_distribution_form_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
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
        'extend_fields'];
}
