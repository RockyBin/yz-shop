<?php
namespace App\Modules\ModuleShop\Http\Controllers\Crm;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class ErrorController extends BaseController
{
    /**
     * 记录前端小程序提交的错误信息
     * @return array
     */
    public function report(Request $request)
    {
        try {
            return makeApiResponseSuccess('ok', $request->all());
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}