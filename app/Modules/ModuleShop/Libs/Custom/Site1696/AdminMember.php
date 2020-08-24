<?php
/**
 * 后台会员相关定制逻辑
 * User: liyaohui
 * Date: 2020/5/21
 * Time: 11:44
 */

namespace App\Modules\ModuleShop\Libs\Custom\Site1696;


use App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

class AdminMember
{
    public static function getSubMemberOrderMoneyList($params = [])
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $memberQuery = MemberModel::query()
            ->where('site_id', $siteId)
            ->orderByDesc('id');
        // 关键字搜索
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $memberQuery->where(function ($q) use ($keyword) {
                $q->orWhere('nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('name', 'like', '%' . $keyword . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $q->orWhere('mobile', 'like', '%' . $keyword . '%');
                }
            });
        }
        // 总数据量
        $total = $memberQuery->count();

        $page = $params['page'] ?: 1;
        $pageSize = $params['page_size'] ?: 20;
        $lastPage = ceil($total/$pageSize);

        $members = $memberQuery->forPage($page, $pageSize)
            ->select(['nickname', 'name', 'id', 'mobile', 'headurl'])
            ->get();
        $memberIds = $members->pluck('id')->toArray();
        $agentUpgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
        if ($memberIds) {
            if (isset($params['order_valid_condition'])) {
                $orderValidCondition = $params['order_valid_condition'];
            } else {
                $orderValidCondition = $agentUpgradeSetting->order_valid_condition;
            }
            if ($orderValidCondition == 1) {
                $orderStatue = [Constants::OrderStatus_OrderFinished];
                $selectRaw = 'mh.member_id, sum( item.price * (item.num - after_sale_over_num) ) as total_money, sum(item.point_money) as point_money, sum(item.coupon_money) as coupon_money,sum(ifnull(discount.discount_price,0) ) AS discount_total';
            } else {
                $orderStatue = Constants::getPaymentOrderStatus();
                $selectRaw = 'mh.member_id, sum( item.price * item.num ) as total_money, sum(item.point_money) as point_money, sum(item.coupon_money) as coupon_money,sum(ifnull(discount.discount_price,0) ) AS discount_total';
            }
            
            $list = OrderMembersHistoryModel::query()
                ->from('tbl_order_members_history as mh')
                ->join('tbl_order as o', 'o.id', 'mh.order_id')
                ->leftJoin('tbl_order_item as item', 'item.order_id', 'o.id')
                ->leftJoin('tbl_order_item_discount as discount', 'discount.item_id', 'item.id')
                ->where('mh.site_id', $siteId)
                ->where('item.has_commission_product', 1)
                ->whereIn('o.status', $orderStatue)
                ->whereIn('mh.member_id', $memberIds)
                ->where('mh.level', '>', 0)
                ->where('mh.type', 0);

            $timeField = 'o.pay_at';
            // 维权期后
            if ($params['time_type'] == 1) {
                $timeField = 'o.end_at';
                $list->whereNotNull('o.end_at');
            } elseif ($params['time_type'] == 2) {
                $timeField = 'o.created_at';
            }

            if (isset($params['time_start'])) {
                $list->where($timeField, '>=', trim($params['time_start']));
            }
            if (isset($params['time_end'])) {
                $list->where($timeField, '<=', trim($params['time_end']));
            }

            $list = $list->groupBy('mh.member_id')
                ->selectRaw($selectRaw)
                ->get()->keyBy('member_id');

            foreach ($members as $item) {
                $money = $list[$item->id];
                $item['total'] = moneyCent2Yuan($money['total_money'] - $money['point_money'] - $money['coupon_money'] - $money['discount_total'] );
                $item['mobile'] = Member::memberMobileReplace($item['mobile']);
            }
        }

        return [
            'list' => $members->toArray(),
            'total' => $total,
            'last_page' => $lastPage,
            'current' => $page,
            'page_size' => $pageSize,
            'order_valid_condition' => $agentUpgradeSetting->order_valid_condition
        ];
    }
}