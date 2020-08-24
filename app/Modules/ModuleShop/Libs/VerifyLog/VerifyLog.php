<?php

/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\VerifyLog;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use YZ\Core\Member\Member;
use YZ\Core\Site\Site;

class VerifyLog
{
    /**
     * 记录审核日志
     * @param integer $type 日志类型
     * @param string $model 模型
     * @return bool|void
     * @throws \Exception
     */
    public static function Log(int $type, $model)
    {
        try {
            switch ($type) {
                case $type == Constants::VerifyLogType_DealerVerify:
                    if ($model instanceof DealerModel) {
                        return DealerVerifyLog::save($type, $model);
                    } else {
                        throw new \Exception('模型错误');
                    }
                    break;
                case $type == Constants::VerifyLogType_CloudStockPurchaseOrderFinanceVerify:
                    if ($model instanceof CloudStockPurchaseOrderModel) {
                        return CloudStockPurchaseOrderFinanceVerifyLog::save($type, $model);
                    } else {
                        throw new \Exception('模型错误');
                    }
                    break;
                case $type == Constants::VerifyLogType_BalanceVerify:
                    return BalanceVerifyLog::save($type, $model);
                    break;
                // 经销商奖金审核记录
                case Constants::VerifyLogType_DealerPerformanceReward:
                case Constants::VerifyLogType_DealerRecommendReward:
                case Constants::VerifyLogType_DealerSaleReward:
                    return DealerRewardVerifyLog::save($type, $model);
                    break;
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public static function getList($params)
    {
        // 分页参数
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        $showAll = $params['show_all'] || ($params['ids'] && strlen($params['ids'] > 0)) ? true : false; // 是否显示所有，导出功能用，默认False
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;
        $expression = VerifyLogModel::query()
            ->where('tbl_verify_log.site_id', Site::getCurrentSite()->getSiteId());
        $expression->leftJoin('tbl_member', 'tbl_verify_log.from_member_id', 'tbl_member.id');
        if (isset($params['status'])) {
            $expression->whereIn('tbl_verify_log.status', myToArray($params['status']));
        }
        if (isset($params['member_id'])) {
            $expression->where('member_id', $params['member_id']);
        }
        if (isset($params['from_member_id'])) {
            $expression->where('from_member_id', $params['from_member_id']);
        }
        if (isset($params['type'])) {
            $expression->where('type', $params['type']);
        }

        if (isset($params['keyword'])) {
            $expression->where(function ($query) use ($params) {
                //$query->where('tbl_verify_log.info', 'like', '%' . $params['keyword'] . '%');
                  $query->orWhere('tbl_member.mobile', 'like', '%' . $params['keyword'] . '%');
                  $query->orWhere('tbl_member.nickname', 'like', '%' . $params['keyword'] . '%');
                  $query->orWhere('tbl_member.name', 'like', '%' . $params['keyword'] . '%');
            });
        }
        if (isset($params['pay_type'])) {
            $expression->where('info', 'like', '%' . $params['pay_type'] . '%');
        }

        // 成为经销商时间搜索
        if (isset($params['created_at_start'])) {
            $expression->where('tbl_verify_log.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $expression->where('tbl_verify_log.created_at', '<=', $params['created_at_end']);
        }

        // ids
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            if (count($ids) > 0) {
                $expression->whereIn('tbl_member.id', $ids);
            }
        }

        $balanceUnauditedCount = VerifyLogModel::query()
            ->where('tbl_verify_log.site_id', Site::getCurrentSite()->getSiteId())
            ->leftJoin('tbl_member', 'tbl_verify_log.from_member_id', 'tbl_member.id')
            ->where('tbl_verify_log.status', 0)->where('member_id', 0)
            ->count();
        $total = $expression->count();
        if ($total > 0 && $showAll) {
            $page = 1;
            $page_size = $total;
        }

        $expression->forPage($page, $page_size);
        $expression->selectRaw('tbl_verify_log.*,
        tbl_member.nickname,
        tbl_member.headurl,
        tbl_member.mobile,
        tbl_member.name,
        tbl_verify_log.member_id as log_member_id');
        $expression->orderBy('created_at', 'desc');
        $list = $expression->get();
        //余额审核是否存在(仅供后台余额审核列表使用)
        foreach ($list as &$item) {
            $item->info = json_decode($item->info);
            if (in_array($item->type, [Constants::VerifyLogType_CloudStockPurchaseOrderFinanceVerify, Constants::VerifyLogType_BalanceVerify]) && $item->info) {
                $member = (new Member($item->info->member_id))->getModel();
                $item->nickname = $member->nickname;
                $item->headurl = $member->headurl;
                $item->mobile = $member->mobile;
            }
            if ($item->type == Constants::VerifyLogType_BalanceVerify && $item->info) {
                $item->info->money = moneyCent2Yuan($item->info->money);
                if ($item->info->recharge_bonus) {
                    $item->info->recharge_bonus = json_decode($item->info->recharge_bonus, true);
                    $item->info->recharge_bonus['recharge'] = moneyCent2Yuan($item->info->recharge_bonus['recharge']);
                    $item->info->recharge_bonus['bonus'] = moneyCent2Yuan($item->info->recharge_bonus['bonus']);
                }
                $member = (new Member($item->member_id))->getModel();
                $item->parent_nickname = $member->nickname;
                $item->parent_headurl = $member->headurl;
                $item->parent_mobile = $member->mobile;
            }
            if (in_array($item->type, [Constants::VerifyLogType_CloudStockPurchaseOrderFinanceVerify, Constants::VerifyLogType_BalanceVerify])) {
                $item->review_button = true;
            } else {
                // 有推荐人的时候
                if (($item->info->parent_review_member == $item->log_member_id && $item->info->parent_review_member != $item->invite_review_member && $item->info->invite_review_status == Constants::DealerStatus_Active && $item->info->parent_review_status == Constants::DealerStatus_WaitReview)
                    || ($item->info->invite_review_status == Constants::DealerStatus_WaitReview && $item->info->invite_review_member == $item->log_member_id)
                    || ($item->info->invite_review_member == 0 && $item->info->parent_review_status == Constants::DealerStatus_WaitReview && $item->info->parent_review_member != 0)
                ) {
                    $item->review_button = true;
                } else {
                    $item->review_button = false;
                }
            }
        }
        //输出-最后页数
        $last_page = ceil($total / $page_size);
        $result = [
            'balance_unaudited_count' => $balanceUnauditedCount > 0 ? true : false,
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
        return $result;
    }


    /**
     * 记录审核日志
     * @param integer $type 日志类型
     * @param string $model 模型
     * @param  $memberId 审核人Id
     */
    public static function getInfo(int $type, $model, $memberId)
    {

        switch ($type) {
            case $type == Constants::VerifyLogType_DealerVerify:
                return DealerVerifyLog::getInfo($model, $memberId);
                break;
            case $type == Constants::VerifyLogType_BalanceVerify:
                return BalanceVerifyLog::getInfo($model, $memberId);
                break;
        }
    }
}
