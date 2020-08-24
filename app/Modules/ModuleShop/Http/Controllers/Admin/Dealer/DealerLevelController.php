<?php
/**
 * 经销商等级
 * User: liyaohui
 * Date: 2019/12/2
 * Time: 14:07
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use Illuminate\Http\Request;

class DealerLevelController extends BaseAdminController
{
    /**
     * 获取列表 无权限控制
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $params = $request->all();
            // 排序为默认的 权重倒序
            $params['order_by'] = ['weight', 'desc'];
            $list = DealerLevel::getLevelList($params);
            return makeApiResponseSuccess('ok', ['list' => $list]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取列表 有权限控制
     * @param Request $request
     * @return array
     */
    public function getPermList(Request $request)
    {
        try {
            return $this->getList($request);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取等级详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request){
        try {
            $id = $request->input('id', 0);
            $level = new DealerLevel();
            $info = $level->getLevelInfo($id);
            return makeApiResponseSuccess('ok', ['info' => $info]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新增等级
     * @param Request $request
     * @return array|bool
     */
    public function add(Request $request){
        try {
            $params = $request->all();
            $level = new DealerLevel();
            $save = $level->add($params);
            if ($save === true) {
                return makeApiResponseSuccess('ok');
            } else {
                return $save;
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 编辑等级
     * @param Request $request
     * @return array|bool
     */
    public function edit(Request $request){
        try {
            $params = $request->all();
            $level = new DealerLevel();
            $save = $level->edit($params);
            if ($save === true) {
                return makeApiResponseSuccess('ok');
            } else {
                return $save;
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 禁用等级
     * @param Request $request
     * @return array
     */
    public function disable(Request $request){
        try {
            $id = $request->input('id', 0);
            $level = new DealerLevel();
            $level->levelDisable($id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 启用等级
     * @param Request $request
     * @return array
     */
    public function enable(Request $request){
        try {
            $id = $request->input('id', 0);
            $level = new DealerLevel();
            $level->levelEnable($id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除等级
     * @param Request $request
     * @return array
     */
    public function delete(Request $request){
        try {
            $id = $request->input('id', 0);
            $level = new DealerLevel();
            $level->levelDelete($id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取已使用的权重
     * @param Request $request
     * @return array
     */
    public function getEnabledWeight(Request $request){
        try {
            $parentId = $request->input('parent_id', 0);
            $weights = (new DealerLevel())->getEnabledLevelWeight($parentId);
            return makeApiResponseSuccess('ok', ['weights' => $weights]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

}