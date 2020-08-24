<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/22
 * Time: 14:32
 */

namespace App\Http\Controllers\SiteAdmin\ResourceManage;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use Illuminate\Http\Request;
use YZ\Core\ResourceManage\FileManage;

class FileController extends BaseSiteAdminController
{
    public function upload(Request $request)
    {
        try{
            $fileManage = new FileManage();
            $fileManage->upload($request->file('file'), $request->get('folder_id'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function delete(Request $request)
    {
        try{
            $fileManage = new FileManage();
            $fileManage->delete($request->get('id'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function getList(Request $request)
    {
        try{
            $fileManage = new FileManage();
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