<?php
/**
 * 拼团接口
 * User: liyaohui
 * Date: 2020/4/6
 * Time: 15:13
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\GroupBuying;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use Illuminate\Http\Request;

class GroupBuyingController extends BaseAdminController
{
    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getSettingList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $params = $request->all(['status', 'keyword']);
            $data = GroupBuyingSetting::getList($params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $params = $request->all(['status', 'keyword', 'page', 'page_size']);
            $data = GroupBuying::getList($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存活动设置
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $params = $request->all();
            if ($params['base_info']['id']) {
                $groupBuyingSetting = new GroupBuyingSetting($params['base_info']['id']);
            } else {
                $groupBuyingSetting = new GroupBuyingSetting();
            }
            $groupBuyingSetting->save($params);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取商品的sku信息
     * @param Request $request
     * @return array
     */
    public function getProductSku(Request $request)
    {
        try {
            $productId = $request->input('product_id', 0);
            if (!$productId) {
                return makeApiResponseFail('请传入商品ID');
            }
            $groupId = $request->input('group_id', 0);
            $data = GroupBuyingSetting::getProductSkus($productId, $groupId);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取活动详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeApiResponseFail('请传入活动ID');
            }
            $setting = new GroupBuyingSetting($id);
            $data = $setting->getInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 结束活动
     * @param Request $request
     * @return array
     */
    public function setEnd(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeApiResponseFail('请传入活动ID');
            }
            $setting = new GroupBuyingSetting($id);
            $end = $setting->end();
            if ($end !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponseFail('活动结束失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 结束活动
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeApiResponseFail('请传入活动ID');
            }
            $setting = new GroupBuyingSetting($id);
            $delete = $setting->delete();
            if ($delete !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponseFail('删除活动失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}