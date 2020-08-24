<?php
/**
 * 商品参数模板
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Product;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Product\ProductParamTemplate;
use Illuminate\Http\Request;

class ProductParamTemplateController extends BaseSiteAdminController
{
    /**
     * 列表数据
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $param['order_by'] = 'updated_at';
            $data = ProductParamTemplate::getList($param);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $this->convertData($item);
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            $productParamTemplate = new ProductParamTemplate($id);
            if (!$productParamTemplate->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $model = $productParamTemplate->getModel();
            $this->convertData($model);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $model);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存数据
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $id = intval($request->get('id', 0));
            $name = $request->get('name');
            $params = $request->get('params');
            // 数据完整性检查
            if (empty(trim($name))) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            if (!is_array($params) || count($params) == 0) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $inputData = $request->all();
            if (array_key_exists('name', $inputData)) $inputData['name'] = trim($inputData['name']);
            if (array_key_exists('params', $inputData)) $inputData['params'] = json_encode($inputData['params']);
            // 保存数据
            $productParamTemplate = new ProductParamTemplate($id);
            if ($id) {
                if (!$productParamTemplate->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
                $productParamTemplate->edit($inputData);
            } else {
                $id = $productParamTemplate->add($inputData);
            }
            // 返回数据
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'), [
                'id' => $id
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除数据
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->get('id');
            $productParamTemplate = new ProductParamTemplate($id);
            if (!$productParamTemplate->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
            $productParamTemplate->delete();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 输出数据转换
     * @param $item
     */
    private function convertData($item)
    {
        if ($item) {
            if ($item['params']) {
                $item['params'] = json_decode($item['params'], true);
            }
        }
    }
}