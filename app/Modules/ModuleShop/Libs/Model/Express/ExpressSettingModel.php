<?php
/**
 * 快递设置模型
 * User: liyaohui
 * Date: 2020/7/8
 * Time: 15:55
 */

namespace App\Modules\ModuleShop\Libs\Model\Express;


use YZ\Core\Model\BaseModel;

class ExpressSettingModel extends BaseModel
{
    protected $table = 'tbl_express_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'status',
        'app_key',
        'app_secret',
        'prov',
        'city',
        'district',
        'address',
        'address_text',
        'redirect_domain',
        'sender_tel',
        'sender_name',
        'expires_at'
    ];
}