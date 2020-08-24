<?php

namespace YZ\Core\Model;

/**
 * 用户的微信小程序配置信息表
 * Class WxWorkModel
 * @package YZ\Core\Model
 */
class WxAppModel extends BaseModel
{
    protected $table = 'tbl_wxapp';
    protected $fillable = [
        'site_id',
        'appid',
        'appsecret',
        'name',
        'qrcode',
        'domain',
        'head_bgcolor',
        'head_fontcolor',
        'created_at'
    ];
}