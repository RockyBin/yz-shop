<?php

namespace YZ\Core\Model;

/**
 * 公众号的配置信息表
 * Class WxConfigModel
 * @package YZ\Core\Model
 */
class WxConfigModel extends BaseModel
{
    protected $table = 'tbl_wx_config';

    protected $fillable = [
        'site_id',
        'name',
        'qrcode',
        'logo',
        'wxid',
        'appid',
        'appsecret',
        'token',
        'type',
        'domain',
        'wx_no',
    ];
}