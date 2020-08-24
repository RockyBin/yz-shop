<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Wx;

use Illuminate\Http\Request;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\Weixin\WxNews;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class WxNewsController extends BaseAdminController
{
    /**
     * 列表
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->toArray();
            $data = WxNews::getList($param);
            $data['config_full'] = WxConfig::checkConfig();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 子图文列表
     * @return array
     */
    public function getItemList(Request $request)
    {
        try {
            $param = $request->toArray();
            $data = WxNews::getItemList($param);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->id;
            if (empty($id)) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $wxNews = new WxNews($id);
            if (!$wxNews->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $model = $wxNews->getModel();
            $model->items = $model->items()->get();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $model);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $items = $request->items;
            if (!is_array($items) || count($items) == 0) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }

            $isCreate = false;
            $id = $request->id;
            if ($id) {
                $wxNews = new WxNews($id);
                if (!$wxNews->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            } else {
                $wxNews = new WxNews();
                $wxNews->setCreatedAt(date('Y-m-d H:i:s'));
                $wxNews->setSiteId(Site::getCurrentSite()->getSiteId());
                $wxNews->save();
                $isCreate = true;
            }

            foreach ($items as $item) {
                $itemId = $item['id'];
                if ($isCreate) {
                    $wxNews->addItem($item);
                } else {
                    if (empty($itemId)) {
                        return makeApiResponseFail(trans('shop-admin.common.data_error'));
                    }

                    $wxNews->editItem($itemId, $item);
                }
            }
            // 推送到微信
            $push = $wxNews->push();
            if ($push['errcode'] != 0) {
                return makeApiResponse('500', $push['errmsg'], $push);
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->id;
            if (empty($id)) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $wxNews = new WxNews($id);
            if (!$wxNews->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $wxNews->delete();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}