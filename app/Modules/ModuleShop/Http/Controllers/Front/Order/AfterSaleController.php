<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 2019/2/14
 * Time: 16:15
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Order;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Order\AfterSale;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use Illuminate\Http\Request;

class AfterSaleController extends BaseMemberController
{
    public function __construct()
    {
        parent::__construct();
    }

    // 申请售后界面数据
    public function applyAfterSale(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $items = $request->input('items', []);
            if ($orderId && $items) {
                $items = collect($items)->pluck('num', 'item_id');
                $afterSale = new AfterSale($this->memberId);
                $afterSale->initOrderModel($orderId);
                $afterSaleItems = $afterSale->getAfterSaleData($items);
                $data = array_merge($afterSaleItems, AfterSale::getAfterSaleText());
                return makeApiResponseSuccess('ok', $data);
            } else {
                return makeApiResponse(400, trans('shop-front.shop.data_error'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    // 创建售后
    public function createAfterSale(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $items = $request->input('items', []);
            $param = $request->input('param', []);
            if (!$orderId || !$items || !$param) {
                return makeApiResponse(400, trans('shop-front.shop.data_error'));
            }
            $items = collect($items)->pluck('num', 'item_id');
            $afterSale = new AfterSale($this->memberId);
            $afterSale->initOrderModel($orderId);
            $afterSaleId=$afterSale->createAfterSale($orderId, $items, $param);
            if($afterSaleId){
                return makeApiResponseSuccess('ok',['afterSaleId'=>$afterSaleId]);
            }else{
                return makeApiResponse(400, trans('shop-front.shop.data_error'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    // 编辑售后
    public function editAfterSale(Request $request)
    {
        try {
            $id = $request->input('id');
            $param = $request->input('param', []);
            if (!$id || !$param) {
                return makeApiResponse(400, trans('shop-front.shop.data_error'));
            }
            $afterSale = new AfterSale($this->memberId);
            $afterSale->initAfterSaleById($id);
            $afterSale->editAfterSale($id, $param);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    // 获取售后信息
    public function getAfterSaleInfo(Request $request)
    {
        try {
            $id = $request->input('id');
            $afterSale = new AfterSale($this->memberId);
            $data = $afterSale->getAfterSaleInfo($id, true);
            $text = [
                'text' => AfterSale::getAfterSaleText(),
                'express_text' => Constants::getExpressCompanyText()
            ];
            $data = array_merge($data, $text);
            $data['aftersale_isopen']=(new OrderConfig())->getInfo()['aftersale_isopen'];
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    // 撤销售后
    public function cancelAfterSale(Request $request)
    {
        try {
            $id = $request->input('id');
            $afterSale = new AfterSale($this->memberId);
            $afterSale->initAfterSaleById($id);
            $afterSale->cancelAfterSale();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    // 填写售后物流
    public function editLogisticsInfo(Request $request)
    {
        try {
            $id = $request->input('id');
            $logisticsNo = $request->input('logistics_no');
            $logisticsKey = $request->input('logistics_key');
            $logisticsName = $request->input('logistics_name', null);
            $afterSale = new AfterSale($this->memberId);
            $afterSale->editLogisticsInfo($id, $logisticsNo, $logisticsKey, $logisticsName);
            // 发送通知
            MessageNoticeHelper::sendMessageAfterSaleGoodsRefund($afterSale->getAfterSaleModel());
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    // 获取售后列表
    public function getList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 10);
            $afterSale = new AfterSale($this->memberId);
            $list = $afterSale->getAfterSaleList($page, $pageSize);
            $statusText['status_text'] = Constants::getFrontAfterSaleStatusText();
            $list = array_merge($list, $statusText);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    // 获取可收货产品列表
    public function getCanAfterSaleProductList(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $afterSale = new AfterSale($this->memberId);
            $afterSale->initOrderModel($orderId);
            $productList = $afterSale->getCanAfterSaleProductList();
            return makeApiResponseSuccess('ok', $productList);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function uploadAfterSaleImage(Request $request)
    {
        try {
            $image = AfterSale::uploadAfterSaleImage($request->file('image'));
            return makeApiResponseSuccess('ok', ['image_src' => $image]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getAfterSaleBatchList(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $afterSale = new AfterSale($this->memberId);
            $afterSale->initOrderModel($orderId);
            $productList = $afterSale->getCanAfterSaleProductList();
            return makeApiResponseSuccess('ok', $productList);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
