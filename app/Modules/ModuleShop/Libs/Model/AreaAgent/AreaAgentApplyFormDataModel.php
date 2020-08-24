<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2020/5/25
 * Time: 17:02
 */

namespace App\Modules\ModuleShop\Libs\Model\AreaAgent;


use YZ\Core\Model\BaseModel;

class AreaAgentApplyFormDataModel extends BaseModel
{
    protected $table = 'tbl_area_agent_apply_form_data';
    protected $fillable = [
        'site_id',
        'member_id',
        'company',
        'business_license',
        'business_license_file',
        'business_type',
        'idcard',
        'idcard_file',
        'applicant',
        'mobile',
        'sex',
        'address',
        'remark',
        'extend_fields'
    ];
}