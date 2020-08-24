<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Order;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\Order\Logistics;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController as BaseController;
use YZ\Core\Site\Site;

/**
 * 物流信息
 * Class LogisticsController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Order
 */
class LogisticsController extends BaseController
{
    /**
     * 单个物流信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            // 检查数据
            $id = intval($request->id);
            if ($id <= 0) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $logistics = Logistics::find($id);
            // 检查是否当前用户
            $model = $logistics->getModel();
            // 第三方查询地址
            $logisticsPage = $logistics->getSearchPage();
            $model->search_url = $logisticsPage['url'];
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $model);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 修改物流信息
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            // 检查数据
            $id = intval($request->logistics_id);
            if ($id <= 0) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $logistics = Logistics::find($id);
            if (!$logistics->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $logistics_company = intval(trim($request->get('logistics_company')));
            $logistics_no = trim($request->get('logistics_no'));
            $logistics_name = Constants::getExpressCompanyText(intval($logistics_company));
            if ($logistics_company == Constants::ExpressCompanyCode_Other) {
                $logistics_name = trim($request->get('logistics_name'));
            }
            if (empty($logistics_name)) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $logisticsModel=$logistics->getModel();
            // 检查对应订单的状态，已支付和已发货才能好修改物流信息
            if ($logistics->getModel()->order_id) {
                $orderModel = OrderModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('id', $id)->first();
                if ($orderModel && !in_array(intval($orderModel->status), [Constants::OrderStatus_OrderPay, Constants::OrderStatus_OrderSend])) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            }
            // 发货通知
            if ($logisticsModel->logistics_company != $logistics_company || $logisticsModel->logistics_name != $logistics_name || $logisticsModel->logistics_no != $logistics_no) {
                $sendMessage=true;
            }
            // 编辑
            $logistics->edit([
                'logistics_company' => $logistics_company,
                'logistics_name' => $logistics_name,
                'logistics_no' => $logistics_no,
            ]);
            if($sendMessage)  MessageNoticeHelper::sendMessageOrderSend($logistics->getModel());
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}