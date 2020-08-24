<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent\AgentInvite;

use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentInvite\InvitePoster;
use Illuminate\Support\Facades\DB;

class InvitePosterController extends BaseAdminController
{
    /**
     * 获取邀请海报的信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            if($id) $page = new InvitePoster($id);
            else $page = InvitePoster::getDefaultPoster();
            $result = $page->getModel()->toArray();
            $result['modules'] = json_decode($result['modules'], true);
            return makeApiResponseSuccess('ok', ['info' => $result]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除某个邀请海报
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->id;
            if (!is_array($id)) {
                return makeApiResponseFail('传值必须是数组');
            }
            InvitePoster::delete($id);
            DB::commit();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存邀请海报信息
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $all = $request->all();
            $id = $request->get('id');
            if(is_numeric($id)) $page = new InvitePoster($id);
            else $page = InvitePoster::getDefaultPoster();
            $newPaperId = $page->update($all['info']);
            return makeApiResponseSuccess('ok', ['id' => $newPaperId]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
