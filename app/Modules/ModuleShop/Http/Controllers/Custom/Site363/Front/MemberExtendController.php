<?php
namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Front;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;

class MemberExtendController extends BaseController
{
    /**
     * 获取数据
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $siteId = Site::getCurrentSite()->getSiteId();
            $memberId = $this->memberId;
            $data = [
                'is_open_operation_center' => 0,
            ];
            if ($memberId) {
                $row = DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                    ->where('site_id', $siteId)
                    ->where('member_id', $memberId)
                    ->first();
                if($row) $data = $row;
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}