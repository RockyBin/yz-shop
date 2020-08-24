<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Distribution;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use Illuminate\Http\Request;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Common\DataCache;

class DistributorController extends BaseAdminController
{
    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $pageSize = $request->get('page_size');
            if (!$pageSize) $pageSize = 20;
            $page = $request->get('page');
            if (!$page) $page = 1;
            $params = [
                'page_size' => $pageSize,
                'page' => $page
            ];

            if ($request->get('name')) $params['nickname'] = $request->get('name');
            if ($request->get('mobile')) $params['mobile'] = $request->get('mobile');
            if ($request->get('keyword')) $params['keyword'] = $request->get('keyword');
            if ($request->has('keyword_type')) $params['keyword_type'] = $request->get('keyword_type');
            if ($request->has('status')) $params['status'] = $request->get('status');
            if ($request->has('level')) $params['level'] = $request->get('level');
            if ($request->has('member_level')) $params['member_level'] = $request->get('member_level');
            if ($request->get('type')) $params['list_type'] = $request->get('type');
            if ($request->get('parent_id')) $params['parent_id'] = $request->get('parent_id');
            if ($request->get('finance_member_id')) $params['finance_member_id'] = $request->get('finance_member_id');
            if ($request->get('no_member_id')) $params['no_member_id'] = $request->get('no_member_id');

            if ($request->get('apply_time_start')) $params['apply_time_start'] = $request->get('apply_time_start');
            if ($request->get('apply_time_end')) $params['apply_time_end'] = $request->get('apply_time_end');
            if ($request->get('passed_time_start')) $params['passed_time_start'] = $request->get('passed_time_start');
            if ($request->get('passed_time_end')) $params['passed_time_end'] = $request->get('passed_time_end');
            if ($request->get('reg_time_start')) $params['reg_time_start'] = $request->get('reg_time_start');
            if ($request->get('reg_time_end')) $params['reg_time_end'] = $request->get('reg_time_end');

            if ($request->get('buy_money_min')) $params['buy_money_min'] = $request->get('buy_money_min');
            if ($request->get('buy_money_max')) $params['buy_money_max'] = $request->get('buy_money_max');
            if ($request->get('deal_money_min')) $params['deal_money_min'] = $request->get('deal_money_min');
            if ($request->get('deal_money_max')) $params['deal_money_max'] = $request->get('deal_money_max');

            if ($request->get('buy_times_min')) $params['buy_times_min'] = $request->get('buy_times_min');
            if ($request->get('buy_times_max')) $params['buy_times_max'] = $request->get('buy_times_max');
            if ($request->get('deal_times_min')) $params['deal_times_min'] = $request->get('deal_times_min');
            if ($request->get('deal_times_max')) $params['deal_times_max'] = $request->get('deal_times_max');

            if ($request->get('trade_money_min')) $params['trade_money_min'] = $request->get('trade_money_min');
            if ($request->get('trade_money_max')) $params['trade_money_max'] = $request->get('trade_money_max');
            if ($request->get('trade_time_min')) $params['trade_time_min'] = $request->get('trade_time_min');
            if ($request->get('trade_time_max')) $params['trade_time_max'] = $request->get('trade_time_max');

            if ($request->get('order_by')) {
                $params['order_by'] = $request->get('order_by');
                $params['order_by_asc'] = $request->get('order_by_asc');
            }

            //$params['return_buy_times'] = 1;
            //$params['return_buy_money'] = 1;
            //$params['return_deal_times'] = 1;
            //$params['return_deal_money'] = 1;
            $params['return_total_team'] = 1;
            $params['return_commission_money'] = 1;
            $params['return_directly_under_distributor'] = 1;
            //$params['return_directly_under_member'] = 1;
            //$params['return_sub_team_commission'] = 1;
            //$params['return_sub_team_order_num'] = 1;
            //$params['return_sub_self_purchase_order_num'] = 1;
            //$params['return_sub_self_purchase_commission'] = 1;
            //$params['return_sub_directly_order_num'] = 1;
            //$params['return_sub_directly_commission'] = 1;
            //$params['return_sub_subordinate_order_num'] = 1;
            //$params['return_sub_subordinate_commission'] = 1;

