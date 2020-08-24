<?php
namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Admin;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;

/**
 * 会员证件
 * Class MemberCertController
 * @package App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Admin
 */
class MemberExtendController extends BaseAdminController
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
            $memberId = intval($request->get('member_id'));
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

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $siteId = Site::getCurrentSite()->getSiteId();
            $memberId = intval($request->get('member_id'));
            $isOpenOperationCenter = intval($request->get('is_open_operation_center'));
            if ($memberId) {
                $dataExist = DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                    ->where('site_id', $siteId)
                    ->where('member_id', $memberId)
                    ->count();
                if ($dataExist > 0) {
                    DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                        ->where('site_id', $siteId)
                        ->where('member_id', $memberId)
                        ->update(['is_open_operation_center' => $isOpenOperationCenter]);
                } else {
                    DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                        ->insert([
                            'site_id' => $siteId,
                            'member_id' => $memberId,
                            'is_open_operation_center' => $isOpenOperationCenter
                        ]);
                }
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}