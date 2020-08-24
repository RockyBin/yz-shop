<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use YZ\Core\Site\Site;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Order\AfterSale;
use App\Modules\ModuleShop\Libs\Constants;

/**
 * 代理正常订单分红
 */
class AgentOrderCommision
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
            ->where('tbl_order.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_order.has_agent_order_commision', '>', 0);
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        // 排序
        $query->orderBy('tbl_order.created_at', 'desc');
        $query->selectRaw('DISTINCT(tbl_order.id)');
        $query->addSelect('tbl_order.id as order_id', 'tbl_order.created_at', 'tbl_order.after_sale_type', 'tbl_order.after_sale_status', 'tbl_order.money as order_money', 'tbl_order.has_agent_order_commision', 'tbl_order.agent_order_commision');
        $query->forPage($page, $pageSize);
        $list = $query->get();
        $memberIds = [];
        foreach ($list as $item) {
            $memberIds[] = $item->member_id;
            $commission = json_decode($item->agent_order_commision, true);
            if (is_array($commission)) {
                foreach ($commission as $val) {
                    $memberIds[] = $val['member_id'];
                }
            }
        }

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

        // 合并会员信息
        $afterSale = new AfterSale();
        foreach ($list as &$item) {
            $memberIds[] = $item->member_id;
            $commission = json_decode($item->agent_order_commision, true);
            $total_commission = 0;
            $item->agent_order_commision = [];
            if (is_array($commission)) {
                foreach ($commission as $key => &$val) {
                    $val['nickname'] = $memberInfos[$val['member_id']]['nickname'];
                    $val['name'] = $memberInfos[$val['member_id']]['name'];
                    $val['mobile'] = $memberInfos[$val['member_id']]['mobile'];
                    if (!$val['agent_level']) $val['agent_level'] = $memberInfos[$val['member_id']]['agent_level'];
                    $val['agent_level_name'] = Constants::getAgentLevelTextForAdmin($val['agent_level']);
                    $total_commission += intval($val['money']);
                    $val['money'] = moneyCent2Yuan($val['money']);
                }
                $item->agent_order_commision = $commission;
                unset($val);
            }
            $item->total_commission_val = $total_commission;
            $item->total_commission = moneyCent2Yuan($total_commission);
            if ($item->after_sale_status > -1) $item->after_status_text = Constants::getFrontAfterSaleStatusText($afterSale->getAfterSaleStatus($item->after_sale_status, $item->after_sale_type));
            else $item->after_status_text = '';
            $item->order_money = moneyCent2Yuan($item->order_money);
            $item->finance_status = intval($item->has_agent_order_commision) - 1;
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
            $query->where('tbl_order.has_agent_order_commision', intval($param['status']) + 1);
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
        if ($param['keyword'] && preg_match('/^\w+$/i', $param['keyword'])) {
            // order_id的编码为ascii，输入中文会导致SQL出错，这里将中文替换掉
//            $param['keyword'] = preg_replace("/[\x{4e00}-\x{9fa5}]+/u", '', $param['keyword']);
            $keyword = '%' . $param['keyword'] . '%';
            $query->where('tbl_order.id', 'like', $keyword);
//            if(strlen($keyword) > 2) $query->where('tbl_order.id', 'like', $keyword);
        }
    }
}