<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Live;

use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Live\Live;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;

class LiveController extends BaseAdminController
{
    /**
     * 获取某直播的信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);

            //直播基本信息
            $liveInfo = $live->getInfo();

            //商品列表
            $productList = $live->getProductList();

            //优惠券列表
            $couponList = $live->getCouponList();

            //菜单列表
            $menuList = $live->getMenuList();

            return makeApiResponseSuccess('成功', [
                'liveInfo' => $liveInfo,
                'productList' => $productList,
                'couponList' => $couponList,
                'menuList' => $menuList
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getList(Request $request)
    {

        try {
            $params = $request->toArray();
            $list = live::getList($params, $params['page'], $params['page_size']);
            return makeApiResponseSuccess('成功', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function uploadNavCustomImage(Request $request)
    {
        try {
            $params = $request->toArray();
            $data = Live::uploadNavCustomImage($params);
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function uploadLiveImage(Request $request)
    {
        try {
            $params = $request->toArray();
            $id = $request->input('id', 0);
            $live = new Live($id);
            $data = $live->uploadLiveImage($params);
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function add(Request $request)
    {
        try {
            $info['coupon_list'] = $request->coupon_list;
            $info['product_list'] = $request->product_list;
            $info['nav_list'] = $request->nav_list;

            $info['base_info'] = $request->base_info;

            $live = (new Live())->add($info);
            if ($live) {
                return makeApiResponseSuccess('成功', [
                    'id' => $live
                ]);
            } else {
                return makeApiResponseFail('创建直播失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function edit(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }

            $info['coupon_list'] = $request->coupon_list;
            $info['product_list'] = $request->product_list;
            $info['nav_list'] = $request->nav_list;
            $info['base_info'] = $request->base_info;

            (new Live($request->id))->edit($info);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 设置是否全员禁言
     * @param Request $request
     * @return array
     */
    public function setMuted(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);
            $live->livingEdit(['muted' => $request->muted ? 1 : 0]);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 设置商品上屏
     * @param Request $request
     * @return array
     */
    public function setOnScreenProduct(Request $request)
    {
        try {
            if (!$request->live_id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->live_id);
            $live->setOnScreenProduct($request->product_id, $request->is_onscreen);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 设置优惠券上屏
     * @param Request $request
     * @return array
     */
    public function setOnScreenCoupon(Request $request)
    {
        try {
            if (!$request->live_id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->live_id);
            $live->setOnScreenCoupon($request->coupon_id, $request->is_onscreen);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 结束直播
     * @param Request $request
     * @return array
     */
    public function close(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);
            $data = $live->close();
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 开始直播
     * @param Request $request
     * @return array
     */
    public function open(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);
            $data = $live->open($request->live_platform, $request->live_src);
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function delete(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);
            $live->delete();
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}