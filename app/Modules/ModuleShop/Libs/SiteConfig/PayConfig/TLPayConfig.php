<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use YZ\Core\Site\Site;

/**
 * 通联支付配置类
 * @package App\Modules\ModuleShop\Libs\PayConfig
 */
class TLPayConfig extends abstractPayconfig
{
    //支付配置 通联支付查询相关字段
    public $selectColumn = [
        'site_id',
        'type',
        'tlpay_orgid',
        'tlpay_appid',
        'tlpay_mchid',
        'tlpay_key',
        'tlpay_online_entrance'
    ];
    //支付配置 通联支付插入相关字段
    public $saveColumn = [
        'type',
        'tlpay_orgid',
        'tlpay_appid',
        'tlpay_mchid',
        'tlpay_key',
        'tlpay_online_entrance'
    ];
    //支付配置 通联支付上传所需文件相关字段
    public $uploadColumn = [

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
		$types = json_decode($info['type'],true);
		$isenable = intval($types['tlpay']) === 1;
		if($isenable && $info['tlpay_orgid'] != Site::getCurrentSite()->getSiteId()){
            throw new \Exception('服务商授权ID不正确，请联系我们');
        }
        $this->save($info);
    }
}