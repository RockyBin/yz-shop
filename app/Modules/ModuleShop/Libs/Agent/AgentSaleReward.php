<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use YZ\Core\Site\Site;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Order\AfterSale;
use App\Modules\ModuleShop\Libs\Constants;

/**
 * 代理订单销售奖
 */
class AgentSaleReward
{
    /**
     * 列表
     * @param array $param
     * @return array
     */
    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        $query = DB::table('tbl_order')
            ->from('tbl_order')
            ->leftJoin('tbl_finance', 'tbl_finance.order_id', '=', 'tbl_order.id')
            ->leftJoin('tbl_member as member', 'tbl_finance.member_id', 'member.id')
            ->where('tbl_finance.type', \YZ\Core\Constants::FinanceType_AgentCommission)
            ->where('tbl_finance.sub_type', \YZ\Core\Constants::FinanceSubType_AgentCommission_SaleReward)
            ->where('tbl_finance.money', '>', 0)
            ->where('tbl_order.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count(DB::Raw('DISTINCT(tbl_order.id)'));
        $last_page = ceil($total / $pageSize);
        // 排序
        $query->orderBy('tbl_order.created_at', 'desc')->groupBy('tbl_order.id');
//        $query->selectRaw('DISTINCT(tbl_order.id)');
        $query->addSelect('tbl_order.id as order_id', 'tbl_order.created_at', 'tbl_order.after_sale_type', 'tbl_order.after_sale_status', 'tbl_order.money as order_money', 'tbl_finance.status as finance_status', 'tbl_order.agent_sale_reward_commision');
        $query->addSelect('member.id as member_id', 'member.nickname', 'member.mobile', 'member.name','member.agent_level');
        $query->forPage($page, $pageSize);
        $list = $query->get();

        $memberIds = [];
        foreach ($list as $item) {
            $memberIds[] = $item->member_id;
            $commission = json_decode($item->agent_sale_reward_commision, true);
            if (is_array($commission)) {
                foreach ($commission as $val) {
                    $memberIds[] = $val['member_id'];
                }
            }
        }
        $memberInfos = [];
        // 相关会员的基本信息
        if (count($memberIds)) {
            $listMembers = DB::table('tbl_member')
                ->whereIn('id', $memberIds)
                ->select(['id', 'name','nickname', 'mobile', 'agent_level'])->get();
            $memberInfos = [];
            foreach ($listMembers as $d) {
                $memberInfos[$d->id] = (array)$d;
            }
        }

        //合并会员信息
        $afterSale = new AfterSale();
        foreach ($list as &$item) {
            $memberIds[] = $item->member_id;
            $commission = json_decode($item->agent_sale_reward_commision, true);
            $total_commission = 0;
            $item->agent_sale_reward_commision = [];
            if (is_array($commission)) {
                foreach ($commission as $key => &$val) {
                    $val['nickname'] = $memberInfos[$val['member_id']]['nickname'];
                    $val['name'] = $memberInfos[$val['member_id']]['name'];
                    $val['mobile'] = $memberInfos[$val['member_id']]['mobile'];
                    if (!$val['agent_level']) $val['agent_level'] = $item->agent_level;
                    $val['agent_level_name'] = Constants::getAgentLevelTextForAdmin($val['agent_level']);
                    $val['money'] = moneyCent2Yuan($val['money']);
                    $total_commission = bcadd($total_commission, $val['money'], 2);
                }
                $item->agent_sale_reward_commision = $commission;
                unset($val);
            }
            $item->total_commission = $total_commission;
            if ($item->after_sale_status > -1) $item->after_status_text = Constants::getFrontAfterSaleStatusText($afterSale->getAfterSaleStatus($item->after_sale_status, $item->after_sale_type));
            else $item->after_status_text = '';
            $item->order_money = moneyCent2Yuan($item->order_money);
        }
        unset($item);

        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 查询条件设置
     * @param Builder $query
     * @param array $param
     */
    private static function setQuery($query, array $param)
    {
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('tbl_finance.member_id', intval($param['member_id']));
        }
        // 状态
        if (is_numeric($param['status']) && intval($param['status']) != -1) {
            $query->where('tbl_finance.status', intval($param['status']));
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('tbl_order.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('tbl_order.created_at', '<=', $param['created_at_max']);
        }
        // 指定ID(一般只用在导出时)
        if ($param['ids']) {
            if (!is_array($param['ids'])) $param['ids'] = explode(',', $param['ids']);
            $query->whereIn('tbl_order.id', $param['ids']);
        }
        // 关键词 只接受数字和字母
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            $query->where(function ($query) use ($keyword) {
                $query->orWhere('member.nickname', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $query->orWhere('tbl_order.id', 'like', '%' . trim($keyword) . '%')->orWhere('member.mobile', 'like', '%' . trim($keyword) . '%');
                }
            });
        }
    }
}