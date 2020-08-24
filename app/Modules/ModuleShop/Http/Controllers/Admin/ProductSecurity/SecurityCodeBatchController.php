<?php
/**
 * 防伪码批次后台接口
 * User: liyaohui
 * Date: 2019/11/1
 * Time: 17:38
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\ProductSecurity;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\ProductSecurity\SecurityCodeBatch;
use Illuminate\Http\Request;

class SecurityCodeBatchController extends BaseAdminController
{
    /**
     * 新增防伪码批次
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            $batchCount = $request->input('batch_count', 0);
            $productId = $request->input('product_id', 0);
            (new SecurityCodeBatch())->add(['batch_count' => $batchCount, 'product_id' => $productId]);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseFail($e->getMessage());
        }
    }

    /**
     * 获取防伪码数量
     * @param Request $request
     * @return array
     */
    public function getBatchList(Request $request)
    {
        try {
            $params = [
                'keyword' => $request->input('keyword', ''),
                'created_at_start' => $request->input('created_at_start', ''),
                'created_at_end' => $request->input('created_at_end', '')
            ];
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $list = (new SecurityCodeBatch())->getBatchList($params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseFail($e->getMessage());
        }
    }

    /**
     * 删除批次
     * @param Request $request
     * @return array
     */
    public function deleteBatch(Request $request)
    {
        try {
            $batchId = $request->input('batch_id', 0);
            $del = (new SecurityCodeBatch())->deleteBatch($batchId);
            if ($del) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '删除失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseFail($e->getMessage());
        }
    }
}