            // 返回记录总数时，不需要返回一些统计数据，这里强制将 return_commission_money 等设置为0
            $total = Distributor::getList(array_merge($params, ['return_total_record' => 1,'return_total_team' => 0,'return_commission_money' => 0,'return_directly_under_distributor' =>  0]));
            $pageCount = ceil($total / $pageSize);
            $list = Distributor::getList($params);
            $ret = ["total" => $total, "page_size" => $pageSize, "current" => $page, "last_page" => $pageCount, "data" => $list];
            return makeApiResponseSuccess('ok', $ret);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 分销商信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            $type = $request->get('type');
            $distributor = new Distributor($id);
            $params = [
                'list_type' => $type,
                'return_parent_info' => 1,
                'return_bind_weixin' => 1,
                'return_total_team' => 1,
                'return_deal_times' => 1,
                'return_deal_money' => 1,
                'return_buy_times' => 1,
                'return_buy_money' => 1,
                'return_directly_under_distributor' => 1,
                'return_directly_under_member' => 1,
                'return_subordinate_distributor' => 1,
                'return_subordinate_member' => 1,
                'return_commission_money' => 1,
                'return_sub_team_commission' => 1,
                'return_sub_team_order_num' => 1,
                'return_sub_self_purchase_order_num' => 1,
                'return_sub_self_purchase_commission' => 1,
                'return_sub_directly_order_num' => 1,
                'return_sub_directly_commission' => 1,
                'return_sub_subordinate_order_num' => 1,
                'return_sub_subordinate_commission' => 1,
            ];
            $info = $distributor->getInfo($params);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 审核此人的具体信息
     * @param Request $request
     * @return array
     */
    public function reviewDistributorInfo(Request $request)
    {
        $id = $request->get('id');
        $distributor = new Distributor($id);
        $params = [
            'list_type' => 1,
        ];
        $info = $distributor->getReviewDistributorInfo($params);
        return makeApiResponseSuccess('ok', $info);
    }

    /**
     * 审核
     * @param Request $request
     * @return array
     */
    public function review(Request $request)
    {
        try {
            $ids = $request->get('ids');
            $status = $request->get('status'); // 审核状态，-1=不通过，1=通过
            if (!is_array($ids)) $ids = [$ids];
            foreach ($ids as $id) {
                $info['status'] = $status;
                if ($request->has('reject_reason')) {
                    $reject_reason = $request->get('reject_reason');
                    $info['reject_reason'] = $reject_reason;
                }
                $distributor = new Distributor($id);
                $distributor->edit($info);
                // 分销商审核拒绝
                if (intval($status) == -1) {
                    MessageNoticeHelper::sendMessageDistributorBecomeReject($distributor->getModel());
                }
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->get('id');
            $distributor = new Distributor($id);
            if ($request->has('level')) $info['level'] = $request->get('level');
            if ($request->has('parent_id')) $info['parent_id'] = $request->get('parent_id');
            $distributor->edit($info);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 恢复分销商资格
     * @param Request $request
     * @return array
     */
    public function reActive(Request $request)
    {
        try {
            $id = $request->get('id');
            $distributor = new Distributor($id);
            $distributor->reActive();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 取消分销商资格
     * @param Request $request
     * @return array
     */
    public function deActive(Request $request)
    {
        try {
            $id = $request->get('id');
            $distributor = new Distributor($id);
            $distributor->deActive();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 添加
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function add(Request $request)
    {
        try {
            DataCache::setData(Constants::GlobalsKey_PointAtAdmin, true);
            $distributor = new Distributor();
            $info['level'] = $request->get('level');
            $info['member_id'] = $request->get('member_id');
            $info['site_id'] = Site::getCurrentSite()->getSiteId();
            $info['status'] = 1;
            $info['passed_at'] = date('Y-m-d H:i:s');
            $memInfo = MemberModel::find($info['member_id']);
            $info['mobile'] = $memInfo['mobile'];
            $info['sex'] = $memInfo['sex'];
            $info['prov'] = $memInfo['prov'];
            $info['city'] = $memInfo['city'];
            $info['area'] = $memInfo['area'];
            $add = $distributor->add($info, true, true);
            if ($add === true) {
                return makeApiResponseSuccess('ok');
            } else if ($add) {
                return makeApiResponse(511,'数据已存在',
                    [
                        'member_id' => $add->member_id,
                        'status' => $add->status
                    ]
                );
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 从审核列表删除
     * @param Request $request
     * @return array
     */
    public function deleteInReview(Request $request)
    {
        try {
            $id = $request->get('id');
            $distributor = new Distributor($id);
            $info = $distributor->deleteInReview();
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除分销商
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->get('id');
            $distributor = new Distributor($id);
            $info = $distributor->delete();
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
