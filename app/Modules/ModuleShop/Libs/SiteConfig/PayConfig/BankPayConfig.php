<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;


/**
 * 微信支付配置类
 * @package App\Modules\ModuleShop\Libs\PayConfig
 */
class BankPayConfig extends abstractPayconfig
{
    //支付配置 银行卡支付查询相关字段
    public $selectColumn = [
        'site_id',
        'type',
        'bank',
        'bank_account',
        'bank_card_name',
        'bank_offline_entrance'
    ];
    //支付配置 银行卡支付查询相关字段
    public $saveColumn = [
        'type',
        'bank',
        'bank_account',
        'bank_card_name',
        'bank_offline_entrance'
    ];

    public function __construct()
    {
        parent::__construct($this->selectColumn, $this->saveColumn);
    }

    /**
     * 编辑设置
     * @param array $info 设置信息，对应 PayConfigModel 的字段信息
     * @throws \Exception
     */
    public function edit(array $info)
    {
        $this->save($info);
    }
}