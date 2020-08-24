<?php


namespace App\Modules\ModuleShop\Libs\AreaAgent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\OrderAreaAgentHistoryModel;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

class AreaAgentor
{
    private $_model = null;

    /**
     * 初始化区域代理对象
     */
    public function __construct($idOrModel = null)
    {
        if ($idOrModel) {
            if (is_numeric($idOrModel)) {
                $this->_model = $this->find($idOrModel);
                if (!$this->_model) {
                    throw new \Exception("区域代理不存在");
                }
            } else {
                $this->_model = $idOrModel;
            }
        }
    }

    /**
     * 根据会员id查找
     * @param $memberId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function find($id)
    {
        return AreaAgentModel::query()
            ->where('id', $id)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->first();
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
     * 返回数据模型
     * @return null
     * @throws \Exception
     */
    public function getModel()
    {
        if ($this->checkExist()) {
            return $this->_model;
        } else {
            throw  new \Exception('无此区域代理');
        }
    }

    public function getTeamMemberCount()
    {
        $teamQuery = AreaAgentModel::query()->where('site_id', getCurrentSiteId())->where('member_id', '<>', $this->_model->member_id)->where('status', AreaAgentConstants::AreaAgentStatus_Active);
        if ($this->getModel()->area_type == AreaAgentConstants::AreaAgentLevel_Province) {
            $teamQuery->where('prov', $this->_model->prov)->where(function ($query) {
                $query->whereIn('area_type', [AreaAgentConstants::AreaAgentLevel_City, AreaAgentConstants::AreaAgentLevel_District]);
            });
        } elseif ($this->getModel()->area_type == AreaAgentConstants::AreaAgentLevel_City) {
            $teamQuery->where('city', $this->_model->city)->where('area_type', AreaAgentConstants::AreaAgentLevel_District);
        }
        $teamQuery->selectRaw("area_type,count(id) as num");
        $teamQuery->groupBy('area_type');
        $list = $teamQuery->get();
        $team = [];
        $total = 0;
        foreach ($list as $item) {
            $team[AreaAgentConstants::getAreaTypeStr($item->area_type)] += $item->num;
            $total += $item->num;
        }
        // 当区代去统计下级代理成员时，认为区代没有下级
        if ($this->getModel()->area_type == AreaAgentConstants::AreaAgentLevel_District) {
            $total = 0;
            $team['province'] = 0;
            $team['city'] = 0;
            $team['district'] = 0;
        }
        $team['total'] = $total;
        $data['team_member_count'] = $team;
        return $data;
    }

    /**
     * 统计数据
     * @param array $params
     * @param bool $format 是否格式化（格式化会把金额转为元，并且绝对值）
     * @return array
     */
    static public function getCountData(array $params, $format = false)
    {
        $data = [];
        // 业绩奖 先留空，如果需要就参考团队代理那边的
        if ($params['performance_reward']) {

        }
        // 可提现返佣
        if ($params['commission'] || $params['commission_balance']) {
            $data['commission_balance'] = FinanceHelper::getMemberCommissionBalance($params['member_id'], CoreConstants::FinanceType_AreaAgentCommission);
            if ($format) {
                $data['commission_balance'] = moneyCent2Yuan($data['commission_balance']);
            }
        }
        // 历史返佣
        if ($params['commission'] || $params['commission_history']) {

            $data['commission_history'] = FinanceHelper::getMemberTotalCommission($params['member_id'], CoreConstants::FinanceType_AreaAgentCommission);
            if ($format) {
                $data['commission_history'] = moneyCent2Yuan(abs($data['commission_history']));
            }
        }
        // 预计到账返佣
        if ($params['commission'] || $params['commission_unsettled']) {

            $data['commission_unsettled'] = FinanceHelper::getMemberCommissionUnsettled($params['member_id'], CoreConstants::FinanceType_AreaAgentCommission);
            if ($format) {
                $data['commission_unsettled'] = moneyCent2Yuan(abs($data['commission_unsettled']));
            }
        }
        // 提现中返佣
        if ($params['commission'] || $params['commission_check']) {
            $data['commission_check'] = FinanceHelper::getMemberCommissionCheck($params['member_id'], CoreConstants::FinanceType_AreaAgentCommission);
            if ($format) {
                $data['commission_check'] = moneyCent2Yuan(abs($data['commission_check']));
            }
        }
        // 无效返佣
        if ($params['commission'] || $params['commission_fail']) {
            $data['commission_fail'] = FinanceHelper::getMemberCommissionFail($params['member_id'], CoreConstants::FinanceType_AreaAgentCommission);
            if ($format) {
                $data['commission_fail'] = moneyCent2Yuan(abs($data['commission_fail']));
            }
        }
        // 当前业绩（本月、本季度、本年度）
        if ($params['performance_now']) {
            $areaAgentSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            $data['performance'] = AreaAgentPerformance::getAreaAgentCurrentPerformance(
                $params['member_id'],
                $areaAgentSetting['commision_grant_time'],
                $format
            );
        }

        // 总订单数(与佣金有关)
        if ($params['history_area_agent_commission_order_count']) {
            $data['history_area_agent_commission_order_count'] = OrderAreaAgentHistoryModel::query()
                ->from('tbl_order_area_agent_history as oh')
                ->join('tbl_order as o', 'o.id', 'oh.order_id')
                ->where('oh.site_id', getCurrentSiteId())
                ->where('oh.member_id', $params['member_id'])
                ->where('o.area_agent_commission_status', 2)
                ->whereIn('o.status', \App\Modules\ModuleShop\Libs\Constants::getPaymentOrderStatus())
                ->count();
        }

        return $data;
    }

