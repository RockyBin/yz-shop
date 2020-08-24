<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Admin;

use App\Modules\ModuleShop\Http\Controllers\Admin\Url\UrlManageController as BaseAdminController;

/**
 * 继承原有接口
 * Class UrlManageController
 * @package App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Admin
 */
class UrlManageController extends BaseAdminController
{
    /**
     * 获取所有静态的链接
     * @return array
     */
    public function getStaticUrl()
    {
        try {
            $return = parent::getStaticUrl();
            if (intval($return['code']) == 200) {
                // 加上我的证书的连接选择
                $return['data']['static_url'][] = [
                    'name' => '我的证书',
                    'url' => '#/member/member-cert',
                    'type' => 'member_cert'
                ];
            }
            return makeApiResponse($return['code'], $return['msg'], $return['data']);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}