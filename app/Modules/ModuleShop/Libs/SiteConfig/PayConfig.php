<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig;

use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig\TLPayConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig\WxPayConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig\AliPayConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig\BankPayConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig\OfflinePayConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig\AllPayConfig;
use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Site\Site;

/**
 * 支付设置类
 * Class OrderConfig
 * @package App\Modules\ModuleShop\Libs\PayConfig
 */
class PayConfig
{
    private $_class;

    /**
     * 构造方法
     * $type 1:微信 2:支付宝 3:银行卡配置 4:线下支付
     * @throws \Exception
     */
    public function __construct(int $pay_config_type = null)
    {
        switch (true) {
            case $pay_config_type == Constants::PayConfigType_WxPay;
                $this->_class = new WxPayConfig();
                break;
            case $pay_config_type == Constants::PayConfigType_AliPay:
                $this->_class = new AliPayConfig();
                break;
            case $pay_config_type == Constants::PayConfigType_TongLian:
                $this->_class = new TLPayConfig();
                break;
            case $pay_config_type == Constants::PayConfigType_BankPay:
                $this->_class = new BankPayConfig();
                break;
            case $pay_config_type == Constants::PayConfigType_OfflinePay;
                $this->_class = new OfflinePayConfig();
                break;
            default:
                $this->_class = new AllPayConfig();
        }
    }

    /**
     * 编辑设置
     * @param array $info 设置信息，对应 PayConfigModel 的字段信息
     * @throws \Exception
     */
    public function edit(array $info)
    {
        $this->_class->edit($info);
    }

    /**
     * 获取设置信息
     * @param bool $format 是否格式化数据（JSON数据格式转为数组）
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getInfo($format = false)
    {
        $model = $this->_class->getModel();

        if ($format) {
            if ($model->type) {
                $model->type = json_decode($model->type, true);
            } else {
                $model->type = [];
            }
            if ($model->wxpay_offline_entrance) {
                $model->wxpay_offline_entrance = json_decode($model->wxpay_offline_entrance, true);
            } else {
                $model->wxpay_offline_entrance = [];
            }
            if ($model->alipay_offline_entrance) {
                $model->alipay_offline_entrance = json_decode($model->alipay_offline_entrance, true);
            } else {
                $model->alipay_offline_entrance = [];
            }
            if ($model->bank_offline_entrance) {
                $model->bank_offline_entrance = json_decode($model->bank_offline_entrance, true);
            } else {
                $model->bank_offline_entrance = [];
            }
            if ($model->wxpay_online_entrance) {
                $model->wxpay_online_entrance = json_decode($model->wxpay_online_entrance, true);
            } else {
                $model->wxpay_online_entrance = [];
            }
            if ($model->alipay_online_entrance) {
                $model->alipay_online_entrance = json_decode($model->alipay_online_entrance, true);
            } else {
                $model->alipay_online_entrance = [];
            }
            if ($model->tlpay_online_entrance) {
                $model->tlpay_online_entrance = json_decode($model->tlpay_online_entrance, true);
            } else {
                $model->tlpay_online_entrance = [];
            }
        }
        return $model;
    }
}