<?php

namespace YZ\Core\Model;

/**
 * 短信模板消息信息表
 * Class TemplateMsgModel
 * @package YZ\Core\Model
 */
class SmsTemplateModel extends BaseModel
{
    protected $table = 'tbl_sms_template';
    protected $fillable = [
        'site_id',
        'type',
        'status',
        'content',
        'created_at',
        'updated_at'
    ];
}