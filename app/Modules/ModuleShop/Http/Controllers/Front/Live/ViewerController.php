<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Live;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Live\Viewer;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use YZ\Core\Common\WebSocket;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Auth;

class ViewerController extends BaseFrontController
{
    /**
     * 增加观看人数
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            if (!$request->client_id) {
                return makeApiResponse(520, '数据异常：client_id 不能不空');
            }
            $viewer = new Viewer($request->live_id);
            $viewer->add($request->client_id, $request->group_id, $request->member_id, $request->nickname, $request->headurl);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 减少观看人数，此接口由 websocket 服务器在用户断线时自动调用
     * @param Request $request
     * @return array
     */
    public function reduce(Request $request)
    {
        /* 收到数据格式如下：
         * array (
              'clientid' => 'b34d29f6-2b01-40dc-88b5-0a74a0c34501',
              'groupid' => 'shop_live_123',
              'info' =>
              array (
                'client_id' => 'b34d29f6-2b01-40dc-88b5-0a74a0c34501',
                'group_id' => 'shop_live_123',
                'member_id' => '69',
                'name' => '会员_69',
                'headurl' => NULL,
                'disconnect_notice_url' => 'http://llshop2.72dns.com/shop/front/live/viewer/reduce',
              ),
            )
         */
        try {
            $params = $request->all();
            $info = $params['info'];
            $viewer = new Viewer($info['live_id']);
            $viewer->reduce($info);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}