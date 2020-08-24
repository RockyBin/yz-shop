<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;


/**
 * 微信支付配置类
 * @package App\Modules\ModuleShop\Libs\PayConfig
 */
class WxPayConfig extends abstractPayconfig
{
    //支付配置 微信支付查询相关字段
    public $selectColumn = [
        'site_id',
        'type',
        'wxpay_mchid',
        'wxpay_key',
        'wxpay_cert',
        'wxpay_cert_key',
        'wxpay_offline_entrance',
        'wxpay_online_entrance',
        'wx_qrcode'
    ];
    //支付配置 微信支付插入相关字段
    public $saveColumn = [
        'type',
        'wxpay_mchid',
        'wxpay_key',
        'wxpay_cert',
        'wxpay_cert_key',
        'wxpay_offline_entrance',
        'wxpay_online_entrance',
        'wx_qrcode'
    ];
    //支付配置 微信支付上传所需文件相关字段
    public $uploadColumn = [
        'wxpay_cert',
        'wxpay_cert_key',
        'wx_qrcode'
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
        if ($configType['wxpay'] == 1 || $configType['wxpay_offline']== 1 ) {
            $info = $this->upload($info);
        }
        $this->save($info);
    }
}