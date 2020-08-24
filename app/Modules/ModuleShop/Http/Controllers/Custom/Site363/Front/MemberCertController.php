<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Front;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 证件
 * Class MemberCertController
 * @package App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Front
 */
class MemberCertController extends BaseController
{
    /**
     * 获取数据
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $siteId = $this->siteId;
            $memberId = $this->memberId;
            $data = [
                'cert' => '',
            ];
            if ($memberId) {
                $data = DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                    ->where('site_id', $siteId)
                    ->where('member_id', $memberId)
                    ->first();
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}