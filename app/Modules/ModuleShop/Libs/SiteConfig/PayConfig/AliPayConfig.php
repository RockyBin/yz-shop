<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;


/**
 * 微信支付配置类
 * @package App\Modules\ModuleShop\Libs\PayConfig
 */
class AliPayConfig extends abstractPayconfig
{
    //支付配置 支付宝支付查询相关字段
    public $selectColumn = [
        'site_id',
        'type',
        'alipay_appid',
        'alipay_public_key',
        'alipay_private_key',
        'alipay_use_sandbox',
        'alipay_offline_entrance',
        'alipay_online_entrance',
        'alipay_offline_pay_type',
        'alipay_account',
        'alipay_name',
        'alipay_qrcode'
    ];
    //支付配置 支付宝支付插入相关字段
    public $saveColumn = [
        'type',
        'alipay_appid',
        'alipay_public_key',
        'alipay_private_key',
        'alipay_use_sandbox',
        'alipay_offline_entrance',
        'alipay_online_entrance',
        'alipay_offline_pay_type',
        'alipay_account',
        'alipay_name',
        'alipay_qrcode'
    ];
    //支付配置 支付宝支付上传所需文件相关字段
    public $uploadColumn = [
        'alipay_qrcode',
    ];

    public function __construct()
    {
        parent::__construct($this->selectColumn, $this->saveColumn,$this->uploadColumn);
    }

    /**
     * 编辑设置
     * @param array $info 设置信息，对应 PayConfigModel 的字段信息
     * @throws \Exception
     */
    public function edit(array $info)
    {
        $configType = json_decode($info['type'], true);
        if ($configType['alipay_offline'] == 1) {
            $info = $this->upload($info);
        }
        $this->save($info);
    }
}