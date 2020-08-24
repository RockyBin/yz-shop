<?php

namespace YZ\Core\Model;

/**
 * 企业微信的第三方应用配置信息表
 * Class WxWorkAppModel
 * @package YZ\Core\Model
 */
class WxWorkAppModel extends BaseModel
{
    protected $table = 'tbl_wxwork_app';
    protected $primaryKey = 'suite_id';
    protected $keyType = 'string';
    protected $fillable = [
        'suite_id',
        'corp_id',
        'secret',
        'token',
        'aes_key',
        'suite_ticket',
        'ticket_updated_at',
        'auth_callback_domain',
        'is_online'
    ];
}