    /**
     * 查找下级区域代理的列表
     * @param array $param
     */
    public function getSubAgentList($param = [])
    {
        $queryParams = [
            'siteId' => getCurrentSiteId(),
            'selfId' => $this->_model->id
        ];
        $sql = " select agent.*,member.headurl,member.name,member.nickname from tbl_area_agent as agent ";
        $sql .= " left join tbl_member as member on member.id = agent.member_id ";
        $where = " where agent.site_id = :siteId and agent.status = 1 and agent.id <> :selfId";
        if ($param['sub_area_type'] == AreaAgentConstants::AreaAgentLevel_City) {
            $queryParams['provId'] = $this->_model->prov;
            $where .= " and agent.prov = :provId and agent.area_type = " . AreaAgentConstants::AreaAgentLevel_City . " ";
        } elseif ($param['sub_area_type'] == AreaAgentConstants::AreaAgentLevel_District) {
            if ($this->_model->area_type == AreaAgentConstants::AreaAgentLevel_Province) {
                $queryParams['provId'] = $this->_model->prov;
                $where .= " and agent.prov = :provId ";
            } elseif ($this->_model->area_type == AreaAgentConstants::AreaAgentLevel_City) {
                $queryParams['cityId'] = $this->_model->city;
                $where .= " and agent.city = :cityId ";
            }
            $where .= " and agent.area_type = " . AreaAgentConstants::AreaAgentLevel_District . " ";
        } else {
            if ($this->_model->area_type == AreaAgentConstants::AreaAgentLevel_Province) $where .= " and agent.prov = " . $this->_model->prov;
            elseif ($this->_model->area_type == AreaAgentConstants::AreaAgentLevel_City) $where .= " and agent.city = " . $this->_model->city . " and agent.area_type = " . AreaAgentConstants::AreaAgentLevel_District . " ";
            else $where .= " and agent.district = " . $this->_model->district;
        }
        //查总数
        $count = BaseModel::runSql("select count(1) as total from tbl_area_agent as agent left join tbl_member as member on member.id = agent.member_id " . $where, $queryParams);
        $total = $count[0]->total;
        //查列表
        $page = $param['page'] ? $param['page'] : 1;
        $pageSize = $param['page_size'] ? $param['page_size'] : 20;
        $sql .= $where;
        $sql .= " order by agent.id desc limit :offset,:page_size";
        $queryParams['offset'] = ($page - 1) * $pageSize;
        $queryParams['page_size'] = $pageSize;
        $list = BaseModel::runSql($sql, $queryParams);
        foreach ($list as &$item) {
            $item->area_path = AreaAgentHelper::getAreaTypePath($item->area_type, [$item->prov, $item->city, $item->district]);
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => ceil($total / $pageSize),
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 获取会员的概括，包括等级名称等等
     * @param array $param
     */
    static public function getAreaAgentMemberInfo($member_id)
    {
        // 会员信息
        $member = MemberModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('id', $member_id)
            ->select([
                'id',
                'name',
                'nickname',
                'mobile',
                'headurl',
                'is_area_agent'
            ])
            ->first();
        $data['member_id'] = $member->id;
        $data['name'] = $member->name;
        $data['nickname'] = $member->nickname;
        $data['mobile'] = $member->mobile;
        $data['headurl'] = $member->headurl;
        $data['is_area_agent'] = $member->is_area_agent;
        $areaAgent = AreaAgentModel::query()->where('site_id', getCurrentSiteId())->where('member_id', $member_id)->first();
        $data['level_name'] = AreaAgentLevelModel::query()->where('id', $areaAgent->area_agent_level)->pluck('name')->first();
        return $data;
    }


}