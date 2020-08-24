<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceRewardModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use \YZ\Core\Constants as CodeConstatns;

/**
 * 团队业绩奖励
 */
class AgentPerformanceReward
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
            $time = date('Y-m-d H:i:s');
            $param['site_id'] = Site::getCurrentSite()->getSiteId();
            $param['created_at'] = $time;
            if (array_key_exists('status', $param) && intval($param['status']) == Constants::AgentRewardStatus_Active) {
                $param['checked_at'] = $time;
            }
            $model = new AgentPerformanceRewardModel();
            $model->fill($param);
            $model->save();
            if ($reload) {
                $this->findById($model->id);
            }
            return $model->id;
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
            unset($param['site_id']);
            if (array_key_exists('status', $param) && intval($param['status']) != Constants::AgentRewardStatus_Freeze) {
                $param['checked_at'] = date('Y-m-d H:i:s');
            }
            $this->_model->fill($param);
            $this->_model->save();
            if ($reload) {
                $this->findById($this->_model->id);
            }
            return true;
        }
        return false;
    }

    /**
     * 审核通过
     * @throws \Exception
     */
    public function pass()
    {
        if ($this->checkExist()) {
            if (intval($this->getModel()->status) == Constants::AgentRewardStatus_Freeze) {
                $this->edit([
                    'status' => Constants::AgentRewardStatus_Active,
                    'reason' => null,
                ], true);
            }
            // 如果是生效的
            if (intval($this->getModel()->status) == Constants::AgentRewardStatus_Active) {
                $model = $this->getModel();
                $orderId = self::buildFinanceOrderId($model->period);
                $financeExist = FinanceModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('member_id', $model->member_id)
                    ->where('type', CodeConstatns::FinanceType_AgentCommission)
                    ->where('sub_type', CodeConstatns::FinanceSubType_AgentCommission_Performance)
                    ->where('order_id', $orderId)
                    ->count();
                if ($financeExist == 0) {
                    $insertFinanceData = self::buildFinanceData($model->ToArray());
                    if ($insertFinanceData) {
                        $financeModel = new FinanceModel();
                        $financeModel->fill($insertFinanceData);
                        $financeModel->save();
                        // 发送通知
                        MessageNoticeHelper::sendMessageAgentCommission($financeModel);
                    }
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
        if ($this->checkExist() && intval($this->getModel()->status) == Constants::AgentRewardStatus_Freeze) {
            $this->edit([
                'reason' => $reason,
                'status' => Constants::AgentRewardStatus_Invalid
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
            $model = AgentPerformanceRewardModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $id)
                ->first();
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

        $query = AgentPerformanceRewardModel::query()
            ->from('tbl_agent_performance_reward')
            ->leftJoin('tbl_member', 'tbl_agent_performance_reward.member_id', '=', 'tbl_member.id')
            ->where('tbl_agent_performance_reward.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 指定数据
        if ($param['ids']) {
            $ids = myToArray($param['ids']);
            if ($ids) {
                $showAll = true;
                $query->whereIn('tbl_agent_performance_reward.id', $ids);
            }
        }
        // 总数据量
        $total = $query->count();
        // 排序
        if ($param['order_by'] && Schema::hasColumn('tbl_agent_performance_reward', $param['order_by'])) {
            if ($param['order_by_asc']) {
                $query->orderBy('tbl_agent_performance_reward.' . $param['order_by']);
            } else {
                $query->orderByDesc('tbl_agent_performance_reward.' . $param['order_by']);
            }
        } else {
            $query->orderByDesc('tbl_agent_performance_reward.id');
        }
        $query->addSelect('tbl_agent_performance_reward.*');
        $query->addSelect('tbl_member.nickname as member_nickname', 'tbl_member.name as member_name', 'tbl_member.mobile as member_mobile', 'tbl_member.headurl as member_headurl');
        if ($showAll) {
            $last_page = 1;
        } else {
            $query->forPage($page, $pageSize);
            $last_page = ceil($total / $pageSize);
        }
        $list = $query->get();
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
            $query->where('tbl_agent_performance_reward.member_id', intval($param['member_id']));
        }
        // 代理等级
        if (is_numeric($param['member_agent_level'])) {
            if (intval($param['member_agent_level']) != -1) {
                $query->where('tbl_agent_performance_reward.member_agent_level', $param['member_agent_level']);
            }
        }
        // 时期
        if ($param['period']) {
            $query->where('tbl_agent_performance_reward.period', intval($param['period']));
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('tbl_agent_performance_reward.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('tbl_agent_performance_reward.created_at', '<=', $param['created_at_max']);
        }
        // 状态
        if (is_numeric($param['status'])) {
            if (intval($param['status']) != -9) {
                $query->where('tbl_agent_performance_reward.status', intval($param['status']));
            }
        }
        // 关键词
        if ($param['keyword']) {
            $keyword = '%' . $param['keyword'] . '%';
            $query->where(function (Builder $subQuery) use ($keyword) {
                $subQuery->where('tbl_member.nickname', 'like', $keyword)
                    ->orWhere('tbl_member.mobile', 'like', $keyword)
                    ->orWhere('tbl_member.name', 'like', $keyword);
            });
        }
    }

    /**
     * 生成财务订单id
     * @param $period
     * @return string
     */
    public static function buildFinanceOrderId($period)
    {
        return 'PERFORMANCE_REWARD_' . $period;
    }

    /**
     * 生成财务数据
     * @param array $reward
     * @return array|null
     * @throws \Exception
     */
    public static function buildFinanceData(array $reward)
    {
        if (!$reward) return null;
        $financeData = [
            'site_id' => Site::getCurrentSite()->getSiteId(),
            'member_id' => $reward['member_id'],
            'type' => CodeConstatns::FinanceType_AgentCommission,
            'sub_type' => CodeConstatns::FinanceSubType_AgentCommission_Performance,
            'in_type' => CodeConstatns::FinanceInType_Commission,
            'pay_type' => CodeConstatns::PayType_Commission,
            'status' => CodeConstatns::FinanceStatus_Active,
            'order_id' => self::buildFinanceOrderId($reward['period']),
            'tradeno' => 'AGENT_PERFORMANCE_REWARD_COMMISSION_' . date('YmdHis') . '_' . genUuid(8),
            'money' => $reward['reward_money'],
            'money_real' => $reward['reward_money'],
            'created_at' => date('Y-m-d H:i:s'),
            'active_at' => date('Y-m-d H:i:s'),
            'about' => '代理团队业绩售奖'
        ];

        return $financeData;
    }
}