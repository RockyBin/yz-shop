<?php
/**
 * 供应商平台文件夹接口
 * User: liyaohui
 * Date: 2020年07月23日
 */

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\ResourceManage;;

use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierFolderManage;
use Illuminate\Http\Request;

class SupplierFolderController extends BaseSupplierPlatformController
{
    /**
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try{
            $folderManage = new SupplierFolderManage();
            $folderManage->add($request->get('name'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function rename(Request $request)
    {
        try{
            $folderManage = new SupplierFolderManage();
            $folderManage->rename($request->get('id'),$request->get('name'));
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
            $folderManage = new SupplierFolderManage();
            $folderManage->delete($request->get('id'));
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
            $folderManage = new SupplierFolderManage();
            $type = $request->get('type');
            $keyWord = $request->get('keyword');
            $pageSize = $request->get('page_size');
            $parentId = $request->get('parent_id');
            $sortKey = $request->get('sort_key');
            $sortDirection = $request->get('sort_direction');
            if (!$pageSize) $pageSize = 100;
            $page = $request->get('page');
            if (!$page) $page = 1;
            $params = ['page_size' => $pageSize, 'page' => $page, 'type' => $type, 'keyword' => $keyWord, 'parent_id' => $parentId];
            $total = $folderManage->getList(array_merge($params, ['return_total_record' => 1]));
            $pageCount = ceil($total / $pageSize);
            $sortRule = [$sortKey => $sortDirection];
            $list = $folderManage->getList($params, $sortRule);
            $ret = ["total" => $total, "page_size" => $pageSize, "current" => $page, "last_page" => $pageCount, "list" => $list];
            return makeApiResponse(200, 'ok', $ret);
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }
}