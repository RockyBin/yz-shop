<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockApplySetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\AgentOtherRewardModel;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberAuth;
use YZ\Core\Member\Member;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use App\Modules\ModuleShop\Libs\Model\AgentParentsModel;
use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Member\Member as LibsMember;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * 代理成员
 * @author Administrator
 */
class Agentor
{
    use DispatchesJobs;
    private $_model = null;
    private $_memberModel = null;
    private $_autoInit = false;

    /**
     * 初始化某团队成员对象
     * Agentor constructor.
     * @param string|AgentModel $idOrModel 菜单的 数据库ID 或 数据库记录模型
     * @param bool $autoInit 是否自动初始化
     */
    public function __construct($idOrModel = null, $autoInit = false)
    {
        $this->_autoInit = $autoInit;
        if ($idOrModel) {
            if (is_numeric($idOrModel)) {
                $this->_model = $this->find($idOrModel);
                if ($autoInit && !$this->_model) {
                    $this->_model = $this->initAgentor($idOrModel);
                }
            } else {
                $this->_model = $idOrModel;
            }
        }
    }

    /**
     * 返回数据库记录模型
     * @return null|AgentModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 检查数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->member_id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否生效
     * @return bool
     */
    public function isActive()
    {
        if ($this->checkExist() && intval($this->_model->status) == Constants::AgentRewardStatus_Active) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否代理（状态为生效或者删除）
     * @return bool
     */
    public function isAgent()
    {
        if ($this->checkExist() && in_array(intval($this->_model->status), [Constants::AgentRewardStatus_Active, Constants::AgentStatus_Cancel])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 返回会员id
     * @return int
     */
    public function getMemberId()
    {
        if ($this->_model) {
            return $this->_model->member_id;
        } else {
            return 0;
        }
    }

    /**
     * 返回网站id
     * @return int
     */
    public function getSiteId()
    {
        if ($this->_model) {
            return $this->_model->site_id;
        } else {
            return Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 获取代理等级
     * @return int
     */
    public function getMemberAgentLevel()
    {
        $this->initMember();
        if ($this->_memberModel && $this->_memberModel->id) {
            return intval($this->_memberModel->agent_level);
        }
        return 0;
    }

    /**
     * 获取会员模型
     * @return null
     */
    public function getMemberModel()
    {
        $this->initMember();
        return $this->_memberModel;
    }

    /**
     * 统计数据
     * @param array $params
     * @param bool $format 是否格式化（格式化会把金额转为元，并且绝对值）
     * @return array
     */
    public function getCountData(array $params, $format = false)
    {
        $data = [];
        $baseSetting = AgentBaseSetting::getCurrentSiteSetting();
        $settingAgentLevel = intval($baseSetting->level);
        $memberAgentLevel = $this->getMemberAgentLevel();
        $agentRole = $settingAgentLevel > 0 && $memberAgentLevel > 0;
        $memberId = $this->getMemberId();
        $siteId = $this->getSiteId();
        // 团队信息
        if ($params['team']) {
            $agentTotalMemberNum = 0; // 团队人数
            $agentLevel2MemberNum = 0; // 二级代理人数
            $agentLevel3MemberNum = 0; // 三级代理人数
            if ($agentRole) {
                // 统计各个等级的数量
                $agentMemberCount = [];
                $agentMemberData = AgentParentsModel::query()
                    ->where('site_id', $siteId)
                    ->where('parent_id', $memberId)
                    ->groupBy('agent_level')
                    ->selectRaw('agent_level, count(1) as num')
                    ->get();
                foreach ($agentMemberData as $agentMemberItem) {
                    $num = intval($agentMemberItem->num);
                    $agentMemberCount[intval($agentMemberItem->agent_level)] = $num;
                    $agentTotalMemberNum += $num;
                }
                if ($settingAgentLevel > 1 && $memberAgentLevel == 1) {
                    // 二级代理人数
                    if (array_key_exists(2, $agentMemberCount)) {
                        $agentLevel2MemberNum = $agentMemberCount[2];
                    }
                }
                if ($settingAgentLevel > 2 & $memberAgentLevel > 0 && $memberAgentLevel <= 2) {
                    // 三级代理人数
                    if (array_key_exists(3, $agentMemberCount)) {
                        $agentLevel3MemberNum = $agentMemberCount[3];
                    }
                }
            }
            // 如果统计人数时包含自身
            if ($params['team_contain_self']) {
                $agentTotalMemberNum += 1;
            }
            $data['agent_team_member_num'] = intval($agentTotalMemberNum);
            $data['agent_team_member_num_level_2'] = intval($agentLevel2MemberNum);
            $data['agent_team_member_num_level_3'] = intval($agentLevel3MemberNum);
        }
        // 统计历史数据
        $historyData = [];
        $historyFinanceData = FinanceModel::query()->where('site_id', $siteId)->where('member_id', $memberId)
            ->where('type', CoreConstants::FinanceType_AgentCommission)
            ->where('status', CoreConstants::FinanceStatus_Active)
            ->groupBy('sub_type')
            ->selectRaw('sub_type, sum(money) as money, count(1) as num')
            ->get();
        foreach ($historyFinanceData as $historyFinanceItem) {
            $historyData[intval($historyFinanceItem->sub_type)] = [
                'money' => $historyFinanceItem->money,
                'num' => $historyFinanceItem->num,
            ];
        }
        //  订单分红
        if ($params['order_reward']) {
            $data['agent_order_reward_history'] = 0; // 订单分红收益
            $data['agent_order_num_history'] = 0;  // 订单分红订单数
            if ($agentRole && array_key_exists(CoreConstants::FinanceSubType_AgentCommission_Order, $historyData)) {
                $data['agent_order_reward_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_Order]['money']);
                $data['agent_order_num_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_Order]['num']);
            }
            if ($format) {
                $data['agent_order_reward_history'] = moneyCent2Yuan(abs($data['agent_order_reward_history']));
            }
        }
        // 销售奖
        if ($params['sale_reward']) {
            $data['agent_sale_reward_history'] = 0; // 销售奖收益
            $data['agent_sale_num_history'] = 0;  // 销售奖订单数
            if ($agentRole && array_key_exists(CoreConstants::FinanceSubType_AgentCommission_SaleReward, $historyData)) {
                $data['agent_sale_reward_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_SaleReward]['money']);
                $data['agent_sale_num_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_SaleReward]['num']);
            }
            if ($format) {
                $data['agent_sale_reward_history'] = moneyCent2Yuan(abs($data['agent_sale_reward_history']));
            }
        }
        // 推荐奖
        if ($params['recommend_reward']) {
            $data['agent_recommend_reward_history'] = 0; // 推荐奖奖金
            $data['agent_recommend_num_history'] = 0;  // 推荐奖数量
            if ($agentRole && array_key_exists(CoreConstants::FinanceSubType_AgentCommission_Recommend, $historyData)) {
                $data['agent_recommend_reward_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_Recommend]['money']);
                $data['agent_recommend_num_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_Recommend]['num']);
            }
            if ($format) {
                $data['agent_recommend_reward_history'] = moneyCent2Yuan(abs($data['agent_recommend_reward_history']));
            }
        }
        // 业绩奖
        if ($params['performance_reward']) {
            $data['agent_performance_reward_history'] = 0; // 业绩奖奖金
            $data['agent_performance_num_history'] = 0;  // 业绩奖数量
            if ($agentRole && array_key_exists(CoreConstants::FinanceSubType_AgentCommission_Performance, $historyData)) {
                $data['agent_performance_reward_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_Performance]['money']);
                $data['agent_performance_num_history'] = intval($historyData[CoreConstants::FinanceSubType_AgentCommission_Performance]['num']);
            }
            if ($format) {
                $data['agent_performance_reward_history'] = moneyCent2Yuan(abs($data['agent_performance_reward_history']));
            }
        }
        // 可提现分红
        if ($params['reward'] || $params['reward_balance']) {
            $data['reward_balance'] = FinanceHelper::getMemberCommissionBalance($memberId, CoreConstants::FinanceType_AgentCommission);
            if ($format) {
                $data['reward_balance'] = moneyCent2Yuan($data['reward_balance']);
            }
        }
        // 历史分红
        if ($params['reward'] || $params['reward_history']) {

            $data['reward_history'] = FinanceHelper::getMemberTotalCommission($memberId, CoreConstants::FinanceType_AgentCommission);
            if ($format) {
                $data['reward_history'] = moneyCent2Yuan(abs($data['reward_history']));
            }
        }
        // 预计到账分红
        if ($params['reward'] || $params['reward_unsettled']) {

            $data['reward_unsettled'] = FinanceHelper::getMemberCommissionUnsettled($memberId, CoreConstants::FinanceType_AgentCommission);
            if ($format) {
                $data['reward_unsettled'] = moneyCent2Yuan(abs($data['reward_unsettled']));
            }
        }
        // 提现中分红
        if ($params['reward'] || $params['reward_check']) {
            $data['reward_check'] = FinanceHelper::getMemberCommissionCheck($memberId, CoreConstants::FinanceType_AgentCommission);
            if ($format) {
                $data['reward_check'] = moneyCent2Yuan(abs($data['reward_check']));
            }
        }
        // 无效分红
        if ($params['reward'] || $params['reward_fail']) {
            $data['reward_fail'] = FinanceHelper::getMemberCommissionFail($memberId, CoreConstants::FinanceType_AgentCommission);
            if ($format) {
                $data['reward_fail'] = moneyCent2Yuan(abs($data['reward_fail']));
            }
        }
        // 当前业绩（本月、本季度、本年度）
        if ($params['performance_now'] || $params['performance_month'] || $params['performance_season'] || $params['performance_year']) {
            $countPeriod = intval(AgentPerformanceRewardSetting::getCurrentSiteSetting()->count_period);
            $curYear = intval(date('Y'));
            $curMonth = intval(date('n'));
            $curSeason = ceil($curMonth / 3);
            // 当月业绩
            if ($params['performance_now'] || $params['performance_month']) {
                $timeParam = AgentPerformance::parseTime(Constants::AgentPerformanceCountType_Month, $curYear, $curMonth);
                $performanceMonth = AgentPerformanceModel::query()->where('site_id', $siteId)->where('member_id', $memberId)
                    ->where('count_period', $countPeriod)
                    ->where('order_time', '>=', $timeParam['start_time'])
                    ->where('order_time', '<=', $timeParam['end_time'])
                    ->sum('money');
                $data['performance_month'] = intval($performanceMonth);
                if ($format) {
                    $data['performance_month'] = moneyCent2Yuan(abs($data['performance_month']));
                }
            }
            // 当季业绩
            if ($params['performance_now'] || $params['performance_season']) {
                $timeParam = AgentPerformance::parseTime(Constants::AgentPerformanceCountType_Season, $curYear, $curSeason);
                $performanceSeason = AgentPerformanceModel::query()->where('site_id', $siteId)->where('member_id', $memberId)
                    ->where('count_period', $countPeriod)
                    ->where('order_time', '>=', $timeParam['start_time'])
                    ->where('order_time', '<=', $timeParam['end_time'])
                    ->sum('money');
                $data['performance_season'] = intval($performanceSeason);
                if ($format) {
                    $data['performance_season'] = moneyCent2Yuan(abs($data['performance_season']));
                }
            }
            // 当年业绩
            if ($params['performance_now'] || $params['performance_year']) {
                $timeParam = AgentPerformance::parseTime(Constants::AgentPerformanceCountType_Year, $curYear, $curSeason);
                $performanceYear = AgentPerformanceModel::query()->where('site_id', $siteId)->where('member_id', $memberId)
                    ->where('count_period', $countPeriod)
                    ->where('order_time', '>=', $timeParam['start_time'])
                    ->where('order_time', '<=', $timeParam['end_time'])
                    ->sum('money');
                $data['performance_year'] = intval($performanceYear);
                if ($format) {
                    $data['performance_year'] = moneyCent2Yuan(abs($data['performance_year']));
                }
            }
        }
        if ($params['agent_new_other_reward']) {
            // 暂时只有统计已成功发红的感恩奖
            $agentOtherRewardQuery = AgentOtherRewardModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('reward_member_id', $memberId)
                ->where('type', Constants::AgentOtherRewardType_Grateful)
                ->where('status', 2);
            $data['agent_new_other_reward_num_history'] = $agentOtherRewardQuery->count();
            $data['agent_new_other_reward_history'] = $agentOtherRewardQuery->sum('reward_money');
            if ($format) {
                $data['agent_new_other_reward_history'] = moneyCent2Yuan(abs($data['agent_new_other_reward_history']));
            }

        }

        return $data;
    }

    /**
     * 获取团队成员列表
     * @param array $params
     * @return array
     */
    public function getAgentSubMemberList(array $params)
    {
        $page = intval($params['page']);
        $pageSize = intval($params['page_size']);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = AgentParentsModel::query()
            ->join('tbl_member', 'tbl_agent_parents.member_id', 'tbl_member.id')
            ->leftJoin('tbl_member_level', 'tbl_member.level', 'tbl_member_level.id')
            ->leftJoin('tbl_distributor', 'tbl_agent_parents.member_id', 'tbl_distributor.member_id')
            ->leftJoin('tbl_distribution_level', 'tbl_distribution_level.id', 'tbl_distributor.level')
            ->leftJoin('tbl_agent', 'tbl_agent_parents.member_id', 'tbl_agent.member_id')
            ->leftJoin('tbl_member_parents', function ($join) {
                $join->on('tbl_member_parents.member_id', '=', 'tbl_agent_parents.member_id');
                $join->on('tbl_member_parents.parent_id', '=', 'tbl_agent_parents.parent_id');
            })
            ->where('tbl_agent_parents.site_id', $this->getSiteId())
            ->where('tbl_agent_parents.parent_id', $this->getMemberId());
        // 代理等级
        $agentLevel = $params['agent_level'];
        if (is_numeric($agentLevel)) {
            $agentLevel = intval($params['agent_level']);
            if ($agentLevel >= 0) {
                $query->where('tbl_agent_parents.agent_level', $agentLevel);
            }
        } else {
            $agentLevel = -1;
        }
        // 统计数据条数
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        // 分页
        $query->forPage($page, $pageSize);
        // 排序
        if ($agentLevel > 0) {
            // 按代理升级时间
            $query->orderByDesc('tbl_agent.upgrade_at');
        } else {
            // 按会员注册时间
            $query->orderByDesc('tbl_member.created_at');
        }
        // 查询数据
        $query->addSelect('tbl_member.nickname', 'tbl_member.mobile', 'tbl_member.headurl', 'tbl_member.agent_level', 'tbl_member.is_distributor', 'tbl_member.created_at as member_created_at', 'tbl_agent.upgrade_at as agent_upgrade_at');
        $query->addSelect('tbl_member_level.name as member_level_name', 'tbl_distribution_level.name as distribution_level_name', 'tbl_member_parents.level as member_parent_level', 'tbl_agent_parents.level as agent_parent_level');
        // 子查询
        $nativeSqlBindings = [];
        $nativeSqlList = $this->getNativeSqlList($params, $nativeSqlBindings);
        if (count($nativeSqlList) > 0) {
            foreach ($nativeSqlList as $nativeSqlItem) {
                $query->addSelect(DB::raw($nativeSqlItem));
            }
            if (count($nativeSqlBindings) > 0) {
                $query->addBinding($nativeSqlBindings, 'select');
            }
        }
        $list = $query->get();
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 子查询
     * @param array $params
     * @param array $bindings
     * @param string $memberKey
     * @return array
     */
    public function getNativeSqlList(array $params, array &$bindings, $memberKey = 'tbl_agent_parents.member_id')
    {
        $list = [];
        if ($params['show_sub_member_num']) {
            $list[] = '(select count(1) from tbl_agent_parents as tap where tap.parent_id = ' . $memberKey . ') as sub_member_num';
        }
        if ($params['show_reward_provide']) {
            $list[] = '(select sum(money) from tbl_finance where tbl_finance.site_id = ? and tbl_finance.member_id = ? and tbl_finance.status = ' . CoreConstants::FinanceStatus_Active . ' and tbl_finance.sub_type in (' . implode(',', [CoreConstants::FinanceSubType_AgentCommission_Order, CoreConstants::FinanceSubType_AgentCommission_SaleReward, CoreConstants::FinanceSubType_AgentCommission_Recommend]) . ') and (tbl_finance.from_member1 = ' . $memberKey . ' or tbl_finance.from_member2 = ' . $memberKey . ' or tbl_finance.from_member3 = ' . $memberKey . ')) as reward_provide';
            $bindings[] = $this->getSiteId();
            $bindings[] = $this->getMemberId();
        }
        return $list;
    }

    /**
     * 检测并对此代理商进行升级
     * @param bool $save 是否保存
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function upgrade($save = true, $params)
    {
        // 取消了代理资格和审核不通过的 禁止升级
        if (in_array($this->_model->status, [Constants::AgentStatus_Cancel, Constants::AgentStatus_RejectReview])) {
            return false;
        }
        $agentLevel = new AgentLevel();
        $levels = $agentLevel->getLevelModel(['level', 'asc']);
        $member = new \App\Modules\ModuleShop\Libs\Member\Member($this->getMemberId());
        $memberAgentLevel = $member->getModel()->agent_level;
        foreach ($levels as $level) {
            if ($level->upgrade_condition) {
                $levelData = json_decode($level->upgrade_condition);
            } else {
                return false;
            }
            if (intval($levelData->status) !== 1) continue; // 禁用或不应用的等级不能升级
            $productId = $levelData->product_id ? $levelData->product_id : "";
            $params['agent_level'] = $level->level;
            if ($agentLevel->canUpgrade($this->_model->member_id, $levelData->upgrade, $productId, $memberAgentLevel, $params)) {
                // 增加会员自动升级的判断
                if (
                    ($memberAgentLevel != 0 && $memberAgentLevel > $level->level) ||
                    ($this->_autoInit && $memberAgentLevel == 0)
                ) {
                    if (!$save) return true;
                    $member->edit([
                        'agent_level' => $level->level,
                    ]);
                    // 自动升级的 特殊处理
                    if ($this->_autoInit) {
                        $this->saveAutoUpgradeAgent($level->level, $levelData->upgrade, $productId, $params);
                        return true;
                    }
                    // 保存
                    $this->_model->upgrade_at = date('Y-m-d H:i:s');
                    $this->_model->save();

                    // 写日志
                    Log::writeLog('agentLevelUpgrade', 'member_id[' . $this->_model->member_id . '] from ' . $memberAgentLevel . ' upgrade to ' . $level->level);
                    // 匹配成功则退出循环
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 自动升级相关代理人员的等级
     * @param $memberId 购买者的会员ID
     * @return bool
     */
    public static function upgradeRelationAgentLevel($memberId, $params = [])
    {
        // 分红功能是否开启
        $agentSetting = (new AgentBaseSetting())->getSettingModel();
        if (!$agentSetting || $agentSetting->level <= 0) {
            return false;
        }
        $upgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
        if ($upgradeSetting->status == 0) {
            return false;
        }
        $memberIds = self::getRelationAllParentMemberId($memberId);
        $memberIds[] = $memberId;// 因为有自购成交次数和自购成交金额，所以将当前会员也压入处理一下
        foreach ($memberIds as $memberId) {
            $m = MemberModel::find($memberId);
            // 如果后台开启了会员自动升级 则不去判断是否是代理
            if ($m && ($upgradeSetting->auto_upgrade == 1 || $m->agent_level != 0)) {
                // 会员自动升级 需要自动初始化
                $autoInit = $m->agent_level != 0;
                $d = new Agentor($memberId, !$autoInit);
                $d->upgrade(true, $params);
                // 因为会员升级之后会影响到整个代理的链条关系，所以当会员升级之后，需要把链条重新确认
                // 升级的时候已经做了处理 不需要再刷新关系 by hui
//                if ($upgrade) {
//                    AgentHelper::resetAgentParentRelationTree($memberId);
//                }
            }
        }

    }

    /**
     * 查找某人所有的代理上级
     * @param $memberId
     * @return array
     */
    public static function getRelationAllAgentMemberId($memberId)
    {
        $allAgent = [];
        $memberId = intval($memberId);
        $setting = AgentBaseSetting::getCurrentSiteSetting();
        $maxLevel = $setting->level;
        $allAgentData = AgentParentsModel::query()->where(['member_id' => $memberId])->where('agent_level', '<=', $maxLevel)->get();
        if ($allAgent) {
            $allAgent = $allAgentData->toArray();
        }
        return $allAgent;
    }

    /**
     * 查找某人所有的上级
     * @param $memberId
     * @return array
     */
    public static function getRelationAllParentMemberId($memberId)
    {
        $allParent = [];
        $memberId = intval($memberId);
        $allParentData = MemberParentsModel::query()->where(['member_id' => $memberId])->select('parent_id')->get();
        if ($allParentData) {
            $allParent = $allParentData->pluck('parent_id')->all();
        }
        return $allParent;
    }

    /**
     * 修改代理等级（用于后台）
     * @param $newLevel
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function setAgentLevel($newLevel)
    {
        $setting = AgentBaseSetting::getCurrentSiteSetting();
        $maxLevel = intval($setting->level);
        $newLevel = intval($newLevel);
        // 验证
        if (!$this->isActive()) throw new \Exception("当前代理未生效，不能修改等级");
        if ($newLevel <= 0) throw new \Exception("新等级不能为0");
        if ($newLevel > $maxLevel) throw new \Exception("系统只开了 $maxLevel 级代理，不能设置为 $newLevel 级代理");
        $this->_model->upgrade_at = date('Y-m-d H:i:s');
        $this->_model->save();
        $member = new LibsMember($this->getMemberId());
        $member->edit([
            'agent_level' => $newLevel,
        ]);
        return true;
    }

    /**
     * 处理成为代理商的事件
     * @param null $member
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    private function eventForAgentActive($member = null)
    {
        if ($this->checkExist()) {
            if (!$member) $member = new Member($this->getMemberId());
            //相关代理商升级
            $this->upgradeRelationAgentLevel($this->getMemberId());
        }
    }

    /**
     * 根据会员id查找
     * @param $memberId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function find($memberId)
    {
        return AgentModel::query()
            ->where('member_id', $memberId)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->first();
    }

    /**
     * 按需初始化会员信息
     */
    private function initMember()
    {
        if ($this->checkExist()) {
            if (!$this->_memberModel || !$this->_memberModel->id) {
                $this->_memberModel = MemberModel::query()->where('id', $this->getMemberId())->first();
            }
        }
    }

    /**
     * 获取查找某会员的下级分红SQL
     * @param $memberId
     * @param int $maxLevel
     * @param int $startLevel
     * @param string $table
     * @return string
     */
    public static function getSubFinanceSql($memberId, $maxLevel = 0, $startLevel = 1, $table = '')
    {
        // 默认从配置读取
        if ($maxLevel <= 0) {
            $setting = AgentBaseSetting::getCurrentSiteSetting();
            $maxLevel = $setting->level;
        }

        return FinanceHelper::getSubUserSql($memberId, $maxLevel, $startLevel, $table);
    }

    /**
     * 用于会员自动升级为代理时的初始化
     * @param $memberId
     * @return AgentModel
     */
    private function initAgentor($memberId)
    {
        $agentor = new AgentModel();
        $agentor->member_id = $memberId;
        $agentor->site_id = Site::getCurrentSite()->getSiteId();
        return $agentor;
    }

    private function saveAutoUpgradeAgent($level, $levelData, $productId, $params)
    {
        $this->_model->agent_apply_level = $level;
        $this->_model->status = 1;
        $now = Carbon::now();
        $this->_model->created_at = $now;
        $this->_model->passed_at = $now;
        $this->_model->upgrade_at = $now;
        $cache = [
            'data' => $levelData,
            'text' => AgentLevel::getLevelConditionsTitle($levelData, $productId, $params)
        ];
        $this->_model->auto_upgrade_data = json_encode($cache, JSON_UNESCAPED_UNICODE);
        $save = $this->_model->save();
        // 写日志
        Log::writeLog('agentLevelUpgrade', 'member_id[' . $this->_model->member_id . ']  auto upgrade to ' . $level);
    }
}
