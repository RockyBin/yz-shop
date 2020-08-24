<?php
/**
 * 区域代理业绩逻辑
 * User: liyaohui
 * Date: 2020/5/29
 * Time: 15:26
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentPerformanceModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\OrderAreaAgentHistoryModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\License\SNUtil;
use YZ\Core\Model\DistrictModel;
use YZ\Core\Model\MemberAddressModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

class AreaAgentPerformance
{
    /**
     * 获取列表
     * @param $params
     * @return array
     */
    public static function getList($params)
    {
        $query = MemberModel::query()
            ->from('tbl_member as member')
            ->leftJoin('tbl_area_agent as agent', 'member.id', 'agent.member_id')
            ->leftJoin('tbl_area_agent_performance as perf', function ($join) use ($params) {
                $join->on('perf.member_id', 'member.id')
                    ->where('perf.agent_id', 0); // 只获取会员总业绩
                // 统计数据 付款后还是维权期后的
                if (isset($params['count_period'])) {
                    $join->where('perf.count_period', $params['count_period']);
                }
                // 业绩时间类型
                if (isset($params['time_type'])) {
                    $join->where('perf.time_type', $params['time_type']);
                }
                // 统计时间
                if (isset($params['date_time'])) {
                    $join->where('perf.date_time', $params['date_time']);
                }
            })
            ->where('member.is_area_agent', AreaAgentConstants::AreaAgentStatus_Active)
            ->where('member.site_id', getCurrentSiteId());
        // 搜索条件
        // 手机号 昵称搜索
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $query->where(function ($query) use ($keyword) {
                $query->where('member.nickname', 'like', $keyword);
                $query->orWhere('member.name', 'like', $keyword);
                $query->orWhere('member.mobile', 'like', $keyword);
            });
        }
        // 区域级别
        if (isset($params['area_type']) && $params['area_type']) {
            $query->where('agent.area_type', $params['area_type']);
        }
        // 代理区域搜索
        if (isset($params['prov']) && $params['prov']) {
            $query->where('agent.prov', $params['prov']);
        }
        if (isset($params['city']) && $params['city']) {
            $query->where('agent.city', $params['city']);
        }
        if (isset($params['district']) && $params['district']) {
            $query->where('agent.district', $params['district']);
        }
        // id搜索 主要用于导出
        if (isset($params['ids']) && is_array($params['ids'])) {
            $query->whereIn('member.id', $params['ids']);
        }

        $page = $params['page'] ?: 1;
        $pageSize = $params['page_size'] ?: 20;
        // 统计数据条数
        $total = (clone $query)->selectRaw('count(distinct member.id) as total')->first();
        $total = $total ? $total['total'] : 0;
        $lastPage = ceil($total / $pageSize);
        // 排序 默认按成为区代时间倒序
        if ($params['order_by']) {
            $field = $params['order_by']['field'] ?: 'money';
            if ($params['order_by']['type'] == 'asc') {
                $query->orderBy($field);
            } else {
                $query->orderByDesc($field);
            }
        } else {
            $query->orderByDesc('member.area_agent_at');
        }
        // 获取所有的 不用分页
        if (!$params['is_all']) {
            $query->forPage($page, $pageSize);
        }
        $list = $query->selectRaw('distinct member.id as member_id')
            ->addSelect([
            'member.nickname',
            'member.mobile as member_mobile',
            'member.headurl',
            'member.name',
            'perf.money',
            'perf.time_type',
            'perf.date_time',
        ])->get();
        if ($list->isNotEmpty()) {
            // 会员id
            $memberIds = $list->pluck('member_id')->toArray();
            // 获取代理的所有区域
            $areaAgents = AreaAgentHelper::getMemberAreaAgentList($memberIds);
            $dateTime = $params['date_time'] ?: '';
            $timeType = isset($params['time_type']) ? $params['time_type'] : 0;
            // 匹配给列表
            foreach ($list as $value) {
                // 代理区域匹配
                $value->area_list = $areaAgents->where('member_id', $value['member_id'])->values()->all();
                $value->money = $value->money ? moneyCent2Yuan($value->money) : 0.00;
                $time = $value->date_time ?: $dateTime;
                $type = $value->time_type ?: $timeType;
                $value->date_time_text = self::getDateText($time, $type);
                $value->member_mobile = Member::memberMobileReplace($value->member_mobile);
            }
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    public static function export($params)
    {
        $exportHeadings = [
            'ID',
            '昵称',
            '姓名',
            '手机号',
            '区域级别',
            '代理区域',
            '区域业绩统计',
            '统计业绩周期'
        ];
        $list = self::getList($params);
        $list = $list['list'];
        $exportData = [];
        if ($list) {
            foreach ($list as $item) {
                // 区域类型文案
                $item['area_type_text'] = AreaAgentConstants::getAreaTypeText($item['area_type']);
                // 地址名称
                $item['area_text'] = $item['prov_text'];
                $item['area_text'] .= $item['city_text'] ? '-'.$item['city_text'] : '';
                $item['area_text'] .= $item['district_text'] ? '-'.$item['district_text'] : '';
                // 统计周期
                $item['time_text'] = $item['date_time_text']['start'] . '-' . $item['date_time_text']['end'];
                $exportData[] = [
                    $item['member_id'],
                    $item['nickname'],
                    $item['name'],
                    "\t" . $item['member_mobile'] . "\t",
                    $item['area_type_text'],
                    $item['area_text'],
                    $item['money'] ?: '0.00',
                    $item['time_text']
                ];
            }
        }
        $fileName = '';
        switch ($params['time_type']) {
            case AreaAgentConstants::AreaAgentPerformanceTimeType_Month:
                $fileName = 'YueDU' . $params['date_time'];
                break;
            case AreaAgentConstants::AreaAgentPerformanceTimeType_Quarter:
                $fileName = 'JiDU' . substr($params['date_time'], 0, 4) . '-' . substr($params['date_time'], -1);
                break;
            case AreaAgentConstants::AreaAgentPerformanceTimeType_Year:
                $fileName = 'NianDu' . $params['date_time'];
                break;
            default:
                throw new \Exception('数据错误，无法导出');
        }
        $fileName .= '-' . date('YmdHis') . '.xlsx';
        $exportObj = new Export(new Collection($exportData), $fileName, $exportHeadings);
        return $exportObj->export();
    }

    /**
     * 获取业绩的日期文案
     * @param $dateTime
     * @param $timeType
     * @return array
     */
    public static function getDateText($dateTime, $timeType)
    {
        switch ($timeType) {
            // 月
            case AreaAgentConstants::AreaAgentPerformanceTimeType_Month:
                $start = date('Y.m.01', strtotime($dateTime));
                // 月最后一天
                $end = date('Y.m.d', strtotime($dateTime . '01' . ' +1months -1days'));
                break;
            // 季度
            case AreaAgentConstants::AreaAgentPerformanceTimeType_Quarter:
                $year = substr($dateTime, 0, 4); // 年
                $quarter = intval(substr($dateTime, -1)); // 第几季度
                $quarter = $quarter < 1 ? 1 : $quarter;
                $quarter = $quarter > 4 ? 4 : $quarter;
                $startMonth = $quarter * 3 -2; // 当前季度开始月份
                $startMonth = $startMonth < 10 ? '0' . $startMonth : $startMonth;
                $endMonth = $quarter * 3; // 当前季度结束月份
                $endMonth = $endMonth < 10 ? '0' . $endMonth : $endMonth;
                $start = "{$year}.{$startMonth}.01";
                $end = date('Y.m.d', strtotime("{$year}{$endMonth}01" . ' +1months -1days'));
                break;
            case AreaAgentConstants::AreaAgentPerformanceTimeType_Year:
                $start = "{$dateTime}.01.01";
                $end = "{$dateTime}.12.31";
                break;
            default:
                $start = "0000.00.00";
                $end = "0000.00.00";
                break;
        }
        return [
            'start' => $start,
            'end' => $end
        ];
    }

    /**
     * 创建订单相关区域业绩
     * @param OrderModel $order 订单记录
     * @param int $countPeriod      统计节点
     * @throws \Exception
     * @return bool
     */
    public static function createAreaAgentPerformance(OrderModel $order, $countPeriod)
    {
        if (!$order['address_id']) return false;
        // 版本权限
        $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
        if (!$sn->hasPermission(Constants::FunctionPermission_ENABLE_AREA_AGENT)) {
            return false;
        }
        // 获取基础配置
        $setting = AreaAgentBaseSetting::getCurrentSiteSetting();
        if (!$setting['status']) {
            return false;
        }
        $snapshot = json_decode($order['snapshot'], true);
        if ($snapshot && $snapshot['address']) {
            $address = $snapshot['address'];
        } else {
            $address = MemberAddressModel::query()
                ->where('site_id', $order['site_id'])
                ->where('id', $order['address_id'])
                ->first();
        }
        // 获取订单的区域代理列表
        $areaAgentList = AreaAgentHelper::getAreaAgentListByAddress($address);
        self::buildOrderPerformance($order, $areaAgentList, $setting, $countPeriod);
        // 只在付款后记录
        if ($countPeriod == 0) {
            self::buildOrderHistory($order, $areaAgentList, $setting);
        }
    }

    /**
     * 生成或累加区域代理业绩 每个日期会生成2条记录 1条是对会员id的 1条是对具体区域
     * @param OrderModel $order 订单记录
     * @param \Illuminate\Database\Eloquent\Collection|static[] $areaAgentList    当前订单的区域代理列表
     * @param \Illuminate\Database\Eloquent\Collection|static[] $setting          当前站点的区域设置
     * @param int $countPeriod      统计节点
     * @throws \Exception
     */
    public static function buildOrderPerformance(OrderModel $order, $areaAgentList, $setting, $countPeriod)
    {
        if ($areaAgentList->count()) {
            $insertData = [];
            $updateData = [];
            $order = $order->toArray();
            foreach ($areaAgentList as $agent) {
                $orderData = $order;
                // 开启了自购不算入业绩
                if ($orderData['member_id'] == $agent['member_id'] && $setting['internal_purchase_performance'] == 0) {
                    continue;
                }
                // 查找是否已经生成过
                $year = self::getCurrentYear();
                $month = self::getCurrentMonth();
                $quarter = self::getCurrentQuarter();
                // 减去运费
//                $orderData['money'] -= $orderData['freight'];
                // 维权期后的 要减去售后的金额
                $orderData['money'] = $countPeriod == 1 ? $orderData['money'] + $orderData['after_sale_money'] : $orderData['money'];
                // 查找出来当前代理当前时间 所有已有的数据
                $performanceList = AreaAgentPerformanceModel::query()
                    ->where('site_id', $orderData['site_id'])
                    ->where('member_id', $agent['member_id'])
                    ->where('count_period', $countPeriod)
                    ->whereIn('date_time', [$year, $month, $quarter])
                    ->get();
                // 构造一下三个业绩的时间对应关系
                $dateTime = [
                    AreaAgentConstants::AreaAgentPerformanceTimeType_Month => $month,
                    AreaAgentConstants::AreaAgentPerformanceTimeType_Year => $year,
                    AreaAgentConstants::AreaAgentPerformanceTimeType_Quarter => $quarter
                ];
                // 循环构造三个业绩 年 季度 月
                foreach ($dateTime as $type => $time) {
                    // 当前区域的数据
                    $currentAreaData = $performanceList->where('time_type', $type)->where('agent_id', '>', 0)->first();
                    $areaData = self::insertOrUpdatePerformance($currentAreaData, $orderData, $agent, $type, $time, $countPeriod, 1);
                    if ($areaData['update']) {
                        $updateData[] = $areaData['update'];
                    } else {
                        $insertData[] = $areaData['insert'];
                    }

                    // 当前会员的数据
                    $currentMemberData = $performanceList->where('time_type', $type)->where('agent_id', 0)->first();
                    $memberData = self::insertOrUpdatePerformance($currentMemberData, $orderData, $agent, $type, $time, $countPeriod, 0);
                    if ($memberData['update']) {
                        $updateData[] = $memberData['update'];
                    } else {
                        $insertData[] = $memberData['insert'];
                    }
                }
            }
            if ($insertData) {
                AreaAgentPerformanceModel::query()->insert($insertData);
            }
            if ($updateData) {
                $performance = new AreaAgentPerformanceModel();
                $performance->updateBatch($updateData);
            }
        }
    }

    /**
     * 新增或更新单条业绩
     * @param $originData 已有数据
     * @param $order    订单
     * @param $agent    区域代理
     * @param $timeType 业绩时间类型
     * @param $time     业绩统计时间
     * @param $countPeriod  付款后还是维权期后
     * @param $dataType 0=属于会员的总业绩 1=代理区域的业绩
     * @return array
     */
    public static function insertOrUpdatePerformance($originData, $order, $agent, $timeType, $time, $countPeriod, $dataType)
    {
        $now = date('Y-m-d H:i:s');
        $returnData = [
            'update' => [],
            'insert' => []
        ];
        if ($originData) {
            $returnData['update'] = [
                'id' => $originData['id'],
                'money' => $originData['money'] + $order['money'],
                'updated_at' => $now
            ];
        } else {
            $areaId = 0;
            $agentId = 0;
            // 属于区域的业绩
            if ($dataType == 1) {
                $agentId = $agent['id'];
                switch ($agent['area_type']) {
                    case AreaAgentConstants::AreaAgentLevel_Province:
                        $areaId = $agent['prov'];
                        break;
                    case AreaAgentConstants::AreaAgentLevel_City:
                        $areaId = $agent['city'];
                        break;
                    case AreaAgentConstants::AreaAgentLevel_District:
                        $areaId = $agent['district'];
                        break;
                }
            }
            $returnData['insert'] = [
                'site_id' => $order['site_id'],
                'member_id' => $agent['member_id'],
                'agent_id' => $agentId,
                'area_id' => $areaId,
                'money' => $order['money'],
                'count_period' => $countPeriod,
                'time_type' => $timeType,
                'date_time' => $time,
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        return $returnData;
    }

    /**
     * 记录区域代理订单的订单历史
     * @param OrderModel $order
     * @param \Illuminate\Database\Eloquent\Collection|static[] $areaAgentList
     * @param \Illuminate\Database\Eloquent\Collection|static[] $setting
     */
    public static function buildOrderHistory(OrderModel $order, $areaAgentList, $setting)
    {
        $history = [];
       foreach ($areaAgentList  as $agent) {
           $areaId = AreaAgentHelper::getAreaAgentAreaId($agent);
           // 如果找不到对应的区域 不去插入
           if (!$areaId) continue;
           $history[] = [
               'site_id' => $order->site_id,
               'order_id' => $order->id,
               'member_id' => $agent['member_id'],
               'agent_id' => $agent['id'],
               'area_type' => $agent['area_type'],
               'area_id' => $areaId,
               // 是否计算到业绩
               'calc_performance' => $order['member_id'] == $agent['member_id'] && $setting['internal_purchase_performance'] == 0 ? 0 : 1
           ];
       }
       if ($history) {
           OrderAreaAgentHistoryModel::query()->insert($history);
       }
    }

    /**
     * 获取区代当前 月 季度 年的业绩
     * @param int $memberId     会员id
     * @param int $countPeriod  0 付款后 1 维权期后
     * @param bool $format      是否转为远
     * @return array
     */
    public static function getAreaAgentCurrentPerformance($memberId, $countPeriod, $format = false)
    {
        // 当前月
        $monthTime = self::getCurrentMonth();
        $monthMoney = self::getAreaAgentPerformance(
            $memberId,
            AreaAgentConstants::AreaAgentPerformanceTimeType_Month,
            $monthTime,
            $countPeriod
        );
        // 当前季度
        $quarterTime = self::getCurrentQuarter();
        $quarterMoney = self::getAreaAgentPerformance(
            $memberId,
            AreaAgentConstants::AreaAgentPerformanceTimeType_Quarter,
            $quarterTime,
            $countPeriod
        );
        // 当前年
        $yearTime = self::getCurrentYear();
        $yearMoney = self::getAreaAgentPerformance(
            $memberId,
            AreaAgentConstants::AreaAgentPerformanceTimeType_Year,
            $yearTime,
            $countPeriod
        );
        return [
            'month_money' => $format ? moneyCent2Yuan($monthMoney) : $monthMoney,
            'quarter_money' => $format ? moneyCent2Yuan($quarterMoney) : $quarterMoney,
            'year_money' => $format ? moneyCent2Yuan($yearMoney) : $yearMoney
        ];
    }

    /**
     * 获取区代的业绩数据
     * @param $memberId
     * @param $timeType
     * @param $time
     * @param bool $getTotal
     * @return array
     */
    public static function getAreaAgentPerformanceData($memberId, $timeType, $time, $getTotal = false)
    {
        $setting = AreaAgentBaseSetting::getCurrentSiteSetting();
        $data = [
            'money' => self::getAreaAgentPerformance($memberId, $timeType, $time, $setting['commision_grant_time']),
            'order_num' => self::getAreaAgentPerformanceOrderNum($memberId, $timeType, $time, $setting)
        ];
        $data['money'] = moneyCent2Yuan($data['money']);
        if ($getTotal) {
            $data['total_money'] = self::getAreaAgentAllPerformance($memberId, $setting['commision_grant_time']);
            $data['total_money'] = moneyCent2Yuan($data['total_money']);
        }
        return $data;
    }

    /**
     * 获取区域代理总业绩
     * @param $memberId     会员id
     * @param $countPeriod  0=付款后 1=维权期后
     * @return int
     */
    public static function getAreaAgentAllPerformance($memberId, $countPeriod)
    {
        $performance = AreaAgentPerformanceModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('agent_id', 0)
            ->where('member_id', $memberId)
            ->where('count_period', $countPeriod)
            ->where('time_type', AreaAgentConstants::AreaAgentPerformanceTimeType_Year)
            ->sum('money');
        return $performance ?: 0;
    }

    /**
     * 获取区代的业绩统计
     * @param int $memberId     会员id
     * @param int $timeType     时间类型 0 月 1 季度 2 年
     * @param int $time         具体时间
     * @param int $countPeriod  0=付款后 1=维权期后
     * @return int|mixed
     */
    public static function getAreaAgentPerformance($memberId, $timeType, $time, $countPeriod)
    {
        $performance = AreaAgentPerformanceModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('agent_id', 0)
            ->where('member_id', $memberId)
            ->where('time_type', $timeType)
            ->where('date_time', $time)
            ->where('count_period', $countPeriod)
            ->first();
        return $performance ? $performance['money'] : 0;
    }

    /**
     * 获取会员的区域订单数
     * @param $memberId 会员id
     * @param $timeType 时间类型 0 月 1 季度 2 年
     * @param $time     具体时间
     * @param $setting  区代设置
     * @return int
     */
    public static function getAreaAgentPerformanceOrderNum($memberId, $timeType, $time, $setting)
    {
        $queryTime = self::getDateText($time, $timeType);
        $start = $queryTime['start'];
        $end = $queryTime['end'] . ' 23:59:59';
        if ($setting['commision_grant_time'] == 0) {
            $orderStatus = Constants::getPaymentOrderStatus();
            $timeField = 'o.pay_at';
        } else {
            $orderStatus = [Constants::OrderStatus_OrderFinished];
            $timeField = 'o.end_at';
        }
        $orderNum = OrderAreaAgentHistoryModel::query()
            ->from('tbl_order_area_agent_history as oh')
            ->join('tbl_order as o', 'o.id', 'oh.order_id')
            ->where('oh.site_id', getCurrentSiteId())
            ->where('oh.member_id', $memberId)
            ->where($timeField, '>=', $start)
            ->where($timeField, '<=', $end)
            ->whereIn('o.status', $orderStatus);
        if ($setting['internal_purchase_performance'] == 0) {
            $orderNum->where('calc_performance', 1);
        }
        $orderNum = $orderNum->count();
        return $orderNum ?: 0;
    }

    /**
     * 获取当前的年
     * @return false|string
     */
    public static function getCurrentYear()
    {
        return date('Y');
    }

    /**
     * 获取当前的年月
     * @return false|string
     */
    public static function getCurrentMonth()
    {
        return date('Ym');
    }

    /**
     * 获取当前季度
     * @param int $month 月份
     * @return string
     */
    public static function getCurrentQuarter($month = 0)
    {
        $month = $month > 0 ? $month : date('n');
        $quarter = intval(ceil($month/3));
        return date('Y') . $quarter;
    }
}