<?php
namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardRuleModel;
use App\Modules\ModuleShop\Libs\Model\StatisticsModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;

/**
 * 经销商业绩
 */
class DealerPerformance
{
    /**
     * 获取业绩统计
     * @param $params
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws \Exception
     */
    public static function getPerformanceList($params, $page = 1, $pageSize = 20)
    {
        $showAll = $params['is_all'] ? true : false;
        $siteId = Site::getCurrentSite()->getSiteId();
        $query = DB::query()->from('tbl_dealer')
            ->leftJoin('tbl_member', 'tbl_dealer.member_id', 'tbl_member.id')
            ->leftJoin('tbl_member as parent', 'parent.id', 'tbl_member.dealer_parent_id')
            ->where('tbl_dealer.site_id', $siteId);
        // 代理状态
        if (is_numeric($params['status'])) {
            $query->where('tbl_dealer.status', $params['status']);
        }
        // 代理等级
        if (is_numeric($params['dealer_level'])) {
            $dealerLevel = intval($params['dealer_level']);
            if ($dealerLevel >= 0) {
                $query->where('tbl_member.dealer_level', $params['dealer_level']);
            }
        }
        // 上级领导
        if (is_numeric($params['dealer_parent_id'])) {
            $dealerParentId = intval($params['dealer_parent_id']);
            if ($dealerParentId >= 0) {
                $query->where('tbl_member.dealer_parent_id', $params['dealer_parent_id']);
            } else if ($dealerParentId == -2) {
                $query->where('tbl_member.dealer_parent_id', '>', 0);
            }
        }
        // 关键词
        if ($params['keyword']) {
            $keyword = '%' . $params['keyword'] . '%';
            $query->where(function ($query) use ($keyword) {
                $query->where('tbl_member.nickname', 'like', $keyword)
                    ->orWhere('tbl_member.mobile', 'like', $keyword)
                    ->orWhere('tbl_member.name', 'like', $keyword);
            });
        }
        // 业绩范围
        if ($params['performance_min']) {
            $query->where('performance', '>=', moneyYuan2Cent($params['performance_min']));
        }
        if ($params['performance_max']) {
            $query->where('performance', '<=', moneyYuan2Cent($params['performance_max']));
        }
        // 指定的会员id
        if ($params['ids']) {
            $memberIds = myToArray($params['ids']);
            if ($memberIds) {
                $showAll = true;
                $query->whereIn('tbl_dealer.member_id', $memberIds);
            }
        }
        // 业绩统计
        $period = 0;
        if (is_numeric($params['period'])) {
            $period = intval($params['period']);
        }
        $countType = 0;
        if (is_numeric($params['count_type'])) {
            $countType = intval($params['count_type']);
        }
        $countYear = 0;
        if (is_numeric($params['count_year'])) {
            $countYear = intval($params['count_year']);
        }
        $countNum = 0;
        if (is_numeric($params['count_num'])) {
            $countNum = intval($params['count_num']);
        }

        if($countType == Constants::DealerPerformanceRewardPeriod_Month){ //月业绩
            $query->leftJoin('tbl_statistics as stat', function ($join) use ($countYear,$countNum) {
                $join->on('stat.member_id', '=', 'tbl_member.id')
                    ->where('stat.type', Constants::Statistics_MemberCloudStockPerformancePaidMonth)
                    ->where('stat.time',$countYear.str_pad($countNum,2,'0',STR_PAD_LEFT))
                    ->where('stat.dealer_parent_id', -1);
            });
        } else if($countType == Constants::DealerPerformanceRewardPeriod_Quarter){ //季度业绩
            $query->leftJoin('tbl_statistics as stat', function ($join) use ($countYear,$countNum) {
                $join->on('stat.member_id', '=', 'tbl_member.id')
                    ->where('stat.type', Constants::Statistics_MemberCloudStockPerformancePaidQuarter)
                    ->where('stat.time', $countYear.str_pad($countNum,2,'0',STR_PAD_LEFT))
                    ->where('stat.dealer_parent_id', -1);
            });
        } else if($countType == Constants::DealerPerformanceRewardPeriod_Year){ //年度业绩
            $query->leftJoin('tbl_statistics as stat', function ($join) use ($countYear,$countNum) {
                $join->on('stat.member_id', '=', 'tbl_member.id')
                    ->where('stat.type', Constants::Statistics_MemberCloudStockPerformancePaidYear)
                    ->where('stat.time', $countYear)
                    ->where('stat.dealer_parent_id', -1);
            });
        } else {
            throw new \Exception('请选择要统计的业绩类型');
        }

        $query->addSelect("stat.value as performance");
        // 统计数量
        $total = $query->count();
        $query->addSelect('tbl_dealer.member_id', 'tbl_member.dealer_level as member_dealer_level','tbl_member.dealer_hide_level as member_dealer_hide_level', 'tbl_member.nickname as member_nickname','tbl_member.name as member_name', 'tbl_member.mobile as member_mobile', 'tbl_member.headurl as member_headurl', 'tbl_member.dealer_parent_id as dealer_parent_id', 'parent.dealer_level as dealer_parent_level', 'parent.nickname as dealer_parent_nickname', 'parent.mobile as dealer_parent_mobile', 'parent.headurl as dealer_parent_headurl');
        // 排序
        if ($params['order_by'] == 'performance') {
            if ($params['order_sort'] == 'asc') {
                $query->orderBy('performance');
            } else {
                $query->orderByDesc('performance');
            }
        } else {
            $query->orderByDesc('tbl_dealer.passed_at');
        }
        // 分页
        if ($showAll) {
            $last_page = 1;
        } else {
            $query->forPage($page, $pageSize);
            $last_page = ceil($total / $pageSize);
        }
        $list = $query->get();
        // 返回值
        $timeParam = static::parseTime($countType, $countYear, $countNum);
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
            'time_start' => $timeParam['start'],
            'time_end' => $timeParam['end'],
            'time_sign' => $timeParam['sign']
        ];
    }
    /**
     * 输出数据转换
     * @param $item
     */
    public static function convertData(&$item)
    {
        if ($item) {
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
            $item['performance_money'] = moneyCent2Yuan($item['performance_money']);
            $item['total_performance_money'] = moneyCent2Yuan($item['total_performance_money']);
            // 统计时间段
            $item['period_start'] = '';
            $item['period_end'] = '';
            if ($item['period']) {
                list($givePeriod, $year, $num) = explode('_', $item['period']);
                $timeParam = self::parseTime($givePeriod, $year, $num);
                $item['period_start'] = $timeParam['start'];
                $item['period_end'] = $timeParam['end'];
            }
            // 头像
            if($item['member_headurl'] && !preg_match('/^(http)/i',$item['member_headurl'])){
                $item['member_headurl'] = Site::getSiteComdataDir().$item['member_headurl'];
            }
            // 状态
            $item['status_text'] = DealerReward::getRewardStatusText($item['status']);
        }
    }
    /**
     * 根据参数计算 开始时间 和 结束时间
     * @param int $givePeriod 方式：0=月，1=季度，2=年
     * @param int $year 年份
     * @param int $num 第几季度 或 第几个月
     * @return array
     */
    public static function parseTime($givePeriod = 0, $year = 0, $num = 0)
    {
        $givePeriod = intval($givePeriod);
        $num = intval($num);
        $year = intval($year);
        if ($num <= 0) $num = 1;
        if ($year <= 0) $year = intval(date('Y'));

        if ($givePeriod == Constants::DealerPerformanceRewardPeriod_Year) {
            // 按年
            $timeStart = $year . '-01-01';
            $timeEnd = $year . '-12-31';
        } else if ($givePeriod == Constants::DealerPerformanceRewardPeriod_Quarter) {
            // 按季度
            $timeStart = date('Y-m-d', strtotime($year . '-' . (1 + (intval($num) - 1) * 3) . '-01'));
            $endMonth = intval($num) * 3;
            $timeEnd = date('Y-m-d', strtotime($year . '-' . $endMonth . '-' . date('t', strtotime($year . '-' . $endMonth))));
        } else {
            // 按月
            $givePeriod = 0;
            $timeStart = date('Y-m-d', strtotime($year . '-' . $num . '-01'));
            $timeEnd = date('Y-m-d', strtotime($year . '-' . $num . '-' . date('t', strtotime($year . '-' . $num))));
        }
        return [
            'start' => $timeStart,
            'end' => $timeEnd,
            'start_time' => $timeStart . ' 00:00:00',
            'end_time' => $timeEnd . ' 23:59:59',
            'sign' => $givePeriod . '-' . $year . '-' . $num,
        ];
    }

    /**
     * 获取会员的个人业绩
     * @param int $memberId
     * @param array $params
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getMemberPerformance($memberId, $params)
    {
        $type = $params['type'] ?: 0;
        $year = $params['year'] ?: date('Y');
        $num = $params['num'] ?: 1;
        $parseTime = self::parseTime($type, $year, $num);
        $data = CloudStockPurchaseOrderModel::query()->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->whereIn('status', Constants::getCloudStockPurchaseOrderPayStatus())
            ->where('created_at', '>=', $parseTime['start_time'])
            ->where('created_at', '<=', $parseTime['end_time'])
            ->selectRaw('sum(total_money) as money,count(*) as order_count')
            ->first();
        $data['money'] = moneyCent2Yuan($data['money']);
        return $data;
    }

    /**
     * 获取下级团队业绩
     * @param int $memberId 会员id
     * @param array $params 参数
     * @param int $page     当前页
     * @param int $pageSize 每页条数
     * @return array
     */
    public static function getMemberSubPerformanceList($memberId, $params, $page = 1, $pageSize = 20)
    {
        $type = $params['type'] ?: 0;
        $year = $params['year'] ?: date('Y');
        $num = $params['num'] ?: 1;
        $getCount = $params['get_count'] ? 1 : 0;
        $parseTime = self::parseTime($type, $year, $num);

        if ($type == 0 || $type == 1) {
            $month = intval($num) < 10 ? '0' . intval($num) : $num;
        } else {
            $month = '';
        }
        $time = $year . $month;
        $statisticsType = self::getStatisticsType($type);
        $query = StatisticsModel::query()
            ->from('tbl_statistics as s')
            ->where('s.site_id', getCurrentSiteId())
            ->where('s.dealer_parent_id', $memberId)
            ->where('s.type', $statisticsType)
            ->where('s.time', $time);
        // 统计一下总金额和记录条数
        $total = 0;
        $data = [];
        if ($getCount) {
            $count = $query->selectRaw('count(*) as total, sum(s.value) as total_money')->first();
            $data['total_money'] = moneyCent2Yuan($count->total_money);
            $total = $count->total;
            // 获取订单条数
            if ($total) {
                $data['total_order'] = DB::table('tbl_cloudstock_purchase_order_history as oh')
                    ->join('tbl_cloudstock_purchase_order as o', 'o.id', 'oh.order_id')
                    ->whereIn('o.status', Constants::getCloudStockPurchaseOrderPayStatus())
                    ->where('oh.member_id', $memberId)
                    ->where('oh.level', 1)
                    ->where('oh.type', 1)
                    ->where('o.created_at', '>=', $parseTime['start_time'])
                    ->where('o.created_at', '<=', $parseTime['end_time'])
                    ->count();
            }
        } else {
            $count = $query->selectRaw('count(*) as total')->first();
            $total = $count->total;
        }

        $list = $query->leftJoin('tbl_member as m', 'm.id', 's.member_id')
            ->leftJoin('tbl_dealer_level as dl', 'dl.id', 'm.dealer_level')
            ->select(['s.value as money', 'm.nickname', 'm.mobile', 'm.headurl', 'dl.name as level_name'])
            ->forPage($page, $pageSize)
            ->get();
        if ($total) {
            // 列表和分页处理
            $lasePage = ceil($count->total / $pageSize);
            foreach ($list as &$item) {
                $item['money'] = moneyCent2Yuan($item['money']);
            }
            // 查找订单数
        } else {
            $lasePage = 0;
            $list = [];
        }

        return array_merge($data, [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lasePage,
            'list' => $list
        ]);
    }

    /**
     * 根据月 季度 年 去匹配业绩统计表的类型值
     * @param $type 0 月 1 季度 2 年
     * @return int
     */
    public static function getStatisticsType($type)
    {
        switch (intval($type)) {
            case 0:
                return Constants::Statistics_MemberCloudStockPerformancePaidMonth;
            case 1:
                return Constants::Statistics_MemberCloudStockPerformancePaidQuarter;
            case 2:
                return Constants::Statistics_MemberCloudStockPerformancePaidYear;
        }
    }
}