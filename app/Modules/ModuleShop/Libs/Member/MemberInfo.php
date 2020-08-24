<?php
/**
 * 会员详情业务类
 * User: liyaohui
 * Date: 2019/7/15
 * Time: 15:48
 */

namespace App\Modules\ModuleShop\Libs\Member;


use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentConstants;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentHelper;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentor;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceReward;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\AgentLevelModel;
use App\Modules\ModuleShop\Libs\Model\AgentParentsModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use function Complex\add;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\Finance;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\DistrictModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Point\PointHelper;
use YZ\Core\Site\Site;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Member\Member;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Weixin\WxUser;

class MemberInfo
{
    protected $_siteId = 0;
    protected $_model = null;

    public function __construct($memberId)
    {
        if (!$memberId) {
            throw new \Exception('请传入会员ID');
        }
        $siteId = Site::getCurrentSite()->getSiteId();
        $member = MemberModel::query()->where('site_id', $siteId)
            ->where('id', $memberId)
            ->first();
        if (!$member) {
            throw new \Exception('会员不存在');
        }
        $this->_siteId = $siteId;
        $this->_model = $member;
    }

    /**
     * 获取基础配置信息
     * @return array
     */
    public static function getBaseInfo()
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        // 代理等级
        $agentLevel = (new AgentBaseSetting())->getSettingModel()->level;
        // 分销商设置的等级
        $distributionSettingLevel = DistributionSetting::getCurrentSiteSetting()->level;
        // 分销等级
        $distributionLevel = DistributionLevelModel::query()->where('site_id', $siteId)
            ->where('status', 1)
            ->orderBy('weight', 'asc')
            ->select(['id', 'weight', 'name'])
            ->get();
        // 会员等级
        $memberLevel = MemberLevelModel::query()->where('site_id', $siteId)
            ->where('status', 1)
            ->orderBy('weight', 'asc')
            ->select(['id', 'weight', 'name'])
            ->get();
        // 经销商等级
        $dealerLevels = DealerLevel::getCachedLevels();
        return [
            'agent_level' => $agentLevel,
            'distribution_setting_level' => $distributionSettingLevel,
            'distribution_level' => $distributionLevel,
            'member_level' => $memberLevel,
            'dealer_level' => $dealerLevels
        ];
    }

    /**
     * 获取会员基础信息
     * @return mixed
     */
    public function getMemberBaseInfo()
    {
        $model = $this->_model;
        // 终端类型
        $model->terminal_type = CoreConstants::getTerminalTypeText($model->terminal_type);
        // 省市区
        $districtIDs = [];
        if ($model->prov) $districtIDs[] = $model->prov;
        if ($model->city) $districtIDs[] = $model->city;
        if ($model->area) $districtIDs[] = $model->area;
        $districtData = DistrictModel::whereIn('id', $districtIDs)->select('id', 'name')->pluck('name', 'id');
        $model->prov_text = $districtData[$model->prov];
        $model->city_text = $districtData[$model->city];
        $model->area_text = $districtData[$model->area];

        // 积分统计
        $pointInfo = PointHelper::getPointInfo($model->id);
        $model->point = $pointInfo['balance'];
        $model->point_blocked = $pointInfo['blocked'];
        $model->point_consume = $pointInfo['consume'];
        $model->point_history = $pointInfo['history'];

        //统计
        //交易金额
        $tradeMoney = $model->statisticsList()->where('type', Constants::Statistics_member_tradeMoney)->first();
        $tradeTime = $model->statisticsList()->where('type', Constants::Statistics_member_tradeTime)->first();

        $model->trade_money = $tradeMoney ? moneyCent2Yuan($tradeMoney->value) : 0;
        $model->trade_time = $tradeTime ? $tradeTime->value : 0;

        // 统计余额
        $model->balance = FinanceHelper::getMemberBalance($model->id);
        $model->balance_blocked = FinanceHelper::getMemberBalanceWithdrawCheck($model->id);
        $model->balance_history = FinanceHelper::getMemberBalanceHistory($model->id);

        // 第三方绑定
        $model->bind_weixin = '';
        /*$auth = $model->authList()
            ->where('type', CoreConstants::MemberAuthType_WxOficialAccount)
            ->select(['openid', 'nickname'])
            ->first();
        if ($auth && $auth->openid) {
            $wxuser = new WxUser($auth->openid);
            $model->bind_weixin = $wxuser->getModel()->nickname;
        }*/
        $wxOpenId = (new Member($model))->getOfficialAccountOpenId();
        if ($wxOpenId) {
            $wxuser = new WxUser($wxOpenId);
            if ($wxuser->getModel()) $model->bind_weixin = $wxuser->getModel()->nickname;
        }

        // 会员身份信息 账户情况
        $infoQuery = MemberModel::query()->where('tbl_member.site_id', $this->_siteId)
            ->where('tbl_member.id', $model->id)
            ->leftJoin('tbl_member as parent', 'tbl_member.invite1', 'parent.id')
            ->leftJoin('tbl_member_withdraw_account as account', 'account.member_id', 'tbl_member.id')
            ->leftJoin('tbl_site_admin as admin', 'admin.id', 'tbl_member.admin_id')
            ->leftJoin('tbl_site_admin_department as department', 'department.id', 'admin.department_id')
            ->leftJoin('tbl_member_auth as auth', 'auth.member_id', 'tbl_member.id');
        $infoSelectArray = [
            'parent.nickname as parent_nickname',
            'parent.name as parent_name',
            'parent.mobile as parent_mobile',
            'parent.id as parent_id',
            'parent.headurl as parent_headurl',
            'account.wx_qrcode',
            'account.alipay_account',
            'account.alipay_qrcode',
            'account.alipay_name',
            'account.bank_card_name',
            'account.bank',
			'account.bank_branch',
            'account.bank_account',
            'admin.name as admin_name',
            'admin.headurl as admin_headurl',
            'admin.mobile as admin_mobile',
            'admin.position',
            'department.name as department_name',
            'auth.openid'
        ];
        // 代理配置
        $agentBaseConfig = (new AgentBaseSetting())->getSettingModel();
        //if ($agentBaseConfig->level > 0) {
        $infoQuery->leftJoin('tbl_agent as agent', function ($join) {
            $join->on('agent.member_id', 'tbl_member.id')
                ->where('agent.status', Constants::AgentStatus_Active);
        });
        $infoSelectArray[] = 'agent.passed_at as agent_passed_at';
        //}
        // 分销设置
        $distributionConfig = (new DistributionSetting())->getSettingModel();
        //if ($distributionConfig->level > 0) {
        $infoQuery->leftJoin('tbl_distributor as dist', function ($join) {
            $join->on('dist.member_id', 'tbl_member.id')
                ->where('tbl_member.is_distributor', 1);
        });
        $infoSelectArray[] = 'dist.passed_at as distributor_passed_at';
        $infoSelectArray[] = 'dist.level as distributor_level';
        //}
        // 经销商
        $dealer = DealerModel::find($model->id);

        $info = $infoQuery->select($infoSelectArray)->first();
        $orderPayStatus = implode(',', BaseShopOrder::getPaidStatusList());
        $paidStatusList = implode(',', Constants::getPaymentOrderStatus());
        // 区域代理
        $areaAgent = AreaAgentModel::query()
            ->where('member_id', $model->id)
            ->where('status', AreaAgentConstants::AreaAgentStatus_Active)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->first();
        if($areaAgent){
            $areaAgentLevel = AreaAgentLevelModel::find($areaAgent->area_agent_level);
            $areaAgent->area_type_text = AreaAgentConstants::getAreaTypeText($areaAgent->area_type);
            $areaAgent->level_name = $areaAgentLevel ? $areaAgentLevel->name : "默认等级";
            $areaAgent->area_path = implode('-',AreaAgentHelper::getAreaTypePath($areaAgent->area_type,[$areaAgent->prov,$areaAgent->city,$areaAgent->district]));
        }
        // 会员交易概况
        $orderCount = OrderModel::query()->where('site_id', $this->_siteId)
            ->where('member_id', $model->id)
            ->selectRaw("
            count(*) as order_total,
            sum(if(`status`=?,1,0)) as order_nopay,
            sum(if(`status`=?,1,0)) as order_pay,
            sum(if(`status`=?,1,0)) as order_send,
            sum(if(`status`=?,1,0)) as order_cancel,
            sum(if(`status`=?,1,0)) as order_closed,
            sum(if(`status`in (?,?),1,0)) as order_finished
            ", [
                Constants::OrderStatus_NoPay,
                Constants::OrderStatus_OrderPay,
                Constants::OrderStatus_OrderSend,
                Constants::OrderStatus_Cancel,
                Constants::OrderStatus_OrderClosed,
                Constants::OrderStatus_OrderFinished,
                Constants::OrderStatus_OrderSuccess
            ])
            ->first();
        // 合并数据
        $data = $model->toArray();

        $data['order_sale_after'] = AfterSaleModel::query()->where('member_id',$model->id)->count();
        $data['agent_setting_level'] = $agentBaseConfig->level;
        $data['distribution_setting_level'] = $distributionConfig->level;
        $data['dealer_passed_at'] = $dealer->passed_at;
        $data['dealer_level_name'] = DealerLevelModel::query()->where('id', $this->_model->dealer_level)->value('name');
        $data['dealer_hide_level_name'] = DealerLevelModel::query()->where('id', $this->_model->dealer_hide_level)->value('name');
        $data['distribution_level_name'] = DistributionLevelModel::query()->where('id', $info->distributor_level)->value('name');
        $data['level_name'] = MemberLevelModel::query()->where('id', $data['level'])->value('name');
        $data['agent_level_name'] = Constants::getAgentLevelTextForFront($data['agent_level']);
        $data['label'] = (new MemberLabel())->getMemberRelationLabel($this->_model->id);
        $data['area_agent'] = $areaAgent;
        $data = array_merge($data, $info->toArray(), $orderCount->toArray());
        return $this->convertOutputMemberBaseData($data);
    }

    /**
     * 获取分销商详情
     * @return array|mixed
     */
    public function getDistributorInfo()
    {
        $memberId = $this->_model->id;
        $siteId = $this->_siteId;
        $baseInfo = [
            'id' => $memberId,
            'nickname' => $this->_model->nickname,
            'name' => $this->_model->name,
            'mobile' => $this->_model->mobile,
            'level' => $this->_model->level,
            'headurl' => $this->_model->headurl,
            'agent_level' => $this->_model->agent_level,
            'is_distributor' => $this->_model->is_distributor,
            'dealer_level' => $this->_model->dealer_level,
            'is_area_agent' => $this->_model->is_area_agent,
            'is_supplier' => $this->_model->is_supplier,
        ];
        // 如果不是分销商 则只返回直推下级总数
        if (!$this->_model->is_distributor) {
            $memberCount = MemberModel::query()->where('site_id', $siteId)
                ->where('invite1', $memberId)
                ->count();
            $baseInfo['total'] = $memberCount;
            return $baseInfo;
        }
        // 获取当前分销商等级
        $distributorLevel = DistributorModel::query()->where('site_id', $siteId)
            ->where('member_id', $memberId)
            ->selectRaw('level as distributor_level')
            ->first()->toArray();
        $getSubSql = Distributor::getSubUserSql($memberId, 3);
        // 分销团队人数
        $memberCountSelect = "count(*) as total,
                sum(if(is_distributor = 1,1,0)) as distributor_total,
                sum(if(invite1 = ?,1,0)) as level1_num,
                sum(if((invite1 = ? and is_distributor = 1),1,0)) as level1_distributor_num,
                sum(if((invite2 = ? and is_distributor = 1),1,0)) as level2_distributor_num,
                sum(if((invite3 = ? and is_distributor = 1),1,0)) as level3_distributor_num,
                sum(if(invite2 = ?,1,0)) as level2_num,
                sum(if(invite3 = ?,1,0)) as level3_num";
        $subCount = MemberModel::query()->where('site_id', $siteId)
            ->whereRaw($getSubSql)
            ->selectRaw($memberCountSelect, [$memberId, $memberId, $memberId, $memberId, $memberId, $memberId])
            ->first();
        // 总人数加上自身
        $subCount->total = intval($subCount->total) + 1;
        // 分销团队交易情况
        $orderPayStatus = implode(',', BaseShopOrder::getPaidStatusList());
        $paidStatusList = implode(',', Constants::getPaymentOrderStatus());
        $orderCountSelect = "sum(if(order_h.level < 4 and order_h.calc_distribution_performance = 1 and tbl_order.`status` in({$orderPayStatus}),1,0)) as order_buy_times,
                sum(if(order_h.level = 1 and tbl_order.`status` in({$orderPayStatus}),1,0)) as level1_order_buy_times,
                sum(if(order_h.level = 2 and tbl_order.`status` in({$orderPayStatus}),1,0)) as level2_order_buy_times,
                sum(if(order_h.level = 3 and tbl_order.`status` in({$orderPayStatus}),1,0)) as level3_order_buy_times,
                sum(if(order_h.level = 0 and order_h.calc_distribution_performance = 1 and tbl_order.`status` in({$orderPayStatus}),1,0)) as self_purchase_order_buy_times,
                sum(if(order_h.level < 4 and order_h.calc_distribution_performance = 1 and tbl_order.`status` in({$paidStatusList}),(money + after_sale_money),0)) as order_buy_money,
                sum(if(order_h.level = 1 and tbl_order.`status` in({$paidStatusList}),(money + after_sale_money),0)) as level1_order_buy_money,
                sum(if(order_h.level = 2 and tbl_order.`status` in({$paidStatusList}),(money + after_sale_money),0)) as level2_order_buy_money,
                sum(if(order_h.level = 3 and tbl_order.`status` in({$paidStatusList}),(money + after_sale_money),0)) as level3_order_buy_money,
                sum(if(order_h.level = 0 and order_h.calc_distribution_performance = 1 and tbl_order.`status` in({$paidStatusList}),(money + after_sale_money),0)) as self_purchase_order_buy_money";
        // 要查找历史的数据 曾经是该会员的下级也要计算进去
        $orderCount = OrderMembersHistoryModel::query()->from('tbl_order_members_history as order_h')
            ->where('order_h.site_id', $siteId)
            ->where('order_h.member_id', $memberId)
            ->where('order_h.type', 0)
            ->where('order_h.has_commission', 1)
            ->leftJoin('tbl_order', 'tbl_order.id', 'order_h.order_id')
            ->selectRaw($orderCountSelect)
            ->first();
//        $orderCount = MemberModel::query()->where('tbl_member.site_id', $siteId)
//            ->whereRaw($getSubSql)
//            ->where('tbl_member.status', CoreConstants::MemberStatus_Active)
//            ->leftJoin('tbl_order', 'tbl_order.member_id', 'tbl_member.id')
//            ->selectRaw($orderCountSelect,[$memberId,$memberId,$memberId,$memberId,$memberId,$memberId])
//            ->first();
        // 分销佣金概况
        $commissionCount = FinanceModel::query()
            ->where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('type', CoreConstants::FinanceType_Commission)
            ->selectRaw(
                "sum(if(`status`=? and money > 0,money,0)) as commission_total,
                sum(if(`status`=? and money>0,money,0) + if(`status`<>? and money<0,money,0)) as commission_balance,
                sum(if(`status`=? and money>0,money,0)) as commission_unsettled,
                -sum(if(`status`=? and money<0 and out_type in(?,?),money,0)) as commission_check"
                , [
                CoreConstants::FinanceStatus_Active,
                CoreConstants::FinanceStatus_Active,
                CoreConstants::FinanceStatus_Invalid,
                CoreConstants::FinanceStatus_Freeze,
                CoreConstants::FinanceStatus_Freeze,
                CoreConstants::FinanceOutType_Withdraw,
                CoreConstants::FinanceOutType_CommissionToBalance
            ])
            ->first();
        $commissionCount = $commissionCount ? $commissionCount->toArray() : [];
        $orderCount = $orderCount ? $orderCount->toArray() : [];
        $data = array_merge($distributorLevel, $subCount->toArray(), $orderCount, $commissionCount, $baseInfo);
        return $this->convertOutputCommissionData($data);
    }

    /**
     * 获取分销商下级列表
     * @param $params
     * @return array
     */
    public function getDistributorSubList($params)
    {
        $pageSize = $params['page_size'] ?: 20;
        $page = $params['page'] ?: 1;
        $memberId = $this->_model->id;
//        $memberId = 415;
        $siteId = $this->_siteId;
//        $siteId = 22;
        $listQuery = MemberModel::query()->where('tbl_member.site_id', $siteId)
//            ->where('tbl_member.status', CoreConstants::MemberStatus_Active)
            ->leftJoin('tbl_distributor as distr', function ($join) {
                $join->on('distr.member_id', 'tbl_member.id')
                    ->where('distr.status', Constants::DistributorStatus_Active)
                    ->where('distr.is_del', 0);
            });
        // 关键字搜索
        if (isset($params['keyword'])) {
            $listQuery->where(function ($query) use ($params) {
                $query->where('tbl_member.nickname', 'like', '%' . addslashes($params['keyword']) . '%')
                    ->orWhere('tbl_member.mobile', 'like', '%' . addslashes($params['keyword']) . '%');
            });
        }
        // 身份
        if (isset($params['id_type'])) {
            switch (true) {
                case $params['id_type'] == 1:
                    $listQuery->where('tbl_member.is_distributor', $params['id_type']);
                    break;
                case $params['id_type'] == 2:
                    $listQuery->where('tbl_member.agent_level', '<>', 0);
                    break;
                case $params['id_type'] == 3:
                    $listQuery->where('tbl_member.dealer_level', '<>', 0);
                    break;
                default:
                    $listQuery->where('tbl_member.level', '<>', 0);
                    break;
            }
        }
        // 会员等级
        if (isset($params['member_level'])) {
            $listQuery->where('tbl_member.level', $params['member_level']);
        }
        // 分销商等级
        if (isset($params['distributor_level'])) {
            $listQuery->where('distr.level', $params['distributor_level']);
        }
        // 寻找所需要的等级
        if (is_numeric($params['search_level'])) {
            $searchLevel = $params['search_level'];
            switch (true) {
                case ($params['search_level_type'] == Constants::LevelType_Member) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.level', '<>', 0);
                    }
                    break;
                case ($params['search_level_type'] == Constants::LevelType_Distributor) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('distr.level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.is_distributor', '<>', 0);
                    }
                    break;
                case ($params['search_level_type'] == Constants::LevelType_Agent) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.agent_level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.agent_level', '<>', 0);
                    }
                    break;
                case ($params['search_level_type'] == Constants::LevelType_Dealer) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.dealer_level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.dealer_level', '<>', 0);
                    }
                    break;
                default:
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.level', '<>', 0);
                    }
                    break;
            }
        }

        // 查找相关层级
        $listQuery->where(function ($query) use ($memberId, $params) {
            if ($this->_model->is_distributor) {
                if (isset($params['level'])) {
                    $query->where('tbl_member.invite' . $params['level'], $memberId);
                } else {
                    $query->where('tbl_member.invite1', $memberId)
                        ->orWhere('tbl_member.invite2', $memberId)
                        ->orWhere('tbl_member.invite3', $memberId);
                }
            } else {
                // 不是分销商 只查找直推的会员
                $query->where('tbl_member.invite1', $memberId);
            }
        });
        // 注册时间
        if (isset($params['created_at_start'])) {
            $listQuery->where('tbl_member.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $listQuery->where('tbl_member.created_at', '<=', $params['created_at_end']);
        }

        // 成为分销商时间
        if (isset($params['passed_at_start'])) {
            $listQuery->where('distr.passed_at', '>=', $params['passed_at_start']);
        }
        if (isset($params['passed_at_end'])) {
            $listQuery->where('distr.passed_at', '<=', $params['passed_at_end']);
        }
        $total = $listQuery->count();
        // 分页数据
        $last_page = ceil($total / $pageSize);

        // 查询基础信息
        // 是分销商要查询佣金
        if ($this->_model->is_distributor) {
            $listQuery->leftJoin('tbl_finance as finance', function ($join) {
                $join->on('finance.member_id', 'tbl_member.id')
                    ->where('finance.type', CoreConstants::FinanceType_Commission)
                    ->where('finance.status', CoreConstants::FinanceStatus_Active)
                    ->where('finance.money', '>', 0);
            })
                ->selectRaw("sum(finance.money) as commission_total");
        } else {
            // 不是分销商 查询下级人数即可
            $listQuery->leftJoin('tbl_member as sub', 'sub.invite1', 'tbl_member.id')
                ->selectRaw("count(*) as sub_count_total,sum(if(sub.`is_distributor`=1,1,0)) as sub_count_distributor");
        }

        $list = $listQuery->groupBy(['tbl_member.id'])
            ->addSelect([
                'tbl_member.nickname',
                'tbl_member.name',
                'tbl_member.id',
                'tbl_member.mobile',
                'tbl_member.agent_level',
                'tbl_member.dealer_level',
                'distr.level as distributor_level',
                'tbl_member.created_at',
                'distr.passed_at',
                'tbl_member.level as member_level',
                'tbl_member.is_distributor',
                'tbl_member.headurl'
            ])
            ->orderByDesc('tbl_member.created_at')
            ->forPage($page, $pageSize)
            ->get();
        // 查询 直属下级数量 佣金贡献值
        if ($this->_model->is_distributor && $list->count() > 0) {
            // 获取要查找的会员id
            $memberIds = $list->pluck('id')->all();
            // 直推下级数量
            $subCount = MemberModel::query()->where('site_id', $siteId)
                ->whereIn('invite1', $memberIds)
                ->selectRaw("count(*) as total,sum(if(`is_distributor`=1,1,0)) as distributor_count,invite1 as parent_id")
                ->groupBy(['invite1'])
                ->get();
            // 佣金贡献值
            // 获取子查询语句
            $subSql = Distributor::getSubFinanceSql('tbl_member.id');
            $commission = MemberModel::query()->where('tbl_member.site_id', $siteId)
                ->whereIn('tbl_member.id', $memberIds)
                ->selectRaw("(select sum(money) from tbl_finance f where ($subSql) and f.type=? and f.money>0 and f.status=? and f.member_id=?) as commission_total, tbl_member.id", [CoreConstants::FinanceType_Commission, CoreConstants::FinanceStatus_Active, $memberId])
                ->pluck('commission_total', 'id');
            // 合并数据
            foreach ($list as &$item) {
                // 下级数量
                $subCountData = $subCount->where('parent_id', $item->id)->first();
                $item['sub_count_total'] = 0;
                $item['sub_count_distributor'] = 0;
                if ($subCountData) {
                    $item['sub_count_total'] = $subCountData['total'];
                    $item['sub_count_distributor'] = $subCountData['distributor_count'];
                }
                $item['commission_total'] = $item['commission_total'] ? moneyCent2Yuan(intval($item['commission_total'])) : '0.00';
                // 佣金贡献
                $item['sub_commission'] = $commission[$item['id']];
                $item['sub_commission'] = $item['sub_commission'] ? moneyCent2Yuan(intval($item['sub_commission'])) : '0.00';
            }

        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 获取代理基础信息
     * @return mixed
     * @throws \Exception
     */
    public function getAgentInfo()
    {
        // 如果不是代理
        if ($this->_model->agent_level <= 0) {
            throw new \Exception('该会员不是代理');
        }
        $siteId = $this->_siteId;
//        $siteId = 42;
        $memberId = $this->_model->id;
//        $memberId = 7352;
        // 基础信息
        $baseInfo = [
            'id' => $memberId,
            'nickname' => $this->_model->nickname,
            'name' => $this->_model->name,
            'mobile' => $this->_model->mobile,
            'level' => $this->_model->level,
            'headurl' => $this->_model->headurl,
            'agent_level' => $this->_model->agent_level,
            'is_distributor' => $this->_model->is_distributor,
            'dealer_level' => $this->_model->dealer_level,
            'is_area_agent' => $this->_model->is_area_agent,
            'is_supplier' => $this->_model->is_supplier,
        ];
        // 人数
        $agentInfo = AgentParentsModel::query()->where('site_id', $siteId)
            ->where('parent_id', $memberId)
            ->selectRaw("count(*)+1 as all_agent_total, 
            sum(if(agent_level=2,1,0)) as level2_agent_count,
            sum(if(agent_level=3,1,0)) as level3_agent_count")
            ->first();
        // 交易概况
        $paidStatusList = Constants::getPaymentOrderStatus();
        // 不能进行实时计算，因为旧的数据需要保留
//        $sql = 'select
//              sum(o.money + o.after_sale_money) as order_buy_money,
//              (count(1) - sum((SELECT count(1) FROM tbl_order AS o2 WHERE o.`status` = 6 AND o2.id = o.id))) as order_buy_times,
//              omh.order_id,
//              o.money,
//              o.after_sale_money,
//              omh.member_id
//              FROM
//              `tbl_order_members_history` AS omh
//              LEFT JOIN `tbl_order` AS `o` ON `o`.`id` = `omh`.`order_id`
//              WHERE
//              `o`.`status` IN ('.implode(',', $paidStatusList).')
//              AND omh.`level` >=0
//              AND omh.member_id = '.$memberId.'
//              AND omh.type = 1
//              GROUP BY
//              omh.member_id';
//        $agentOrderData=\DB::select($sql);
//        $agentOrderInfo=$agentOrderData[0] ?: [];
        $agentOrderInfo = OrderMembersHistoryModel::query()->from('tbl_order_members_history as omh')
            ->join('tbl_order as o', function ($join) use ($paidStatusList) {
                $join->on('o.id', 'omh.order_id')
                    ->whereIn('o.status', $paidStatusList);
            })
            ->where('omh.site_id', $siteId)
            ->where('omh.member_id', $memberId)
            ->where('omh.type', 1)
            ->selectRaw('sum(o.money + o.after_sale_money) as order_buy_money, 
                sum(if(o.status <> ?,1,0)) as order_buy_times,
                omh.member_id', [Constants::OrderStatus_OrderClosed])
            ->first();

        // 分红概况
        $agentMoney = FinanceModel::query()->where('site_id', $siteId)->where('member_id', $memberId)
            ->where('type', CoreConstants::FinanceType_AgentCommission)
            ->selectRaw("
                sum(if(`status`=? and money>0,money,0)) as agent_commission_total,
                sum(if(`status`=? and sub_type=?,money,0)) as agent_commission_order,
                sum(if(`status`=? and sub_type=?,money,0)) as agent_commission_sale_reward,
                sum(if(`status`=? and sub_type=?,money,0)) as agent_commission_recommend,
                sum(if(`status`=? and sub_type=?,money,0)) as agent_commission_performance,
                sum(if(`status`=? and money>0,money,0) + if(`status`<>? and money<0,money,0)) as agent_commission_balance,
                sum(if(`status`=? and money>0,money,0)) as agent_commission_unsettled,
                -sum(if(`status`=? and money<0 and out_type in(?,?),money,0)) as agent_commission_check
                ",
                [
                    CoreConstants::FinanceStatus_Active,
                    CoreConstants::FinanceStatus_Active,
                    CoreConstants::FinanceSubType_AgentCommission_Order,
                    CoreConstants::FinanceStatus_Active,
                    CoreConstants::FinanceSubType_AgentCommission_SaleReward,
                    CoreConstants::FinanceStatus_Active,
                    CoreConstants::FinanceSubType_AgentCommission_Recommend,
                    CoreConstants::FinanceStatus_Active,
                    CoreConstants::FinanceSubType_AgentCommission_Performance,
                    CoreConstants::FinanceStatus_Active,
                    CoreConstants::FinanceStatus_Invalid,
                    CoreConstants::FinanceStatus_Freeze,
                    CoreConstants::FinanceStatus_Freeze,
                    CoreConstants::FinanceOutType_Withdraw,
                    CoreConstants::FinanceOutType_CommissionToBalance
                ])
            ->first();
        $data = array_merge($agentInfo->toArray(), $agentMoney->toArray(), $agentOrderInfo->toArray(), $baseInfo);
        return $this->convertOutputCommissionData($data);
    }

    /**
     * 获取代理下级列表
     * @param $params
     * @return array
     */
    public function getAgentSubList($params)
    {
        $pageSize = $params['page_size'] ?: 20;
        $page = $params['page'] ?: 1;
        $memberId = $this->_model->id;
//        $memberId = 195;
        $siteId = $this->_siteId;
//        $siteId = 17;
        $listQuery = AgentParentsModel::query()->from('tbl_agent_parents as agent_p')
            ->where('agent_p.site_id', $siteId)
            ->where('agent_p.parent_id', $memberId)
            ->leftJoin('tbl_member', 'tbl_member.id', 'agent_p.member_id')
            ->leftJoin('tbl_distributor as distr', function ($join) {
                $join->on('distr.member_id', 'agent_p.member_id')
                    ->where('distr.status', Constants::DistributorStatus_Active)
                    ->where('distr.is_del', 0);
            });
        // 关键字搜索
        if (isset($params['keyword'])) {
            $listQuery->where(function ($query) use ($params) {
                $query->where('tbl_member.nickname', 'like', '%' . addslashes($params['keyword']) . '%')
                    ->orWhere('tbl_member.mobile', 'like', '%' . addslashes($params['keyword']) . '%');
            });
        }
        // 身份
        if (isset($params['id_type'])) {
            switch ($params['id_type']) {
                case 0:
                    $listQuery->where('tbl_member.level', '>', 0);
                    break;
                case 1:
                    $listQuery->where('tbl_member.is_distributor', 1);
                    break;
                case 2:
                    $listQuery->where('tbl_member.agent_level', '>', 0);
                    break;
                case 3:
                    $listQuery->where('tbl_member.dealer_level', '>', 0);
                    break;
            }
        }
        // 会员等级
        if (isset($params['member_level'])) {
            $listQuery->where('tbl_member.level', $params['member_level']);
        }
        // 分销商等级
        if (isset($params['distributor_level'])) {
            $listQuery->where('distr.level', $params['distributor_level']);
        }
        // 代理等级
        if (isset($params['agent_level'])) {
            $listQuery->where('agent_p.agent_level', $params['agent_level']);
        }

        // 寻找所需要的等级
        if (is_numeric($params['search_level'])) {
            $searchLevel = $params['search_level'];
            switch (true) {
                case ($params['search_level_type'] == Constants::LevelType_Member) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.level', '<>', 0);
                    }
                    break;
                case ($params['search_level_type'] == Constants::LevelType_Distributor) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('distr.level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.is_distributor', '<>', 0);
                    }
                    break;
                case ($params['search_level_type'] == Constants::LevelType_Agent) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.agent_level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.agent_level', '<>', 0);
                    }
                    break;
                case ($params['search_level_type'] == Constants::LevelType_Dealer) :
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.dealer_level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.dealer_level', '<>', 0);
                    }
                    break;
                default:
                    if (intval($searchLevel) >= 0) {
                        $listQuery->where('tbl_member.level', intval($searchLevel));
                    } else {
                        $listQuery->where('tbl_member.level', '<>', 0);
                    }
                    break;
            }
        }


        // 注册时间
        if (isset($params['created_at_start'])) {
            $listQuery->where('tbl_member.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $listQuery->where('tbl_member.created_at', '<=', $params['created_at_end']);
        }

        $total = $listQuery->count();
        // 分页数据
        $last_page = ceil($total / $pageSize);

        // 查询基础信息
        $list = $listQuery->leftJoin('tbl_finance as finance', function ($join) {
            $join->on('finance.member_id', 'agent_p.member_id')
                ->where('finance.status', CoreConstants::FinanceStatus_Active)
                ->where('finance.type', CoreConstants::FinanceType_AgentCommission)
                ->where('finance.money', '>', 0);
        })
            ->groupBy(['agent_p.member_id'])
            ->selectRaw("sum(finance.money) as agent_commission_total")
            ->addSelect([
                'tbl_member.nickname',
                'tbl_member.name',
                'tbl_member.id',
                'tbl_member.mobile',
                'tbl_member.dealer_level',
                'distr.level as distributor_level',
                'tbl_member.created_at',
                'tbl_member.level as member_level',
                'tbl_member.is_distributor',
                'tbl_member.agent_level',
                'tbl_member.headurl'
            ])
            ->orderByDesc('tbl_member.created_at')
            ->forPage($page, $pageSize)
            ->get();
        // 查询 直属下级数量 佣金贡献值
        if ($list->count() > 0) {
            // 获取要查找的会员id
            $memberIds = $list->pluck('id')->all();
            // 团队成员
            $subCount = AgentParentsModel::query()->where('site_id', $siteId)
                ->whereIn('parent_id', $memberIds)
                ->selectRaw("count(*) as total,sum(if(agent_level=3,1,0)) as agent_level3_count,parent_id")
                ->groupBy(['parent_id'])
                ->get();

            // 分红贡献值
            // 获取子查询语句
            $subSql = Agentor::getSubFinanceSql('tbl_member.id');
            $commission = MemberModel::query()->where('tbl_member.site_id', $siteId)
                ->whereIn('tbl_member.id', $memberIds)
                ->selectRaw("(select sum(money) from tbl_finance f where ($subSql) and f.type=? and f.sub_type in(?,?,?) and f.member_id=? and f.status=?) as agent_commission_total, tbl_member.id", [CoreConstants::FinanceType_AgentCommission, CoreConstants::FinanceSubType_AgentCommission_Order, CoreConstants::FinanceSubType_AgentCommission_SaleReward, CoreConstants::FinanceSubType_AgentCommission_Recommend, $memberId, CoreConstants::FinanceStatus_Active])
                ->pluck('agent_commission_total', 'id');
            // 合并数据
            foreach ($list as &$item) {
                // 下级数量
                $subCountData = $subCount->where('parent_id', $item->id)->first();
                $item['sub_count_total'] = 0;
                $item['sub_count_agent3'] = 0;
                $subCountData['total'] = $item->agent_level > 0 ? $subCountData['total'] + 1 : $subCountData['total'];
                if ($subCountData) {
                    $item['sub_count_total'] = $subCountData['total'];
                    $item['sub_count_agent3'] = $subCountData['agent_level3_count'];
                }
                $item['agent_commission_total'] = $item['agent_commission_total'] ? moneyCent2Yuan(intval($item['agent_commission_total'])) : '0.00';
                // 佣金贡献
                $item['sub_commission'] = $commission[$item['id']];
                $item['sub_commission'] = $item['sub_commission'] ? moneyCent2Yuan(intval($item['sub_commission'])) : '0.00';
            }

        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 获取经销商基础信息
     * @return mixed
     * @throws \Exception
     */
    public function getDealerInfo()
    {
        // 如果不是代理
        if ($this->_model->dealer_level <= 0) {
            throw new \Exception('该会员不是经销商');
        }
        $siteId = $this->_siteId;
        $memberId = $this->_model->id;
        $levels = DealerLevel::getCachedLevels();
        // 基础信息
        $data = [
            'id' => $memberId,
            'nickname' => $this->_model->nickname,
            'name' => $this->_model->name,
            'mobile' => $this->_model->mobile,
            'level' => $this->_model->level,
            'headurl' => $this->_model->headurl,
            'agent_level' => $this->_model->agent_level,
            'is_distributor' => $this->_model->is_distributor,
            'dealer_level' => $this->_model->dealer_level,
            'dealer_level_text' => $levels[$this->_model->dealer_level]['name'],
            'dealer_hide_level' => $this->_model->dealer_hide_level,
            'dealer_hide_level_text' => $levels[$this->_model->dealer_hide_level]['name'],
            'is_area_agent' => $this->_model->is_area_agent,
            'is_supplier' => $this->_model->is_supplier,
        ];

        // 人数
        $subDealerCount = MemberModel::query()->where('site_id', $siteId)
            ->where('dealer_parent_id', $memberId)->where('dealer_level', '>', 0)
            ->count();

        // 个人业绩
        $memberOrderCount = CloudStockPurchaseOrderModel::query()->where('member_id', $memberId)->whereIn('status', [2, 3])
            ->selectRaw('sum(total_money) as total_money, count(id) as order_num')->first();
        if ($memberOrderCount) {
            $memberOrderCount['total_money'] = moneyCent2Yuan($memberOrderCount['total_money']);
        } else {
            $memberOrderCount = ['total_money' => '0.00', 'order_num' => 0];
        }

        // 直属下级业绩
        $sql = "select distinct(h.order_id),sum(o.total_money) as total_money, count(o.id) as order_num from tbl_cloudstock_purchase_order_history as h";
        $sql .= " left join tbl_cloudstock_purchase_order as o on o.id = h.order_id";
        $sql .= " where h.member_id = $memberId and h.level = 1";
        $sql .= " and o.status in (" . implode(',', Constants::getCloudStockPurchaseOrderPayStatus()) . ')';
        $subOrderCount = DB::select($sql);
        if ($subOrderCount) {
            $subOrderCount = (array)$subOrderCount[0];
            unset($subOrderCount['order_id']);
            $subOrderCount['total_money'] = moneyCent2Yuan($subOrderCount['total_money']);
        } else {
            $subOrderCount = ['total_money' => '0.00', 'order_num' => 0];
        }

        // 资金概况
        // 订单结算
        $settle = CloudStock::getSettleCount($memberId);
        // 余额
        $finance = CloudStock::getMemberFinanceInfo($memberId);
        // 业绩
        $reward = DealerReward::getMemberReward($memberId, false);
        $moneyData = [
            'order_all' => $settle['allStatus1'], //货款的总收入
            'balance' => $finance['balance'], //当前余额
            'freeze' => $finance['freeze'], //冻结的钱
            'total' => $finance['total_income'], //历史以来的收入
            'performance_reward' => $reward['performanceCount'], //业绩奖
            'recommend_reward' => $reward['recommendCount'], //推荐奖
            'sale_reward' => $reward['saleCount'], //销售奖
        ];
        // 分转元
        $moneyData = array_map(function ($item) {
            return moneyCent2Yuan($item);
        }, $moneyData);

        // 整理数据
        $data['sub_dealer_count'] = $subDealerCount;
        $data['member_order_count'] = $memberOrderCount;
        $data['sub_order_count'] = $subOrderCount;
        $data['money_data'] = $moneyData;
        return $data;
    }

    /**
     * 获取下级经销商列表
     * @param $params
     * @return array
     */
    public function getDealerSubList($params)
    {
        $pageSize = $params['page_size'] ?: 20;
        $page = $params['page'] ?: 1;
        $memberId = $this->_model->id;
        $siteId = $this->_siteId;
        $listQuery = MemberModel::query()->from('tbl_member as m')
            ->where('m.site_id', $siteId)
            ->where('m.dealer_parent_id', $memberId)
            ->where('m.dealer_level', '>', 0)
            ->leftJoin('tbl_dealer_level as level', 'level.id', 'm.dealer_level')
            ->leftJoin('tbl_dealer_level as hidelevel', 'hidelevel.id', 'm.dealer_hide_level')
            ->leftJoin('tbl_dealer as dealer', 'dealer.member_id', 'm.id')
            ->leftJoin('tbl_statistics as stat', function ($join) use ($memberId) {
                $join->on('stat.member_id', 'm.id')
                    ->where('stat.type', Constants::Statistics_MemberCloudStockPerformancePaid)
                    ->where('stat.dealer_parent_id', -1);
            });
        // 关键字搜索
        if (isset($params['keyword'])) {
            $listQuery->where(function ($query) use ($params) {
                $query->where('m.nickname', 'like', '%' . addslashes($params['keyword']) . '%')
                    ->orWhere('m.mobile', 'like', '%' . addslashes($params['keyword']) . '%');
            });
        }
        // 等级
        if (isset($params['dealer_level'])) {
            $listQuery->where('m.dealer_level', $params['dealer_level']);
        }
        // 隐藏等级
        if (isset($params['dealer_hide_level'])) {
            $listQuery->where('m.dealer_hide_level', $params['dealer_hide_level']);
        }

        $total = $listQuery->count();
        // 分页数据
        $last_page = ceil($total / $pageSize);

        // 查询基础信息
        $list = $listQuery->select([
            'm.nickname',
            'm.name',
            'm.id',
            'm.mobile',
            'm.headurl',
            'm.status',
            'm.dealer_level',
            'm.dealer_hide_level',
            'level.name as dealer_level_name',
            'hidelevel.name as dealer_hide_level_name',
            'stat.value as performance'
        ])
            ->orderByDesc('dealer.passed_at')
            ->forPage($page, $pageSize)
            ->get();
        // 查询 直属下级数量 佣金贡献值
        if ($list->count() > 0) {
            // 获取要查找的会员id
            $memberIds = $list->pluck('id')->all();
            // 下级经销商数量
            $subCount = MemberModel::query()->where('site_id', $siteId)
                ->whereIn('dealer_parent_id', $memberIds)
                ->where('dealer_level', '>', '0')
                ->selectRaw("count(*) as total,dealer_parent_id")
                ->groupBy('dealer_parent_id')
                ->get();
            // 合并数据
            foreach ($list as &$item) {
                // 下级数量
                $subCountData = $subCount->where('dealer_parent_id', $item->id)->first();
                if ($subCountData) {
                    $item['sub_dealer'] = $subCountData['total'];
                } else {
                    $item['sub_dealer'] = 0;
                }
                $item['performance'] = $item['performance'] ? moneyCent2Yuan(intval($item['performance'])) : '0.00';
            }
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 格式化会员基础数据
     * @param $data
     * @return mixed
     */
    public function convertOutputMemberBaseData($data)
    {
        // 去掉敏感数据
        unset($data['password']);
        unset($data['pay_password']);
        // 分转元
        $keys = ['balance', 'balance_blocked', 'balance_history', 'order_buy_money'];
        foreach ($keys as $val) {
            if ($data[$val]) {
                $data[$val] = moneyCent2Yuan(intval($data[$val]));
            } else {
                $data[$val] = '0.00';
            }
        }
        return $data;
    }

    /**
     * 格式化分销数据
     * @param $data
     * @return mixed
     */
    public function convertOutputCommissionData($data)
    {
        if ($data) {
            foreach ($data as $key => &$value) {
                // 关于钱和佣金的字段才去转换
                if (strpos($key, '_money') !== false || strpos($key, 'commission_') !== false) {
                    if ($value) {
                        $value = moneyCent2Yuan(intval($value));
                    } else {
                        $value = '0.00';
                    }
                }
            }
        }
        return $data;
    }
}