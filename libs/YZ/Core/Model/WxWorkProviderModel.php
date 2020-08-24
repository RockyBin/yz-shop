<?php

namespace YZ\Core\Model;

/**
 * 企业微信服务商的配置信息表
 * Class WxWorkProviderModel
 * @package YZ\Core\Model
 */
class WxWorkProviderModel extends BaseModel
{
    protected $table = 'tbl_wxwork_provider';
    protected $primaryKey = 'corp_id';
    protected $keyType = 'string';
    protected $fillable = [
        'corp_id',
        'secret',
        'token',
        'aes_key',
    ];
}