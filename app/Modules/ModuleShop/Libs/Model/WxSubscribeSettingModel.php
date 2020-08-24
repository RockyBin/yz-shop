<?php
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class WxSubscribeSettingModel extends BaseModel
{
    protected $table = 'tbl_wx_subscribe_setting';
    protected $fillable = [
        'site_id',
        'home_page_bar',
        'home_page_button',
        'home_page_button_pos',
        'member_center',
        'pay_success',
    ];
    public $timestamps = true;
}