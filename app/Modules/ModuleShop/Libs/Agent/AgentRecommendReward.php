<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AgentRecommendRewardModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use \YZ\Core\Constants as CodeConstatns;

/**
 * 团队推荐奖励
 */
class AgentRecommendReward
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
            $model = new AgentRecommendRewardModel();
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
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function pass()
    {
        if ($this->checkExist()) {
            $siteId = Site::getCurrentSite()->getSiteId();
            if (intval($this->getModel()->status) == Constants::AgentRewardStatus_Freeze) {
                $this->edit([
                    'status' => Constants::AgentRewardStatus_Active,
                    'reason' => null,
                ], true);
            }
            // 如果是生效的
            if (intval($this->getModel()->status) == Constants::AgentRewardStatus_Active) {
                $model = $this->getModel();
                $memberId = $model->member_id;
                $tradeno = "AGENT_RECOMMEND_REWARD_COMMISSION_" . $model->id;
                $orderId = "RECOMMEND_REWARD_" . $model->sub_member_id . "_" . $model->sub_member_agent_level;
                $financeExist = FinanceModel::query()
                    ->where('site_id', $siteId)
                    ->where('type', CodeConstatns::FinanceType_AgentCommission)
                    ->where('sub_type', CodeConstatns::FinanceSubType_AgentCommission_Recommend)
                    ->where('tradeno', $tradeno)
                    ->count();
                if ($financeExist == 0) {
                    $insertFinanceData = [
                        'site_id' => $siteId,
                        'member_id' => $memberId,
                        'type' => CodeConstatns::FinanceType_AgentCommission,
                        'sub_type' => CodeConstatns::FinanceSubType_AgentCommission_Recommend,
                        'in_type' => CodeConstatns::FinanceInType_Commission,
                        'pay_type' => CodeConstatns::PayType_Commission,
                        'status' => CodeConstatns::FinanceStatus_Active,
                        'tradeno' => $tradeno,
                        'order_id' => $orderId,
                        'money' => $model->reward_money,
                        'money_real' => $model->reward_money,
                        'created_at' => date('Y-m-d H:i:s'),
                        'active_at' => date('Y-m-d H:i:s'),
                        'about' => '推荐奖',
                        'from_member1' => $model->sub_member_id, // 被推荐这id
                    ];
                    $financeModel = new FinanceModel();
                    $financeModel->fill($insertFinanceData);
                    $financeModel->save();
                    // 发送通知
                    MessageNoticeHelper::sendMessageAgentCommission($financeModel);
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
            $model = AgentRecommendRewardModel::query()
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

        $query = AgentRecommendRewardModel::query()
            ->from('tbl_agent_recommend_reward as reward')
            ->leftJoin('tbl_member as member', 'reward.member_id', '=', 'member.id')
            ->leftJoin('tbl_member as sub_member', 'reward.sub_member_id', '=', 'sub_member.id')
            ->where('reward.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 指定数据
        if ($param['ids']) {
            $ids = myToArray($param['ids']);
            if ($ids) {
                $showAll = true;
                $query->whereIn('reward.id', $ids);
            }
        }


        if ($param['level'] && $param['level'] > 0) {
            switch (true) {
                case $param['level_type'] == 1:
                    $query->where('reward.member_agent_level', $param['level']);
                    break;
                case $param['level_type'] == 2:
                    $query->where('reward.sub_member_agent_level', $param['level']);
                    break;
                default:
                    $query->where('reward.member_agent_level', $param['level']);
                    break;
            }
        }
        // 总数据量
        $total = $query->count();
        // 排序
        if ($param['order_by'] && Schema::hasColumn('tbl_agent_recommend_reward', $param['order_by'])) {
            if ($param['order_by_asc']) {
                $query->orderBy('reward.' . $param['order_by']);
            } else {
                $query->orderByDesc('reward.' . $param['order_by']);
            }
        } else {
            $query->orderByDesc('reward.id');
        }
        $query->addSelect('reward.*');
        $query->addSelect('member.id as member_id','member.nickname as member_nickname', 'member.name as member_name', 'member.mobile as member_mobile', 'member.headurl as member_headurl');
        $query->addSelect('sub_member.id as sub_member_id','sub_member.nickname as sub_member_nickname', 'sub_member.name as sub_member_name', 'sub_member.mobile as sub_member_mobile', 'sub_member.headurl as sub_member_headurl');
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
        // 推荐会员id
        if (is_numeric($param['member_id'])) {
            $query->where('reward.member_id', intval($param['member_id']));
        }
        // 推荐会员代理等级
        if (is_numeric($param['member_agent_level'])) {
            if (intval($param['member_agent_level']) != -1) {
                $query->where('reward.member_agent_level', $param['member_agent_level']);
            }
        }
        // 被推荐会员id
        if (is_numeric($param['sub_member_id'])) {
            $query->where('reward.sub_member_id', intval($param['sub_member_id']));
        }
        // 被推荐会员代理等级
        if (is_numeric($param['sub_member_agent_level'])) {
            if (intval($param['sub_member_agent_level']) != -1) {
                $query->where('reward.sub_member_agent_level', $param['sub_member_agent_level']);
            }
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('reward.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('reward.created_at', '<=', $param['created_at_max']);
        }
        // 状态
        if (is_numeric($param['status'])) {
            if (intval($param['status']) != -9) {
                $query->where('reward.status', intval($param['status']));
            }
        }
        // 关键词
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            switch (true) {
                case $param['keyword_type'] == 1:
                    $query->where(function ($query) use ($keyword) {
                        $query->orWhere('member.nickname', 'like', '%' . trim($keyword) . '%');
                        $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                        if (preg_match('/^\w+$/i', $keyword)) {
                            $query->orWhere('member.mobile', 'like', '%' . trim($keyword) . '%');
                        }
                    });
                    break;
                case $param['keyword_type'] == 2:
                    $query->where(function ($query) use ($keyword) {
                        $query->orWhere('sub_member.nickname', 'like', '%' . trim($keyword) . '%');
                        $query->orWhere('sub_member.name', 'like', '%' . trim($keyword) . '%');
                        if (preg_match('/^\w+$/i', $keyword)) {
                            $query->orWhere('sub_member.mobile', 'like', '%' . trim($keyword) . '%');
                        }
                    });
                    break;
                default:
                    $query->where(function ($query) use ($keyword) {
                        $query->orWhere('member.nickname', 'like', '%' . trim($keyword) . '%');
                        $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                        if (preg_match('/^\w+$/i', $keyword)) {
                            $query->orWhere('member.mobile', 'like', '%' . trim($keyword) . '%');
                        }
                    });
            }
        }
    }
}