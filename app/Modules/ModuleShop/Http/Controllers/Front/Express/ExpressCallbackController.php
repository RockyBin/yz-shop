<?php
/**
 * 快递回调接口
 * User: liyaohui
 * Date: 2020/7/9
 * Time: 16:40
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Express;


use App\Modules\ModuleShop\Libs\Express\ExpressHelper;
use App\Modules\ModuleShop\Libs\Express\ExpressSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpressCallbackController extends Controller
{
    /**
     * @param Request $request
     * @return array|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function callback(Request $request)
    {
        try {
            $setting = new ExpressSetting();
            $callback = ExpressHelper::callbackHandle($request->all(), $setting->getModel());
            if (isset($callback['type']) && $callback['type'] == 1) {
                return view('moduleshop::Express/expressAuthorize', $callback);
            }
            return $callback;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}