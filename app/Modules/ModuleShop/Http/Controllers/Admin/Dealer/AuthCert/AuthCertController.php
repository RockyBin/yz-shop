<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer\AuthCert;

use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Dealer\AuthCert\AuthCert;
use Illuminate\Support\Facades\DB;

class AuthCertController extends BaseAdminController
{
    /**
     * 获取证书的信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            if (!$id) {
                throw new \Exception('缺少参数 id');
            }
            $page = new AuthCert($id);
            $result = $page->getModel()->toArray();
            $result['modules'] = json_decode($result['modules'], true);
            foreach ($result['modules'] as &$m){
                if($m['module_type'] == 'ModuleQrcode'){
                    $m = AuthCert::formatQrcodeModule($m);
                }
            }
            unset($m);
            return makeApiResponseSuccess('ok', ['info' => $result]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取证书的列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $search = $request->toArray();
            $page = $request->page;
            $page_size = $request->page_size;
            $data = AuthCert::getList($search, $page, $page_size);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除某个证书
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->id;
            if (!is_array($id)) {
                return makeApiResponseFail('传值必须是数组');
            }
            AuthCert::delete($id);
            DB::commit();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存证书信息
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $all = $request->all();
            $id = $request->get('id');
            $page = new AuthCert($id);
            $newPaperId = $page->update($all['info']);
            return makeApiResponseSuccess('ok', ['id' => $newPaperId]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取模板信息
     */
    public function templateDate()
    {
        try {
            $data = AuthCert::templateDate();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取授权证书的应用设置信息
     * @param Request $request
     * @return array
     */
    public  function getApplySettingInfo(){
        $data = AuthCert::getApplySettingInfo();
        $levels = DealerLevelModel::query()->where(['site_id' => getCurrentSiteId(),'parent_id' => 0])->orderBy('weight','desc')->get();
        $data['levels'] = $levels;
        return makeApiResponseSuccess('ok', $data);
    }

    /**
     * 获取授权证书的应用设置信息
     * @param Request $request
     * @return array
     */
    public  function saveApplySettingInfo(Request $request){
        AuthCert::saveApplySettingInfo($request->all());
        return makeApiResponseSuccess('ok');
    }
}
