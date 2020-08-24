<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Live;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Live\Chat;
use App\Modules\ModuleShop\Libs\Model\LiveChatModel;
use Illuminate\Http\Request;

class ChatController extends BaseFrontController
{
    /**
     * 记录聊天记录
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            if (!$request->client_id || !$request->live_id) {
                return makeApiResponse(520, '数据异常：client_id 和 live_id 不能不空');
            }
            $chat = new Chat($request->live_id);
            $chat->add($request->client_id, $request->member_id, $request->nickname, $request->headurl,$request->content,$request->link);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 列出最新的N条聊天记录
     * @param Request $request
     * @return array
     */
    public function getNewestList(Request $request)
    {
        try {
            if (!$request->live_id) {
                return makeApiResponse(520, '数据异常：client_id 和 live_id 不能不空');
            }
            $num = $request->num ? $request->num : 20;
            $list = LiveChatModel::query()->where(['site_id' => getCurrentSiteId(),'live_id' => $request->live_id])
                ->orderByDesc('id')->limit($num)->get();
            foreach ($list as &$item){
                if (preg_match('/^(\[\{)/',$item->content)) $item->content = json_decode($item->content,true);
            }
            unset($item);
            return makeApiResponseSuccess('成功',['list' => $list]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}