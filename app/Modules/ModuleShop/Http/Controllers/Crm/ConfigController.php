<?php
namespace App\Modules\ModuleShop\Http\Controllers\Crm;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class ConfigController extends BaseController
{
    /**
     * 获取基础公共配置信息
     * @return array
     */
    public function getConfig(Request $request)
    {
        try {
            return makeApiResponseSuccess('ok', [
                'socketConfig' => ['username' => 'test','password' => 'test']
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}