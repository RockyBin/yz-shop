<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/22
 * Time: 15:11
 */

namespace App\Http\Controllers\SiteAdmin\ResourceManage;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use Illuminate\Http\Request;
use YZ\Core\ResourceManage\FolderManage;

class FolderController extends BaseSiteAdminController
{
    public function add(Request $request)
    {
        try{
            $folderManage = new FolderManage();
            $folderManage->add($request->get('name'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function rename(Request $request)
    {
        try{
            $folderManage = new FolderManage();
            $folderManage->rename($request->get('id'),$request->get('name'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function delete(Request $request)
    {
        try{
            $folderManage = new FolderManage();
            $folderManage->delete($request->get('id'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function getList(Request $request)
    {
        try{
            $folderManage = new FolderManage();
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