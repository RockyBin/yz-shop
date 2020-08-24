<?php
/**
 * 伞下业绩统计
 * User: liyaohui
 * Date: 2020/5/21
 * Time: 11:35
 */

namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site1696\Admin;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Custom\Site1696\AdminMember;
use Illuminate\Http\Request;

class MemberController extends BaseAdminController
{
    public function getSubMemberOrderMoneyList(Request $request)
    {
        try {
            $list = AdminMember::getSubMemberOrderMoneyList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}