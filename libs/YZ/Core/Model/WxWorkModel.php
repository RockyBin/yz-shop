<?php

namespace YZ\Core\Model;

/**
 * 用户的企业微信配置信息表
 * Class WxWorkModel
 * @package YZ\Core\Model
 */
class WxWorkModel extends BaseModel
{
    protected $table = 'tbl_wxwork';
    protected $fillable = [
        'site_id',
        'corp_id',
        'mode',
        'agent_id',
        'secret',
        'token',
        'aes_key',
        'domain',
        'suite_id',
        'status',
        'auth_at',
        'unauth_at',
        'auth_code',
    ];
}