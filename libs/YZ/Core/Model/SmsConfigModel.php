<?php

namespace YZ\Core\Model;

/**
 * 短信设置表
 * Class SmsConfigModel
 * @package YZ\Core\Model
 */
class SmsConfigModel extends BaseModel
{
    protected $table = 'tbl_sms_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'appid',
        'appkey',
        'sign',
        'mobile'
    ];
}