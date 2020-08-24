<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Member;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

/**
 * 会员标签Controller
 * Class MemberLevelController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class MemberLabelController extends BaseAdminController
{
    private $memberConfig;

    /**
     * 初始化
     * MemberConfigController constructor.
     */
    public function __construct()
    {
        $this->memberLabel = new \App\Modules\ModuleShop\Libs\Member\MemberLabel();
    }

    public function edit(Request $request)
    {
        try {
            $params = $request->toArray();
            $this->memberLabel->edit($params);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }

    }

    public function getList(Request $request)
    {
        try {
            $list = $this->memberLabel->getList($request->toArray(),$request->page,$request->page_size);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function delete(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('请输入ID');
            }
            $this->memberLabel->delete($request->id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function check(Request $request){
        if (!$request->id) {
            return makeApiResponseFail('请输入ID');
        }
        $count = $this->memberLabel->check($request->id);
        return makeApiResponseSuccess('ok',$count);
    }

    public function sort(Request $request){
        try {
            $this->memberLabel->sort($request->label);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}