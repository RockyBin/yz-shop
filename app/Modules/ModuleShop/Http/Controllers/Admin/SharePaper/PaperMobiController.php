<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SharePaper;

use App\Modules\ModuleShop\Libs\SharePaper\Mobi\Paper;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Illuminate\Support\Facades\DB;

class PaperMobiController extends BaseAdminController
{
    public function __construct()
    {

    }

    /**
     * 获取海报的信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            if ($id) $page = new Paper($id);
            else $page = Paper::getDefaultPaper();
            $result = $page->getModel()->toArray();
            $result['modules'] = json_decode($result['modules'], true);
            if($result['keyword_id']){
                $result['keyword']= Paper::getKeyword($result['keyword_id']);
            }
            return makeApiResponseSuccess('ok', ['paperInfo' => $result]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取海报的列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $search = $request->toArray();
            $page=$request->page;
            $page_size=$request->page_size;
            $data = Paper::getList($search,$page,$page_size);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存海报显示的位置
     * @param Request $request
     * @return array
     */
    public function savePaperShow(Request $request)
    {
        try {
            $all=$request->all();
            Paper::savePaperShow($all['data']);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存海报显示的位置
     * @param Request $request
     * @return array
     */
    public  function  getConfig(){
        $data=Paper::getConfigInfo();
        return makeApiResponseSuccess('ok', $data);
    }
    /**
     * 删除某张海报
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->delete_id;
            if(!is_array($id)){
                return makeApiResponseFail('传值必须是数组');
            }
            Paper::delete($id);
            DB::commit();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存海报信息
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $all = $request->all();
            //获取页面对象，如果没有传ID说明是新增
            if ($request->get("paper_id")) $page = new Paper($request->get("paper_id"));
            else $page = new Paper();
            //更新页面数据
            $newPaperId = $page->update($all['paperInfo']);
            return makeApiResponseSuccess('ok', ['paper_id' => $newPaperId]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取模板信息
     */
    public function templateDate(){
        try{
            $data= Paper::templateDate();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}