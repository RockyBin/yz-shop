<?php
namespace App\Modules\ModuleShop\Http\Controllers\SysManage\Template;

use App\Modules\ModuleShop\Libs\UI\Module\BaseModule;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\TemplateMobi\TemplateMobi;
use App\Http\Controllers\SysManage\BaseSysManageController;

class TemplateController extends BaseSysManageController
{
    public function getInfo(Request $request){
        try{
            $template = new TemplateMobi();
            $info = $template->get($request->get('id'));
            return makeApiResponse(200, 'ok', $info->toArray());
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function add(Request $request)
    {
        try{
            $template = new TemplateMobi();
            $template->add($request->all());
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function delete(Request $request)
    {
        try{
            $template = new TemplateMobi();
            $template->delete($request->get('id'));
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function edit(Request $request)
    {
        try{
            $template = new TemplateMobi();
            $template->edit($request->get('id'),$request->all());
            return makeApiResponse(200, 'ok');
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function getList(Request $request)
    {
        try{
            $template = new TemplateMobi();
            $keyWord = $request->get('keyword');
            $pageSize = $request->get('page_size');
            $sortKey = $request->get('sort_key','id');
            $sortDirection = $request->get('sort_direction','desc');
            if (!$pageSize) $pageSize = 20;
            $page = $request->get('page');
            if (!$page) $page = 1;
            $params = ['page_size' => $pageSize, 'page' => $page, 'keyword' => $keyWord];
            if($request->has('status')) $params['status'] = $request->get('status');
            if($request->has('industry_id')) $params['industry_id'] = $request->get('industry_id');
            if($request->has('site_id')) $params['site_id'] = $request->get('site_id');
            if($request->has('page_id')) $params['page_id'] = $request->get('page_id');
            $total = $template->getList(array_merge($params, ['return_total_record' => 1]));
            $pageCount = ceil($total / $pageSize);
            $sortRule = ['is_blank' => 'desc',$sortKey => $sortDirection];
            $list = $template->getList($params, $sortRule);
            $ret = ["total" => $total, "page_size" => $pageSize, "current" => $page, "last_page" => $pageCount, "list" => $list];
            return makeApiResponse(200, 'ok', $ret);
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function getIndustryList(Request $request)
    {
        try{
            $list = \YZ\Core\Model\BaseModel::runSql("select * from tbl_industry");
            return makeApiResponse(200, 'ok',['list' => $list]);
        }catch(\Exception $ex){
            return makeApiResponse(500, $ex->getMessage());
        }
    }
}