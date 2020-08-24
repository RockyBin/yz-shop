<?php
/**
 * 经销商奖金通用api
 * User: liyaohui
 * Date: 2020/1/10
 * Time: 20:40
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Dealer;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use Illuminate\Http\Request;

class DealerRewardController extends BaseMemberController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取奖金详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request){
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeServiceResult(500, '确少参数id');
            }
            $reward = new DealerReward($id);
            $info = $reward->getInfo();
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取我的兑换奖金列表
     * @param Request $request
     * @return array
     */
    public function getMyRewardList(Request $request){
        try {
            $memberId = $this->memberId;
            $status = $request->input('status', 0);
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $getCount = $request->input('get_count', 0);
            $list = DealerReward::getMyRewardList($memberId, ['status' => $status], $page, $pageSize, $getCount);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function exchange(Request $request){
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeServiceResult(500, '确少参数id');
            }
            $reward = new DealerReward($id);
            // 是否是当前会员的奖金
            $rewardModel = $reward->getModel();
            if ($rewardModel->member_id != $this->memberId) {
                return makeServiceResult(400, '奖金数据错误');
            }
            // 是否是未兑换状态
            if ($rewardModel->status != Constants::DealerRewardStatus_WaitExchange) {
                return makeServiceResult(401, '该笔奖金已兑换过');
            }
            $reward->exchange();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            dd($e);
            //return makeApiResponseError($e);
        }
    }

    /**
     * 审核通过奖金
     * @param Request $request
     * @return array
     */
    public function pass(Request $request){
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeServiceResult(500, '确少参数id');
            }
            $reward = new DealerReward($id);
            // 是否是当前会员审核的奖金
            $rewardModel = $reward->getModel();
            if ($rewardModel->pay_member_id != $this->memberId) {
                return makeServiceResult(400, '奖金数据错误');
            }
            // 是否是未审核状态
            if ($rewardModel->status != Constants::DealerRewardStatus_WaitReview) {
                return makeServiceResult(401, '该笔奖金已审核过');
            }
            $reward->pass();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 拒绝审核奖金
     * @param Request $request
     * @return array
     */
    public function reject(Request $request){
        try {
            $id = $request->input('id', 0);
            $reason = $request->input('reason', '');
            if (!$id) {
                return makeServiceResult(500, '确少参数id');
            }
            if (!$reason) {
                return makeServiceResult(501, '请输入拒绝原因');
            }
            $reward = new DealerReward($id);
            // 是否是当前会员审核的奖金
            $rewardModel = $reward->getModel();
            if ($rewardModel->pay_member_id != $this->memberId) {
                return makeServiceResult(400, '奖金数据错误');
            }
            // 是否是未审核状态
            if ($rewardModel->status != Constants::DealerRewardStatus_WaitReview) {
                return makeServiceResult(401, '该笔奖金已审核过');
            }
            $reward->reject($reason);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取奖金收入列表
     * @param Request $request
     * @return array
     */
    public function getInRewardList(Request $request){
        try {
            $type = $request->input('type', 1);
            $year = $request->input('year');
            $month = $request->input('month');
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $getCount = $request->input('get_count', 0);
            $list = DealerReward::getRewardList(
                $this->memberId,
                $type,
                ['year' => $year, 'month' => $month, 'get_count' => $getCount],
                $page,
                $pageSize
            );
            // 是否需要获取统计信息 一般获取一次就可以了
//            if ($getCunt) {
//                $count = DealerReward::getRewardCount($this->memberId, $type);
//                $count['reward_money'] = moneyCent2Yuan($count['reward_money']);
//                $list['count'] = $count;
//            }
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取奖金支出列表
     * @param Request $request
     * @return array
     */
    public function getOutRewardList(Request $request){
        try {
            $type = $request->input('type', 1);
            $year = $request->input('year');
            $month = $request->input('month');
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $getCount = $request->input('get_count', 0);
            $list = DealerReward::getOutRewardList(
                $this->memberId,
                $type,
                ['year' => $year, 'month' => $month, 'get_count' => $getCount],
                $page,
                $pageSize
            );
//            // 是否需要获取统计信息 一般获取一次就可以了
//            if ($getCunt) {
//                $count = DealerReward::getOutRewardCount($this->memberId, $type);
//                $count['reward_money'] = moneyCent2Yuan($count['reward_money']);
//                $list['count'] = $count;
//            }
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}