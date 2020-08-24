<?php
/**
 * 获取模块信息
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/3/22
 * Time: 11:04
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\UI;

use App\Modules\ModuleShop\Libs\UI\NavMobi;
use Illuminate\Http\Request;

class ModuleController
{
    /**
     * 获取站点的底部导航
     * @return array
     */
    public function getMobileNavInfo(Request $request){
        try {
            $nav = NavMobi::getDefaultNav($request->get('device_type'))->getModel();
            if ($nav) {
                $nav['items'] = json_decode($nav['items'], true);
            }
            return makeApiResponseSuccess('ok',['nav_info' => $nav]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}