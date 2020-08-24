<?php
namespace YZ\Core\Model;

/**
 * 站点的支付配置信息表，如站点的微信支付设置，支付宝设置等
 * Class PayConfigModel
 * @package YZ\Core\Model
 */
class PayConfigModel extends BaseModel
{
    protected $table = 'tbl_pay_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'type',
        'wxpay_mchid',
        'wxpay_key',
        'wxpay_cert',
        'wxpay_cert_key',
        'wxpay_service_mode',
        'alipay_appid',
        'alipay_public_key',
        'alipay_private_key'
    ];
}