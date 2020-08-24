<?php

namespace App\Modules\ModuleShop\Libs\Crm;

use EasyWeChat\Factory;

/**
 * 微信小程序类
 * Class Auth
 * @package App\Modules\ModuleShop\Libs\Crm
 */
class WxApp
{
    /**
     * 获取小程序 app 实例
     * @return \EasyWeChat\MiniProgram\Application
     */
    public static function getInstance()
    {
        $config = [
            'app_id' => config('app.CRM_APP_ID'),
            'secret' => config('app.CRM_APP_SECRET'),

            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',
            'log' => [
                'default' => 'prod', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => storage_path() . '/logs/weixin/wxapp-crm.log',
                        'level' => 'info',
                        'days' => 30
                    ],
                ],
            ],
        ];

        $app = Factory::miniProgram($config);
        return $app;
    }
}