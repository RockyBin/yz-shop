<?php
/**
 * Created by Aison.
 */

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
class MemberCertController extends BaseAdminController
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
            // 证书
            if ($request->hasFile('cert_data') && $memberId) {
                $upload_file_name = time() . randString(4);
                $upload_file_path = Site::getSiteComdataDir('', true) . '/member/';
                $upload_handle = new FileUpload($request->file('cert_data'), $upload_file_path, $upload_file_name);
                $upload_handle->reduceImageSize(800);

                $certUrl = '/member/' . $upload_handle->getFullFileName();

                $dataExist = DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                    ->where('site_id', $siteId)
                    ->where('member_id', $memberId)
                    ->count();
                if ($dataExist > 0) {
                    DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                        ->where('site_id', $siteId)
                        ->where('member_id', $memberId)
                        ->update(['cert' => $certUrl]);
                } else {
                    DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                        ->insert([
                            'site_id' => $siteId,
                            'member_id' => $memberId,
                            'cert' => $certUrl,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                }
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除证书
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $siteId = Site::getCurrentSite()->getSiteId();
            $memberId = intval($request->get('member_id'));
            if ($memberId) {
                DB::connection('mysql_custom')->query()->from('tbl_363_member_extend')
                    ->where('site_id', $siteId)
                    ->where('member_id', $memberId)
                    ->delete();
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}