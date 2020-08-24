<?php
/**
 * 防伪码接口
 * User: liyaohui
 * Date: 2019/11/1
 * Time: 15:15
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\ProductSecurity;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\ProductSecurity\SecurityCode;

class SecurityCodeController extends BaseFrontController
{
    public function queryCode($code)
    {
        try {
            $data = (new SecurityCode())->queryCode($code);
            if ($data === false) {
                return makeApiResponse(404, '很抱歉！您所购买的商品并非本公司正品，请支持正版购买！如需帮助，请联系客服！');
            } else {
                return makeApiResponseSuccess('ok', $data);
            }
        } catch (\Exception $e) {
            return makeApiResponseFail($e->getMessage());
        }
    }
}