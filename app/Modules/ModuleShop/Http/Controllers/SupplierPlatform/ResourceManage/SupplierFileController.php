<?php
/**
 * 供应商平台文件接口
 * User: liyaohui
 * Date: 2020年07月23日10:44:12
 */

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\ResourceManage;;

use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierFileManage;
use Illuminate\Http\Request;

class SupplierFileController extends BaseSupplierPlatformController
{
    /**
     * @param Request $request
     * @return array
     */
    public function upload(Request $request)
    {
        try{
            $fileManage = new SupplierFileManage();
            $fileManage->upload($request->file('file'), $request->get('folder_id'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try{
            $fileManage = new SupplierFileManage();
            $fileManage->delete($request->get('id'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try{
            $fileManage = new SupplierFileManage();
            $type = $request->get('type');
            if($type == 'image') $type = ["jpg", "png", "gif", "jpeg"];
            else $type = explode(',',$type);
            $keyWord = $request->get('keyword');
            $pageSize = $request->get('page_size');
            $folderId = $request->get('folder_id');
            $sortKey = $request->get('sort_key');
            $sortDirection = $request->get('sort_direction');
            if (!$pageSize) $pageSize = 20;
            $page = $request->get('page');
            if (!$page) $page = 1;
            $params = ['page_size' => $pageSize, 'page' => $page, 'type' => $type, 'keyword' => $keyWord, 'folder_id' => $folderId];
            $total = $fileManage->getList(array_merge($params, ['return_total_record' => 1]));
            $pageCount = ceil($total / $pageSize);
            $sortRule = [$sortKey => $sortDirection];
            $list = $fileManage->getList($params, $sortRule);
            $ret = ["total" => $total, "page_size" => $pageSize, "current" => $page, "last_page" => $pageCount, "list" => $list];
            return makeApiResponse(200, 'ok', $ret);
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }
}