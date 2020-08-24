<?php

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardRuleModel;
use App\Modules\ModuleShop\Libs\Model\DealerRewardModel;
use App\Modules\ModuleShop\Libs\Model\StatisticsModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use \YZ\Core\Constants as CoreConstatns;

/**
 * 经销商业绩奖励
 */
class DealerPerformanceReward implements IDealerReward
{
    private $_model = null;

    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            $this->findById($idOrModel);
        } else {
            $this->init($idOrModel);
        }
    }

    /**
     * 添加数据
     * @param array $param
     * @param bool $reload
     * @return bool|mixed
     */
    public function add(array $param, $reload = false)
    {
        if ($param) {
            $siteId = Site::getCurrentSite()->getSiteId();
            $time = date('Y-m-d H:i:s');
            $rewardParam = [
                'site_id' => $siteId,
                'member_id' => $param['member_id'],
                'type' => Constants::DealerRewardType_Performance,
                'reward_money' => $param['reward_money'],
                'pay_member_id' => $param['pay_member_id'],
                'about' => $param['about'],
                'created_at' => $time,
                'updated_at' => $time
            ];
            if (array_key_exists('status', $param) && intval($param['status']) == Constants::DealerRewardStatus_Active) {
                $param['verify_at'] = $time;
            }
            // 先生成主记录
            $reward = new DealerRewardModel();
            $reward->fill($rewardParam)->save();
            // 根据主id 生成详细记录
            $performanceParam = [
                'site_id' => $siteId,
                'member_id' => $param['member_id'],
                'member_dealer_level' => $param['member_dealer_level'],
                'reward_money' => $param['reward_money'],
                'performance_money' => $param['performance_money'],
                'total_performance_money' => $param['total_performance_money'],
                'period' => $param['period'],
                'reward_id' => $reward->id,
            ];
            DealerPerformanceRewardModel::query()->insert($performanceParam);
            if ($reload) {
                $this->findById($reward->id);
            }
            return $reward->id;
        } else {
            return false;
        }
    }

    /**
     * 修改数据
     * @param array $param
     * @param bool $reload
     * @return bool
     */
    public function edit(array $param, $reload = false)
    {
        if ($this->checkExist()) {
            $data = [];
            if (array_key_exists('status', $param)) {
                $status = intval($param['status']);
                if ($status == Constants::DealerRewardStatus_Active) {
                    $data['verify_at'] = date('Y-m-d H:i:s');
                    $data['status'] = Constants::DealerRewardStatus_Active;
                } else if ($status == Constants::DealerRewardStatus_RejectReview) {
                    $data['reason'] = $param['reason'];
                    $data['status'] = Constants::DealerRewardStatus_RejectReview;
                    $data['verify_at'] = date('Y-m-d H:i:s');
                } else if ($status == Constants::DealerRewardStatus_WaitReview) {
                    $data['status'] = Constants::DealerRewardStatus_WaitReview;
                    $data['exchange_at'] = date('Y-m-d H:i:s');
                }
            }
            if (!$data) {
                return false;
            }
            $model = DealerRewardModel::query()->where('id', $this->_model->id)->first();
            $model->fill($data)->save();
            if ($reload) {
                $this->findById($this->_model->id);
            } else {
                foreach ($data as $key => $value) {
                    $this->_model->$key = $value;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 兑换奖金
     * @return mixed|void
     * @throws \Exception
     */
    public function exchange()
    {
        $this->edit(['status' => Constants::DealerRewardStatus_WaitReview]);
        $dealerPerformanceRewardSetting = DealerPerformanceRewardSetting::getCurrentSiteSetting();
        // 如果是自动审核的 并且审核人是公司的 自动审核
        if ($dealerPerformanceRewardSetting->auto_check && $this->getModel()->pay_member_id == 0) {
            $this->pass();
        }
    }

    /**
     * 审核通过
     * @throws \Exception
     */
    public function pass()
    {
        if ($this->checkExist()) {
            // 如果是待审核的
            if (intval($this->getModel()->status) == Constants::DealerRewardStatus_WaitReview) {
                $model = $this->getModel();
                $orderId = self::buildFinanceOrderId($model->period);
                $financeExist = FinanceModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('member_id', $model->member_id)
                    ->where('type', CoreConstatns::FinanceType_CloudStock)
                    ->where('sub_type', CoreConstatns::FinanceSubType_DealerCommission_Performance)
                    ->where('order_id', $orderId)
                    ->count();
                if ($financeExist == 0) {
                    // 上级支付时 要从上级账户减去财务
                    if ($model->pay_member_id > 0) {
                        // 检测余额是否足够
                        $blance = FinanceHelper::getMemberBalance($model->pay_member_id);
                        if ($blance < $model->reward_money) {
                            throw new \Exception('您的余额不足，不可通过审核！请充值后再进行审核');
                        }
                        // 获取会员名称 缓存信息用
                        $subNickname = MemberModel::query()
                            ->where('site_id', $model->site_id)
                            ->where('id', $model->member_id)
                            ->value('nickname');
                        $insertOutFinanceData = self::buildOutFinanceData($model, $subNickname);
                        if ($insertOutFinanceData) {
                            $financeModel = new FinanceModel();
                            $financeModel->fill($insertOutFinanceData);
                            $financeModel->save();
                        }
                    }
                    $insertFinanceData = self::buildFinanceData($model);
                    if ($insertFinanceData) {
                        $financeModel = new FinanceModel();
                        $financeModel->fill($insertFinanceData);
                        $financeModel->save();
                        // 发送通知
                        //MessageNoticeHelper::sendMessageAgentCommission($financeModel);
                    }
                    $this->edit([
                        'status' => Constants::DealerRewardStatus_Active
                    ], true);
                }
            }
        }
    }

    /**
     * 审核不通过
     * @param string $reason
     */
    public function reject($reason = '')
    {
        if ($this->checkExist() && intval($this->getModel()->status) == Constants::DealerRewardStatus_WaitReview) {
            $this->edit([
                'reason' => $reason,
                'status' => Constants::DealerRewardStatus_RejectReview
            ], true);
        }
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        return $this->_model && $this->_model->id ? true : false;
    }

    /**
     * 返回模型数据
     * @return bool|null
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 初始化
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            $this->_model = $model;
        }
    }

    /**
     * 根据id查找
     * @param $id
     */
    private function findById($id)
    {
        if ($id) {
            $model = DealerRewardModel::query()
                ->from('tbl_dealer_reward as dr')
                ->where('dr.site_id', getCurrentSiteId())
                ->where('dr.id', $id)
                ->join('tbl_dealer_performance_reward as dpr', 'dpr.reward_id', 'dr.id')
                ->select([
                    'dr.*',
                    'dpr.member_dealer_level',
                    'dpr.member_dealer_hide_level',
                    'dpr.performance_money',
                    'dpr.period'
                ])
                ->first();
            // 获取等级名称
            $levelIds = [$model->member_dealer_level];
            if ($model->member_dealer_hide_level) {
                $levelIds[] = $model->member_dealer_hide_level;
            }
            $levelNames = DealerLevelModel::query()->where('site_id', $model->site_id)
                ->whereIn('id', $levelIds)
                ->pluck('name', 'id')->toArray();
            if ($levelNames) {
                $model['member_dealer_level_name'] = $levelNames[$model->member_dealer_level];
                $model['member_dealer_hide_level_name'] = $levelNames[$model->member_dealer_hide_level];
            }
            $this->init($model);
        }
    }

    /**
     * 列表
     * @param array $param
     * @return array
     */
    public static function getList(array $param)
    {
        $showAll = $param['is_all'] ? true : false;
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;

        $query = DealerRewardModel::query()
            ->from('tbl_dealer_reward as dr')
            ->join('tbl_dealer_performance_reward as dpr', 'dpr.reward_id', 'dr.id')
            ->leftJoin('tbl_member as m', 'm.id', 'dr.member_id')
            ->leftJoin('tbl_member as pm', 'pm.id', 'dr.pay_member_id')
//            ->leftJoin('tbl_dealer_level as master_level', 'master_level.id', 'dpr.member_dealer_level')
//            ->leftJoin('tbl_dealer_level as hide_level', 'hide_level.id', 'dpr.member_dealer_hide_level')
            ->where('dr.site_id', getCurrentSiteId());

        // 搜索条件
        self::setQuery($query, $param);
        // 指定数据
        if ($param['ids']) {
            $ids = myToArray($param['ids']);
            if ($ids) {
                $showAll = true;
                $query->whereIn('dr.id', $ids);
            }
        }
        // 总数据量
        $total = $query->count();
        // 排序
        if ($param['order_by'] && Schema::hasColumn('tbl_dealer_reward', $param['order_by'])) {
            if ($param['order_by_asc']) {
                $query->orderBy('dr.' . $param['order_by']);
            } else {
                $query->orderByDesc('dr.' . $param['order_by']);
            }
        } else {
            $query->orderByDesc('dr.id');
        }
        $query->addSelect('dr.*');
        $query->addSelect([
            'm.nickname as member_nickname',
            'm.name as member_name',
            'm.id as member_id',
            'm.mobile as member_mobile',
            'm.headurl as member_headurl',
            'pm.nickname as pay_member_nickname',
            'pm.name as pay_member_name',
            'pm.mobile as pay_member_mobile',
//            'master_level.name as dealer_level_name',
//            'hide_level.name as dealer_hide_level_name',
            'dpr.period',
            'dpr.performance_money',
            'dpr.total_performance_money'
        ]);
        if ($showAll) {
            $last_page = 1;
        } else {
            $query->forPage($page, $pageSize);
            $last_page = ceil($total / $pageSize);
        }
        $list = $query->get();
        foreach ($list as &$item) {
            if (!$item['pay_member_id']) {
                $item['pay_member_nickname'] = '公司';
                $item['pay_member_mobile'] = '';
            }
            $about = json_decode($item['about'], true);
            $item['dealer_level_name'] = $about['member_dealer_level_name'] ?: '';
            $item['dealer_hide_level_name'] = $about['member_dealer_hide_level_name'] ?: '';
            DealerPerformance::convertData($item);
        }
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
    private static function setQuery(Builder $query, array $param)
    {
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('dr.member_id', intval($param['member_id']));
        }
        // 代理等级
        if (is_numeric($param['member_dealer_level'])) {
            if (intval($param['member_dealer_level']) != -1) {
                $query->where(function ($q) use ($param) {
                    $q->where('dpr.member_dealer_level', $param['member_dealer_level'])
                        ->orWhere('dpr.member_dealer_hide_level', $param['member_dealer_level']);
                });
            }
        }
        // 时期
        if ($param['period']) {
            $query->where('dpr.period', intval($param['period']));
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('dr.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('dr.created_at', '<=', $param['created_at_max']);
        }
        // 状态
        if (is_numeric($param['status'])) {
            if (intval($param['status']) != -9) {
                $query->where('dr.status', intval($param['status']));
            }
        }
        // 关键词
        if ($param['keyword']) {
            $keyword = '%' . $param['keyword'] . '%';
            switch (true) {
                case $param['keyword_type'] == 1:
                    $query->where(function (Builder $subQuery) use ($keyword) {
                        $subQuery->where('m.nickname', 'like', $keyword)
                            ->orWhere('m.mobile', 'like', $keyword)
                            ->orWhere('m.name', 'like', $keyword);;
                    });
                    break;
                case $param['keyword_type'] == 2:
                    $query->where(function (Builder $subQuery) use ($keyword) {
                        $subQuery->where('pm.nickname', 'like', $keyword)
                            ->orWhere('pm.mobile', 'like', $keyword)
                            ->orWhere('pm.name', 'like', $keyword);;
                    });
                    break;
                default:
                    $query->where(function (Builder $subQuery) use ($keyword) {
                        $subQuery->where('m.nickname', 'like', $keyword)
                            ->orWhere('m.mobile', 'like', $keyword)
                            ->orWhere('m.name', 'like', $keyword);;
                    });
                    break;

            }


        }
        // 支付奖金
        if (isset($param['payer']) && $param['payer'] != -1) {
            $payer = intval($param['payer']);
            if ($payer > 0) {
                $query->where('pay_member_id', '>', 0);
            } else {
                $query->where('pay_member_id', 0);
            }
        }
    }

    /**
     * 生成财务订单id
     * @param $period
     * @return string
     */
    public static function buildFinanceOrderId($period)
    {
        return 'JXSYJJ_' . $period;
    }

    /**
     * 生成财务数据
     * @param array $reward
     * @return array|null
     * @throws \Exception
     */
    public static function buildFinanceData($reward)
    {
        if (!$reward) return null;
        $financeData = [
            'site_id' => Site::getCurrentSite()->getSiteId(),
            'member_id' => $reward['member_id'],
            'type' => CoreConstatns::FinanceType_CloudStock,
            'sub_type' => CoreConstatns::FinanceSubType_DealerCommission_Performance,
            'in_type' => CoreConstatns::FinanceInType_Commission,
            'pay_type' => CoreConstatns::PayType_Commission,
            'status' => CoreConstatns::FinanceStatus_Active,
            'order_id' => self::buildFinanceOrderId($reward['period']),
            'tradeno' => 'JXSYJJ_' . date('YmdHis') . '_' . genUuid(8),
            'money' => $reward['reward_money'],
            'money_real' => $reward['reward_money'],
            'created_at' => date('Y-m-d H:i:s'),
            'active_at' => date('Y-m-d H:i:s'),
            'about' => '经销商业绩奖'
        ];

        return $financeData;
    }

    /**
     * 生成支出的财务数据
     * @param array $reward
     * @param string $subNickname 下级会员昵称
     * @return array|null
     * @throws \Exception
     */
    public static function buildOutFinanceData($reward, $subNickname)
    {
        if (!$reward) return null;
        $financeData = [
            'site_id' => Site::getCurrentSite()->getSiteId(),
            'member_id' => $reward['pay_member_id'],
            'type' => CoreConstatns::FinanceType_Normal,
            'sub_type' => CoreConstatns::FinanceSubType_DealerCommission_SubPerformance,
            'out_type' => CoreConstatns::FinanceOutType_DealerPerformanceReward,
            'pay_type' => CoreConstatns::PayType_Balance,
            'status' => CoreConstatns::FinanceStatus_Active,
            'order_id' => self::buildFinanceOrderId($reward),
            'tradeno' => 'JXSYJJ_' . date('YmdHis') . '_' . genUuid(8),
            'money' => -$reward['reward_money'],
            'money_real' => -$reward['reward_money'],
            'created_at' => date('Y-m-d H:i:s'),
            'active_at' => date('Y-m-d H:i:s'),
            'about' => "转现支出-兑换业绩奖金给【{$subNickname}】"
        ];

        return $financeData;
    }

    /**
     * 发放绩效奖励（上一个月、上一个季度、上一年）
     * @param null $baseDate 以哪个时间为准则计算，默认以当前时间
     * @param bool $reset 是否重置（强行清理旧数据，慎用）
     * @param array $outputData 输出的内容
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function grantPerformanceReward($baseDate = null, $reset = false, &$outputData = [])
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $dealerPerformanceRewardSetting = DealerPerformanceRewardSetting::getCurrentSiteSetting();
        if (!$dealerPerformanceRewardSetting->enable) return;
        // 发放周期
        $givePeriod = intval($dealerPerformanceRewardSetting->give_period);
        // 是否自动审核
//        $isAutoCheck = $dealerPerformanceRewardSetting->auto_check ? true : false;
        $payee = $dealerPerformanceRewardSetting->payee; // 发放对象0 所以经销商 1 直接对接公司的经销商
        $timeStamp = $baseDate ? strtotime($baseDate) : time(); // 时间戳
        $givePeriodSign = '';
        $timeStart = ''; // 起始时间（包含）
        $timeEnd = ''; // 结束时间（不包含）
        $dataTime = '';
        $aboutData = []; // 缓存的信息
        if ($givePeriod == Constants::DealerPerformanceRewardPeriod_Year) {
            // 按年
            $lastYear = date('Y', strtotime('-1 year', $timeStamp));
            $timeStart = $lastYear . '-01-01 00:00:00';
            $timeEnd = date('Y', $timeStamp) . '-01-01 00:00:00';
            $givePeriodSign = $lastYear;
            $dataType = Constants::Statistics_MemberCloudStockPerformancePaidYear;
            $dataTime = $lastYear;
            $aboutData['date'] = $dataTime; // 缓存一下当前统计的时间
            $aboutData['data_text'] = $dataTime . '年';
            $aboutData['type_text'] = '年度奖';
        } else if ($givePeriod == Constants::DealerPerformanceRewardPeriod_Quarter) {
            // 按季度
            $lastTime = strtotime('-3 month', $timeStamp);
            $lastQuarter = ceil(intval(date('n', $lastTime)) / 3);
            $lastQuarterMonth = 1 + ($lastQuarter - 1) * 3;
            $lastQuarterMonth = $lastQuarterMonth < 10 ? '0' . $lastQuarterMonth : $lastQuarterMonth;
            $timeStart = date('Y', $lastTime) . '-' . $lastQuarterMonth . '-01 00:00:00';

            $thisQuarter = ceil(intval(date('n', $timeStamp)) / 3);
            $thisQuarterMonth = 1 + ($thisQuarter - 1) * 3;
            $thisQuarterMonth = $thisQuarterMonth < 10 ? '0' . $thisQuarterMonth : $thisQuarterMonth;
            $timeEnd = date('Y', $timeStamp) . '-' . $thisQuarterMonth . '-01 00:00:00';

            $givePeriodSign = date('Y', $lastTime) . '_' . $lastQuarter;
            $dataType = Constants::Statistics_MemberCloudStockPerformancePaidQuarter;
            $m = intval(date('m', $lastTime));
            if (in_array($m, [1, 2, 3])) $qm = '01';
            if (in_array($m, [4, 5, 6])) $qm = '02';
            if (in_array($m, [7, 8, 9])) $qm = '03';
            if (in_array($m, [10, 11, 12])) $qm = '04';
            $dataTime = date('Y', $lastTime) . $qm;
            $aboutData['date'] = $dataTime; // 缓存一下当前统计的时间
            $aboutData['data_text'] = date('Y', $lastTime) . '年' . self::quarter2Chinese($qm);
            $aboutData['type_text'] = '季度奖';
        } else {
            // 按月
            $lastTime = strtotime('-1 month', $timeStamp);
            $timeStart = date('Y-m', $lastTime) . '-01 00:00:00';
            $timeEnd = date('Y-m', $timeStamp) . '-01 00:00:00';
            $givePeriodSign = date('Y_n', $lastTime);
            $dataType = Constants::Statistics_MemberCloudStockPerformancePaidMonth;
            $dataTime = date('Ym', $lastTime);
            $aboutData['date'] = date('Y-m', $lastTime); // 缓存一下当前统计的时间
            $aboutData['data_text'] = date('Y年m月', $lastTime); // 缓存一下当前统计的时间文案
            $aboutData['type_text'] = '月度奖';
        }
        $givePeriodSign = $givePeriod . "_" . $givePeriodSign;
        $outputData = [
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'period_sign' => $givePeriodSign,
        ];
        $aboutData['date_start'] = $timeStart;
        $aboutData['date_end'] = $timeEnd;
        $aboutData['type'] = $givePeriod;
        // 强行清理旧数据
        if ($reset) {
            // 清理奖励数据
            DealerRewardModel::query()
                ->from('tbl_dealer_reward as dr')
                ->join('tbl_dealer_performance_reward as dpr', 'dpr.reward_id', 'dr.id')
                ->where('dr.site_id', $siteId)
                ->where('dpr.period', $givePeriodSign)
                ->delete();
            DealerPerformanceRewardModel::query()
                ->where('site_id', $siteId)
                ->where('period', $givePeriodSign)
                ->delete();
            // 清理财务
            FinanceModel::query()
                ->where('site_id', $siteId)
                ->where('type', CoreConstatns::FinanceType_CloudStock)
                ->where('sub_type', CoreConstatns::FinanceSubType_DealerCommission_Performance)
                ->where('order_id', self::buildFinanceOrderId($givePeriodSign))
                ->delete();
            FinanceModel::query()
                ->where('site_id', $siteId)
                ->where('type', CoreConstatns::FinanceType_Normal)
                ->where('sub_type', CoreConstatns::FinanceSubType_DealerCommission_SubPerformance)
                ->where('order_id', self::buildFinanceOrderId($givePeriodSign))
                ->delete();
        }
        // 统计业绩数据
        $performanceList = StatisticsModel::query()->from('tbl_statistics as stat')
            ->leftJoin('tbl_member as m', 'stat.member_id', '=', 'm.id')
            ->leftJoin('tbl_dealer_level as master_level', 'master_level.id', 'm.dealer_level')
            ->leftJoin('tbl_dealer_level as hide_level', 'hide_level.id', 'm.dealer_hide_level')
            ->where('stat.site_id', $siteId)
            ->where('stat.type', $dataType)
            ->where('stat.time', $dataTime)
            ->selectRaw('stat.*,
                m.dealer_level,
                m.dealer_hide_level,
                m.dealer_parent_id as member_dealer_parent_id,
                m.nickname,
                m.mobile,
                m.headurl,
                master_level.name as dealer_level_name, 
                hide_level.name as dealer_hide_level_name')
            ->get()
            ->toArray();
        if (count($performanceList) == 0) return;
        $performanceData = []; // 贡献业绩
        $totalPerformanceData = []; // 总业绩
        foreach ($performanceList as $item) {
            // 总业绩
            if ($item['dealer_parent_id'] == -1) {
                $totalPerformanceData[$item['member_id']] = $item;
            } else {
                // 因为有可能属于不同的父级
                $performanceData[$item['member_id'] . '-' . $item['dealer_parent_id']] = $item;
            }
        }
        // 获取业绩奖励规则，按代理等级，目标从大到小排序
        $ruleData = DealerPerformanceRewardRuleModel::query()
            ->where('site_id', $siteId)
            ->orderByDesc('target')
            ->get();
        if (count($ruleData) == 0) return;
        // 处理规则
        $ruleList = [];
        foreach ($ruleData as $ruleDataItem) {
            $ruleList[$ruleDataItem->dealer_level][] = [
                'target' => intval($ruleDataItem->target),
                'reward_type' => intval($ruleDataItem->reward_type),
                'reward' => intval($ruleDataItem->reward),
            ];
        }
        // 获取已经发过奖励的会员
        $existMemberIds = DealerPerformanceRewardModel::query()
            ->where('site_id', $siteId)
            ->where('period', $givePeriodSign)
            ->select('member_id')
            ->pluck('member_id')->toArray();
        // 处理数据
//        $insertFinanceData = []; // 插入的财务数据
        $_this = new static(); // 实例化一下当前类 用来插入数据
        foreach ($performanceData as $memberId => $item) {
            $memberIdInfo = explode('-', $memberId);
            $memberId = $memberIdInfo[0];
            $dealerParentId = $memberIdInfo[1];
            // 这里拿总的业绩
            $performance = intval($item['value']);
            $totalPerformance = intval($totalPerformanceData[$memberId]['value']);
            $dealerLevel = intval($item['dealer_level']);
            if ($dealerLevel == 0 || $performance == 0 || !$ruleList[$dealerLevel] || $totalPerformance == 0) continue;
            if (in_array($memberId, $existMemberIds)) continue; // 如果已经发过奖励就不发了，保证等幂性
            if ($payee && ($item['member_dealer_parent_id']) !== 0) continue; // 只发给总部直管人员
            // 把会员的基本信息缓存起来 为了给审核列表用
            $aboutData['member_nickname'] = $item['nickname'];
            $aboutData['member_mobile'] = $item['mobile'];
            $aboutData['member_headurl'] = $item['headurl'];
            $aboutData['member_dealer_level'] = $item['dealer_level'];
            $aboutData['member_dealer_level_name'] = $item['dealer_level_name'];
            $aboutData['member_dealer_hide_level'] = $item['dealer_hide_level'];
            $aboutData['member_dealer_hide_level_name'] = $item['dealer_hide_level_name'] ?: '';
            foreach ($ruleList[$dealerLevel] as $ruleItem) {
                if ($totalPerformance >= $ruleItem['target']) {
                    // 达到业绩目标
                    $performanceReward = $ruleItem['reward_type'] == 1
                        ? ceil($totalPerformance * ($ruleItem['reward'] / 10000))
                        : $ruleItem['reward'];
                    // 如果当前业绩和总业绩不相等 说明中间有多个上级的情况 业绩奖需要按比例给对应的上级发放
                    if ($performance != $totalPerformance) {
                        $performanceReward = bcmul(($performance / $totalPerformance), $performanceReward, 2);
                    }
                    // 奖励数据
                    $rewardData = [
                        'site_id' => $siteId,
                        'member_id' => $memberId,
                        'member_dealer_level' => $dealerLevel,
                        'reward_money' => $performanceReward,
                        'performance_money' => $performance,
                        'total_performance_money' => $totalPerformance,
                        'status' => Constants::DealerRewardStatus_WaitExchange,
                        'pay_member_id' => $dealerParentId,
                        'member_dealer_hide_level' => $item['dealer_hide_level'],
                        'period' => $givePeriodSign,
                        'about' => json_encode($aboutData, JSON_UNESCAPED_UNICODE)
                    ];
                    $_this->add($rewardData);

//                    if ($isAutoCheck) {
//                        // 财务数据
//                        $financeData = DealerPerformanceReward::buildFinanceData($rewardData);
//                        if ($financeData) {
//                            $insertFinanceData[] = $financeData;
//                        }
//                    }
                    break;
                }
            }
        }
//        if (count($insertFinanceData) > 0) {
//            DB::table('tbl_finance')->insert($insertFinanceData);
//        }
        // 发送通知
//        if (count($insertFinanceData) > 0) {
//            foreach ($insertFinanceData as $insertFinanceDataItem) {
//                $noticeFinanceModel = new FinanceModel();
//                $noticeFinanceModel->fill($insertFinanceDataItem);
//                //MessageNoticeHelper::sendMessageAgentCommission($noticeFinanceModel);
//            }
//        }
    }

    /**
     * 把季度转为中文表示
     * @param $quarter
     * @return string
     */
    public static function quarter2Chinese($quarter)
    {
        $quarter = intval($quarter);
        switch ($quarter) {
            case 1:
                return '第一季度';
            case 2:
                return '第二季度';
            case 3:
                return '第三季度';
            case 4:
                return '第四季度';
            default:
                return '未知时间';
        }
    }

}