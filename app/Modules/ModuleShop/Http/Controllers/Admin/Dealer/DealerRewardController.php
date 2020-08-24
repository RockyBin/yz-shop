<?php
/**
 * 经销商奖金通用接口
 * User: liyaohui
 * Date: 2020/1/10
 * Time: 14:37
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;


use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use Illuminate\Http\Request;

class DealerRewardController extends BaseSiteAdminController
{
    /**
     * 获取奖金详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->input('id');
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $data = (new DealerReward($id))->getInfo();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 审核奖金
     * @param Request $request
     * @return array
     */
    public function verify(Request $request)
    {
        try {
            $id = $request->input('id');
            $status = $request->input('status', 0);
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $reward = new DealerReward($id);
            $model = $reward->getModel();
            // 如果是上级审核 则公司不能去审核
            if ($model->pay_member_id) {
                return makeApiResponseFail('需要上级审核');
            }
            // 是否审核过
            if ($model->status != Constants::DealerRewardStatus_WaitReview) {
                return makeApiResponseFail('该奖金状态无法审核');
            }
            if ($status) {
                $reward->pass();
            } else {
                $reason = $request->input('reason', '');
                if (!$reason) {
                    return makeApiResponseFail('请输入拒绝理由');
                }
                $reward->reject($reason);
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 兑换奖金
     * @param Request $request
     * @return array
     */
    public function exchange(Request $request)
    {
        try {
            $id = $request->input('id');
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $reward = new DealerReward($id);
            // 是否兑换过
            if ($reward->getModel()->status != Constants::DealerRewardStatus_WaitExchange) {
                return makeApiResponseFail('该奖金状态无法兑换');
            }
            $reward->exchange();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}