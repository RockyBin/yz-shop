<?php
/**
 * 防伪码后台接口
 * User: liyaohui
 * Date: 2019/11/1
 * Time: 16:59
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\ProductSecurity;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\ProductSecurity\SecurityCode;
use Illuminate\Http\Request;

class SecurityCodeController extends BaseAdminController
{
    /**
     * 获取防伪码列表
     * @param Request $request
     * @return array
     */
    public function getCodeList(Request $request)
    {
        try {
            $keyword = $request->input('keyword', '');
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $list = (new SecurityCode())->getCodeList(['keyword' => $keyword], $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseFail($e->getMessage());
        }
    }

    /**
     * 删除防伪码
     * @param Request $request
     * @return array
     */
    public function deleteCode(Request $request)
    {
        try {
            $codeId = $request->input('code_id', 0);
            (new SecurityCode())->deleteCode($codeId);
            return makeApiResponseSuccess('ok', []);
        } catch (\Exception $e) {
            return makeApiResponseFail($e->getMessage());
        }
    }

    /**
     * 导出防伪码
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportCodeList(Request $request)
    {
        try {
            $batchCode = $request->input('batch_code', '');
            if (!$batchCode) {
                return makeApiResponse(400, '请选择要导出的批次');
            }
            return (new SecurityCode())->exportCodeList($batchCode);
        } catch (\Exception $e) {
            return makeApiResponseFail($e->getMessage());
        }
    }
}