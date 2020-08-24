<?php

namespace App\Modules\ModuleShop\Libs\Member;

use App\Modules\ModuleShop\Jobs\UpgradeDistributionLevelJob;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Agent\AgentReward;
use App\Modules\ModuleShop\Libs\Dealer\DealerHelper;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AgentParentsModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\ShoppingCartModel;
use App\Modules\ModuleShop\Libs\OpLog\OpLog;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use YZ\Core\Member\Member as MemberEntity;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\DistrictModel;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Model\PointModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Point\PointHelper;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Weixin\WxUser;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * 会员类
 * Class Member
 * @package App\Modules\ModuleShop\Libs\Member
 */
class Member
{
    use DispatchesJobs;
    private $member = null;
    private $distributionSetting = null; // 分销商设置

    /**
     * 初始化
     * Member constructor.
     * @param int $idOrModel 会员id 或 会员模型
     * @param int siteId 站点id，一般用于不需要实例化会员模型的时候
     * @param bool $useCache
     */
    public function __construct($idOrModel = 0, $siteId = 0, $useCache = true)
    {
        if (is_a($idOrModel, 'YZ\Core\Member\Member')) {
            $this->member = $idOrModel;
        } else {
            $this->member = new MemberEntity($idOrModel, $siteId, $useCache);
        }

        if (!$this->member->hasOnAddEvent()) {
            // 注册赠送积分
            $this->member->addOnAddEvent('App\Modules\ModuleShop\Libs\Point\PointGiveHelper@GiveForRegister');
        }

        if (!$this->member->hasOnLoginEvent()) {
            // 首次登录赠送积分
            $this->member->addOnLoginEvent('App\Modules\ModuleShop\Libs\Point\PointGiveHelper@GiveForLogin');
        }

        if (!$this->member->hasOnSetParentEvent()) {
            // 相关分销商升级
            $this->member->addOnSetParentEvent(function () {
                if ($this->checkExist()) {
                    $this->dispatch(new UpgradeDistributionLevelJob($this->getMemberId()));
//                    Distributor::upgradeRelationDistributorLevel($this->getMemberId());
                }
            });
            // 推荐会员赠送积分
            $this->member->addOnSetParentEvent('App\Modules\ModuleShop\Libs\Point\PointGiveHelper@GiveForMemberRecommend');
        }
    }

    /**
     * 会员是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->member) {
            return $this->member->checkExist();
        } else {
            return false;
        }
    }

    /**
     * 会员是否生效
     * @return bool
     */
    public function isActive()
    {
        if ($this->checkExist() && $this->member->isActive()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否分销商
     * @return bool
     */
    public function isDistributor()
    {
        if (!$this->checkExist()) return false;
        return $this->getModel()->is_distributor ? true : false;
    }

    /**
     * 是否代理商
     * @return bool
     */
    public function isAreaAgent()
    {
        if (!$this->checkExist()) return false;
        return $this->getModel()->is_area_agent == 1 ? true : false;
    }

    /**
     * 添加会员记录
     * @param array $info
     * @return bool
     */
    public function add(array $info)
    {
        try {
            DB::beginTransaction();
            // 检查手机唯一性
            if ($info['mobile'] && $exist = $this->mobileExist($info['mobile'])) {
                return makeServiceResult(511, '手机已存在', ['status' => $exist->status, 'member_id' => $exist->id]);
            }
            // 处理默认会员级别
            if (!$info['level']) {
                $dl = MemberLevelModel::query()->where(['site_id' => $this->getSiteID(), 'weight' => 0])->first();
                if ($dl) $info['level'] = $dl->id;
            }
            $info['site_id'] = $this->getSiteID();
            $info = $this->processData($info);
            $this->member->add($info);
            if ($info['label_id']) {
                (new MemberLabel())->editMemberRelationLabel($this->member->getModelId(), $info['label_id']);
            }
            DB::commit();
            if ($this->checkExist()) {
                return makeServiceResult(200, '操作成功', [
                    'id' => $this->member->getModelId()
                ]);
            } else {
                return makeServiceResult(510, '数据异常');
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改会员信息
     * @param array $info
     * @return array
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function edit(array $info)
    {
        $info = $this->processData($info);
        if ($this->checkExist()) {
            $oldMemberLevel = intval($this->getModel()->level); // 修改前的会员等级
            $oldAgentLevel = intval($this->getModel()->agent_level); // 修改前的代理等级
            $oldDealerLevel = intval($this->getModel()->dealer_level); // 修改前的经销商等级
            $oldDealerHideLevel = intval($this->getModel()->dealer_hide_level); // 修改前的经销商等级
            // 检查手机唯一性
            $mobile = $info['mobile'];
            if ($mobile && $this->member->getModel()->mobile != $mobile) {
                if ($this->mobileExist($mobile)) {
                    return makeServiceResult(511, '手机已存在');
                }
            }
            // 检查上级代理商身份
            $hasChangeParent = false;
            if ($info['parent_id'] && is_numeric($info['parent_id'])) {
                if ($info['parent_id'] == $this->getMemberId()) {
                    unset($info['parent_id']);
                } else {
                    if ($info['parent_id'] != $this->getModel()->invite1) {
                        $hasChangeParent = true;
                    }
                }
            }

            if ($info['admin_id']) {
                $siteAdminModel = (new SiteAdmin($info['admin_id']))->getModel();
                if (!($siteAdminModel && $siteAdminModel->status == 1)) {
                    return makeServiceResult(400, '此员工不存在或已失效');
                }
            }

            $info['id'] = $this->getMemberId();
            unset($info['site_id']);
            $this->member->edit($info);
            if ($hasChangeParent) {
                // 自动升级相关分销员的等级
                $this->dispatch(new UpgradeDistributionLevelJob($this->getMemberId()));
            }
            // 如果修改了会员等级，则要发通知
            if (array_key_exists('level', $info) && $oldMemberLevel != intval($info['level'])) {
                //记录用户操作 $oldMemberLevel 更改前的会员等级 $info['level'] 更改后的会员的等级
                OpLog::Log(Constants::OpLogType_MemberLevelChange, $this->getModel()->id, $oldMemberLevel, $info['level']);
                MessageNoticeHelper::sendMessageMemberLevelUpgrade($this->getModel());
            }
            // 如果修改了代理等级
            $agentLevel = intval($info['agent_level']);
            if (array_key_exists('agent_level', $info)) {
                // 查找之前关系的代理上级
                $agentParentList = AgentParentsModel::query()->where('site_id', $this->getSiteID())->where('member_id', $this->getMemberId())->get();
                //记录用户操作 $oldAgentLevel 更改前的代理等级 $agentLevel 更改后的代理的等级
                OpLog::Log(Constants::OpLogType_AgentLevelChange, $this->getModel()->id, $oldAgentLevel, $agentLevel);

                if ($oldAgentLevel != $agentLevel) {
                    // 重置下级代理关系
                    AgentHelper::dispatchResetAgentParentsJob($this->getMemberId());
                }
                // 升级则发送相关通知
                if ($agentLevel > 0 && ($oldAgentLevel > $agentLevel || $oldAgentLevel == 0)) {
                    // 推荐奖(只有从非代理升级为代理时，才发一次推荐奖)
                    if ($oldAgentLevel == 0) AgentReward::grantRecommendReward($this->getMemberId(), $agentLevel);
                    // 发送代理等级升级通知
                    MessageNoticeHelper::sendMessageAgentLevelUpgrade($this->getModel(), $oldAgentLevel);
                    // 发送下级代理等级升级通知
                    if (count($agentParentList) > 0) {
                        foreach ($agentParentList as $agentParentItem) {
                            $agentParentMemberModel = MemberModel::query()->where('site_id', $this->getSiteID())
                                ->where('id', $agentParentItem->parent_id)
                                ->first();
                            MessageNoticeHelper::sendMessageAgentSubMemberLevelUpgrade($agentParentMemberModel, $this->getModel());
                        }
                    }
                }
            }

            // 如果修改了经销商等级
            $dealerLevel = intval($info['dealer_level']);
            $dealerHideLevel = intval($info['dealer_hide_level']);
            if (array_key_exists('dealer_level', $info) || array_key_exists('dealer_hide_level', $info)) {
                // 查找之前关系的经销商上级
                $dealerParentList = DealerParentsModel::query()->where('site_id', $this->getSiteID())->where('member_id', $this->getMemberId())->get();
                //记录用户操作 $oldDealerLevel 更改前的代理等级 $dealerLevel 更改后的代理的等级
                OpLog::Log(Constants::OpLogType_DealerLevelChange, $this->getModel()->id, $oldDealerLevel, $dealerLevel);

                if ($oldDealerLevel != $dealerLevel || $oldDealerHideLevel != $dealerHideLevel) {
                    // 重置下级经销商关系
                    DealerHelper::dispatchResetDealerParentsJob($this->getMemberId(), $oldDealerLevel, $dealerLevel);
                }
                // 升级则发送相关通知
                $dealerLevels = DealerLevel::getCachedLevels();
                $oldWeight = $dealerLevels[$oldDealerLevel]['weight'];
                $newWeight = $dealerLevels[$dealerLevel]['weight'];
                if ($dealerLevel > 0 && $newWeight > $oldWeight) {
                    // 发送经销商等级升级通知
                    $mModel = $this->getModel();
                    $DealerModel = DealerModel::query()->where('member_id', $this->getMemberId())->where('site_id', $this->getSiteID())->first();
                    MessageNotice::dispatch(\YZ\Core\Constants::MessageType_Dealer_Agree, $DealerModel, $oldDealerLevel);
                    // 发送下级经销商等级升级通知
                    if (count($dealerParentList) > 0) {
                        foreach ($dealerParentList as $agentParentItem) {
                            $dealerParentMemberModel = MemberModel::query()->where('site_id', $this->getSiteID())
                                ->where('id', $agentParentItem->parent_id)
                                ->first();
                            MessageNotice::dispatch(\YZ\Core\Constants::MessageType_DealerSubMember_LevelUpgrade, $dealerParentMemberModel, $mModel);
                        }
                    }
                }
            }

            return makeServiceResult(200, '操作成功');
        } else {
            return makeServiceResult(510, '数据异常');
        }
    }

    /**
     * 变更状态
     * @param $status
     * @return array
     * @throws \Exception
     */
    public function status($status)
    {
        if (!$this->checkExist()) {
            return makeServiceResult(510, '数据异常');
        }

        $status = $status ? 1 : 0;
        // 分销商不能封号
        if ($status == 0 && $this->member->getModel()->is_distributor == 1) {
            return makeServiceResult(512, '分销商不能封号');
        }
        // 代理商不能封号
        if ($status == 0 && $this->member->getModel()->agent_level > 0) {
            return makeServiceResult(512, '代理商不能封号');
        }

        $this->member->edit(['status' => $status]);
        return makeServiceResult(200, '操作成功');
    }

    /**
     * 查找指定ID的会员记录
     * @param bool $showExtend 输出额外的信息
     * @return bool|null
     */
    public function getInfo($showExtend = false)
    {
        if ($this->checkExist()) {
            $model = $this->member->getModel();
            if ($showExtend) {
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

                // 统计余额
                $balanceInfo = FinanceHelper::getBalanceInfo($model->id);
                $model->balance = $balanceInfo['balance'];
                $model->balance_blocked = moneyCent2Yuan($balanceInfo['blocked']);
                $model->balance_history = $balanceInfo['history'];

                // 第三方绑定
                $model->bind_weixin = '';
                $model->bind_qq = '';
                $model->bind_alipay = '';
                /*$auth = $model->authList()->where('type', CoreConstants::MemberAuthType_WxOficialAccount)->first();
                if ($auth && $auth->openid) {
                    $wxuser = new WxUser($auth->openid);
                    $model->bind_weixin = $wxuser->getModel()->nickname;
                }*/
                $wxOpenId = $this->member->getOfficialAccountOpenId();
                if ($wxOpenId) {
                    $wxuser = new WxUser($wxOpenId);
                    if ($wxuser->getModel()) $model->bind_weixin = $wxuser->getModel()->nickname;
                }

                // 上级分销商
                $model->invite_nickname = '';
                $model->name = '';
                if ($model->invite1) {
                    $inviteModel = MemberModel::query()
                        ->where('id', $model->invite1)
                        ->where('site_id', $this->getSiteID())
                        //  ->where('is_distributor', '1')
                        ->first();
                    if ($inviteModel) {
                        $model->invite_nickname = $inviteModel->nickname;
                        $model->invite_name = $inviteModel->name;
                    }
                }

//                // 实时统计订单完成情况
//                $orderHelper = new OrderHelper($this->getSiteID());
//                // 消费
//                $countResult = $orderHelper->countSingleMember(BaseShopOrder::getPaidStatusList(), $this->getMemberId());
//                $model->buy_times = intval($countResult['times']);
//                $model->buy_money = intval($countResult['money']);
//                // 成交
//                $countResult = $orderHelper->countSingleMember(BaseShopOrder::getDealStatusList(), $this->getMemberId());
//                $model->deal_times = intval($countResult['times']);
//                $model->deal_money = intval($countResult['money']);
            }
            return $model;
        } else {
            return false;
        }
    }

    /**
     * 搜索出会员的列表
     * @param $searchInfo
     * @return array
     */
    public function getList($searchInfo)
    {
        $page = intval($searchInfo['page']);
        $pageSize = intval($searchInfo['page_size']);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;
        $isShowAll = $searchInfo['show_all'] || ($searchInfo['ids'] && count($searchInfo['ids']) > 0) ? true : false; // 是否显示全部数据（不分页）
        $countExtend = $searchInfo['count_extend'] ? true : false; // 是否展示统计信息
        $setting = DistributionSetting::getCurrentSiteSetting();
        //若有想查询最大等级优先查询想查询的最大等级，否认就读取配置
        $maxLevel = $searchInfo['search_distributor_max_level'] ? $searchInfo['search_distributor_max_level'] : ($setting ? intval($setting['level']) : 0); // 最大分销等级
        $expression = MemberModel::query()->from('tbl_member');
        $expression->leftJoin('tbl_distributor', 'tbl_distributor.member_id', '=', 'tbl_member.id');
        $expression->leftJoin('tbl_distribution_level', 'tbl_distributor.level', '=', 'tbl_distribution_level.id');
        $expression->leftJoin('tbl_site_admin as admin', 'admin.id', '=', 'tbl_member.admin_id');
        // 区代搜索
        $expression->leftJoin('tbl_area_agent as area', function ($q) {
            $q->on('area.member_id', 'tbl_member.id')
                ->where('area.status', 1);
        });

        if ($countExtend) {
            $memberCountQuery = MemberModel::query()
                ->where('site_id', $this->getSiteID())
                //->groupBy('member_id')
                ->addSelect('id as member_id');
            $extendColumns = $this->getRelationSql($searchInfo);
            foreach ($extendColumns as $extendColumn) {
                $memberCountQuery->addSelect(DB::raw($extendColumn));
            }
            // 关联临时表（如果临时表扫描的数据较多会比较慢）
            $expression->leftJoin(DB::raw("({$memberCountQuery->toSql()}) as member_count"), 'tbl_member.id', '=', 'member_count.member_id')
                ->mergeBindings($memberCountQuery->getQuery());

            if (is_numeric($searchInfo['trade_money_max'])) {
                $expression->where('trade_money', '<=', intval($searchInfo['trade_money_max'] * 100));
            }
            if (is_numeric($searchInfo['trade_money_min'])) {
                $expression->where('trade_money', '>=', intval($searchInfo['trade_money_min'] * 100));
            }
            if (is_numeric($searchInfo['trade_time_max'])) {
                $expression->where('trade_time', '<=', intval($searchInfo['trade_time_max']));
            }
            if (is_numeric($searchInfo['trade_time_min'])) {
                $expression->where('trade_time', '>=', intval($searchInfo['trade_time_min']));
            }

            // 处理 成交次数 和 成交金额 以及 付款次数 和 付款金额
            $dealValueMin = $searchInfo['deal_value_min'] == '' ? -1 : floatval($searchInfo['deal_value_min']);
            $dealValueMax = $searchInfo['deal_value_max'] == '' ? -1 : floatval($searchInfo['deal_value_max']);

            if (is_numeric($searchInfo['deal_type'])) {
                $dealType = intval($searchInfo['deal_type']);
                if ($dealType == 1) {
                    $searchInfo['deal_money_min'] = intval($dealValueMin * 100);
                    $searchInfo['deal_money_max'] = intval($dealValueMax * 100);
                } else if ($dealType == 0) {
                    $searchInfo['deal_times_min'] = intval($dealValueMin);
                    $searchInfo['deal_times_max'] = intval($dealValueMax);
                } else if ($dealType == 2) {
                    $searchInfo['buy_times_min'] = intval($dealValueMin);
                    $searchInfo['buy_times_max'] = intval($dealValueMax);
                } else if ($dealType == 3) {
                    $searchInfo['buy_money_min'] = intval($dealValueMin * 100);
                    $searchInfo['buy_money_max'] = intval($dealValueMax * 100);
                }
            }

            if (is_numeric($searchInfo['deal_money_min']) && intval($searchInfo['deal_money_min']) >= 0) {
                $expression->where('tbl_member.deal_money', '>=', intval($searchInfo['deal_money_min']));
            }
            if (is_numeric($searchInfo['deal_money_max']) && intval($searchInfo['deal_money_max']) >= 0) {
                $expression->where('tbl_member.deal_money', '<=', intval($searchInfo['deal_money_max']));
            }
            if (is_numeric($searchInfo['buy_money_min']) && intval($searchInfo['buy_money_min']) >= 0) {
                $expression->where('tbl_member.buy_money', '>=', intval($searchInfo['buy_money_min']));
            }
            if (is_numeric($searchInfo['buy_money_max']) && intval($searchInfo['buy_money_max']) >= 0) {
                $expression->where('tbl_member.buy_money', '<=', intval($searchInfo['buy_money_max']));
            }
            if (is_numeric($searchInfo['deal_times_min']) && intval($searchInfo['deal_times_min']) >= 0) {
                $expression->where('tbl_member.deal_times', '>=', intval($searchInfo['deal_times_min']));
            }
            if (is_numeric($searchInfo['deal_times_max']) && intval($searchInfo['deal_times_max']) >= 0) {
                $expression->where('tbl_member.deal_times', '<=', intval($searchInfo['deal_times_max']));
            }
            if (is_numeric($searchInfo['buy_times_min']) && intval($searchInfo['buy_times_min']) >= 0) {
                $expression->where('tbl_member.buy_times', '>=', intval($searchInfo['buy_times_min']));
            }
            if (is_numeric($searchInfo['buy_times_max']) && intval($searchInfo['buy_times_max']) >= 0) {
                $expression->where('tbl_member.buy_times', '<=', intval($searchInfo['buy_times_max']));
            }
        }

        #region 通用搜索条件
        $expression->where('tbl_member.site_id', $this->getSiteID());
        // 注册时间开始
        if ($searchInfo['starttime']) {
            $expression->where('tbl_member.created_at', '>=', $searchInfo['starttime']);
        }
        // 注册时间接触
        if ($searchInfo['endtime']) {
            $expression->where('tbl_member.created_at', '<=', $searchInfo['endtime']);
        }
        // 注册渠道
        if (is_numeric($searchInfo['regfrom']) && intval($searchInfo['regfrom']) >= 0) {
            $expression->where('tbl_member.regfrom', intval($searchInfo['regfrom']));
        }
        // 终端来源
        if (is_numeric($searchInfo['terminal_type']) && intval($searchInfo['terminal_type']) >= 0) {
            $expression->where('tbl_member.terminal_type', intval($searchInfo['terminal_type']));
        }
        // 会员状态
        if (is_numeric($searchInfo['status']) && intval($searchInfo['status']) >= 0) {
            $expression->where('tbl_member.status', intval($searchInfo['status']));
        }
        // 所属员工
        if ($searchInfo['admin_id']) {
            $expression->where('tbl_member.admin_id', '=', $searchInfo['admin_id']);
        }
        // 会员等级
        if (is_numeric($searchInfo['level'])) {
            switch (true) {
                case ($searchInfo['level_type'] == Constants::LevelType_Member) :
                    if (intval($searchInfo['level']) >= 0) {
                        $expression->where('tbl_member.level', intval($searchInfo['level']));
                    } else {
                        $expression->where('tbl_member.level', '<>', 0);
                    }
                    break;
                case ($searchInfo['level_type'] == Constants::LevelType_Distributor) :
                    if (intval($searchInfo['level']) >= 0) {
                        $expression->where('tbl_distributor.level', intval($searchInfo['level']));
                    } else {
                        $expression->where('tbl_member.is_distributor', '<>', 0);
                    }
                    break;
                case ($searchInfo['level_type'] == Constants::LevelType_Agent) :
                    if (intval($searchInfo['level']) >= 0) {
                        $expression->where('tbl_member.agent_level', intval($searchInfo['level']));
                    } else {
                        $expression->where('tbl_member.agent_level', '<>', 0);
                    }
                    break;
                case ($searchInfo['level_type'] == Constants::LevelType_Dealer) :
                    if (intval($searchInfo['level']) >= 0) {
                        $expression->where('tbl_member.dealer_level', intval($searchInfo['level']));
                    } else {
                        $expression->where('tbl_member.dealer_level', '<>', 0);
                    }
                    break;
                case ($searchInfo['level_type'] == Constants::LevelType_AreaAgent) :
                    if (intval($searchInfo['level']) >= 0) {
                        $expression->where('area.area_agent_level', intval($searchInfo['level']));
                    } else {
                        $expression->where('tbl_member.is_area_agent', '<>', 0);
                    }
                    break;
                default:
                    if (intval($searchInfo['level']) >= 0) {
                        $expression->where('tbl_member.level', intval($searchInfo['level']));
                    } else {
                        $expression->where('tbl_member.level', '<>', 0);
                    }
                    break;
            }
        }
        // 是否分销商
        if (is_numeric($searchInfo['is_distributor']) && intval($searchInfo['is_distributor']) >= 0) {
            $expression->where('tbl_member.is_distributor', intval($searchInfo['is_distributor']));
        }
        // 是否代理
        if (isset($searchInfo['is_agent']) && $searchInfo['is_agent'] >= 0) {
            if ($searchInfo['is_agent']) {
                $expression->where('tbl_member.agent_level', '>', 0);
            } else {
                $expression->where('tbl_member.agent_level', 0);
            }
        }

        // 是否经销商
        if (isset($searchInfo['is_dealer']) && $searchInfo['is_dealer'] >= 0) {
            if ($searchInfo['is_dealer'] > 0) {
                $expression->where('tbl_member.dealer_level', '>', $searchInfo['is_dealer']);
            } else {
                $expression->where('tbl_member.dealer_level', '=', 0);
            }
        }

        // 是否是区域代理
        if (isset($searchInfo['is_area_agent']) && $searchInfo['is_area_agent'] >= 0) {
            if ($searchInfo['is_area_agent'] == 0) {
                $expression->where('tbl_member.is_area_agent', '!=', 1);
            } else {
                $expression->where('tbl_member.is_area_agent', $searchInfo['is_area_agent']);
            }
        }

        // 是否是供应商
        if (isset($searchInfo['is_supplier']) && $searchInfo['is_supplier'] >= 0) {
            if ($searchInfo['is_supplier'] == 0) {
                $expression->where('tbl_member.is_supplier', '!=', 1);
            } else {
                $expression->where('tbl_member.is_supplier', $searchInfo['is_supplier']);
            }
        }

        // 是否是供应商(会员列表搜索用)
        if (intval($searchInfo['level_type']) === 6) {
            $expression->where('tbl_member.is_supplier', 1);
        }

        // 分销商状态，-99=不限制，-9=未申请或审核不通过或分销商删除掉的，-1=审核不通过，0=申请中，1=审核通过 -98 用于后台新增分销商，同时查询取消资格以及-9的状态
        if (is_numeric($searchInfo['distributor_status']) && intval($searchInfo['distributor_status']) != -99) {
            $distributorStatus = intval($searchInfo['distributor_status']);
            if ($distributorStatus == -9) {
                $expression->where(function (Builder $subQuery) {
                    $subQuery->where('tbl_distributor.status', Constants::DistributorStatus_RejectReview)
                        ->orWhereNull('tbl_distributor.status')
                        ->orWhere('tbl_distributor.is_del', Constants::DistributorIsDel_Yes);
                });
            } else if ($distributorStatus == -98) {
                $expression->where(function (Builder $subQuery) {
                    $subQuery->whereIn('tbl_distributor.status', [Constants::DistributorStatus_RejectReview, Constants::DistributorStatus_DeActive, Constants::DistributorStatus_WaitReview])
                        ->orWhereNull('tbl_distributor.status')
                        ->orWhere('tbl_distributor.is_del', Constants::DistributorIsDel_Yes);
                });
            } else {
                $expression->where('tbl_distributor.status', $distributorStatus);
            }
        }

        // 父级
        if (is_numeric($searchInfo['parent_id'])) {
            $expression->where('tbl_member.invite1', intval($searchInfo['parent_id']));
        }
        // 关键字（会员手机或姓名）
        if (trim($searchInfo['keyword'])) {
            $keyword = trim($searchInfo['keyword']);
            $keyword = preg_replace('/[\xf0-\xf7].{3}/', "", $keyword);
            if ($searchInfo['keyword_type'] == 2) {
                $expression->where(function ($query) use ($keyword) {
                    $query->where('admin.mobile', 'like', '%' . $keyword . '%')
                        ->orWhere('admin.name', 'like', '%' . $keyword . '%');
                });
            } else {
                $expression->where(function ($query) use ($keyword) {
                    $query->where('tbl_member.mobile', 'like', '%' . $keyword . '%')
                        ->orWhere('tbl_member.nickname', 'like', '%' . $keyword . '%')
                        ->orWhere('tbl_member.name', 'like', '%' . $keyword . '%');
                });
            }

        }
        // ids
        if ($searchInfo['ids']) {
            $ids = myToArray($searchInfo['ids']);
            if (count($ids) > 0) {
                $expression->whereIn('tbl_member.id', $ids);
                // 如果传了id数组，认为是显示全部
                $isShowAll = true;
            }
        }
        if ($maxLevel > 0) {
            if (array_key_exists('team_level', $searchInfo) && $searchInfo['finance_member_id']) {
                $teamLevel = intval($searchInfo['team_level']);
                if ($teamLevel < 1) $teamLevel = 1;
                if ($teamLevel > $maxLevel) $teamLevel = $maxLevel;
                $expression->where('tbl_member.invite' . $teamLevel, intval($searchInfo['finance_member_id']));
            }
        }
        // 排除某个会员id以及属于该会员的推荐树的id
        if ($searchInfo['no_member_id']) {
            $noMemberId = intval($searchInfo['no_member_id']);
            $expression->where('tbl_member.id', '!=', $noMemberId)
                ->whereNotIn('tbl_member.id', function ($subExpression) use ($noMemberId) {
                    $subExpression->from('tbl_member_parents')
                        ->where('site_id', $this->getSiteID())
                        ->where('parent_id', $noMemberId)
                        ->select('member_id');
                });
        }
        // 分销商等级
        if (is_numeric($searchInfo['distribution_level']) && intval($searchInfo['distribution_level']) >= 0) {
            $expression->where('tbl_distributor.level', intval($searchInfo['distribution_level']));
        }
        // 排除一些id
        if ($searchInfo['exclude_member_id']) {
            $excludeMemberIds = myToArray($searchInfo['exclude_member_id']);
            if (count($excludeMemberIds) > 0) {
                $expression->whereNotIn('tbl_member.id', $excludeMemberIds);
            }
        }
        #endregion

        // 总数据量
        $total = (clone $expression)->selectRaw('count(distinct tbl_member.id) as total')->first();
        $total = $total ? $total['total'] : 0;
        if ($isShowAll) {
            // 显示全部
            $pageSize = $total > 0 ? $total : 1;
            $page = 1;
        }

        $expression->leftJoin('tbl_member_level as level', 'tbl_member.level', '=', 'level.id');
        $expression->leftJoin('tbl_dealer_level as dlevel', 'tbl_member.dealer_level', '=', 'dlevel.id');
        $expression->leftJoin('tbl_member as invite', 'invite.id', '=', 'tbl_member.invite1');
        $expression->leftJoin('tbl_district as tbl_district_prov', 'tbl_member.prov', '=', 'tbl_district_prov.id');
        $expression->leftJoin('tbl_district as tbl_district_city', 'tbl_member.city', '=', 'tbl_district_city.id');
        $expression->leftJoin('tbl_district as tbl_district_area', 'tbl_member.area', '=', 'tbl_district_area.id');


        $expression->forPage($page, $pageSize);

        // 查询字段
        $expression->selectRaw('distinct tbl_member.id as member_id');
        $expression->addSelect(['tbl_member.*', 'level.name as level_name', 'invite.nickname as invite_nickname', 'invite.name as invite_name']);
        $expression->addSelect(['tbl_district_prov.name as prov_text', 'tbl_district_city.name as city_text', 'tbl_district_area.name as area_text']);
        $expression->addSelect(['tbl_distributor.passed_at as distributor_passed_at', 'tbl_distribution_level.name as distribution_level_name']);
        $expression->addSelect(['admin.name as admin_name', 'admin.mobile as admin_mobile','admin.position as position']);
        $expression->addSelect(['dlevel.name as dealer_level_name']);

//
//        // 交易金额
//        $extendData[]="(select value from tbl_statistics where site_id=".$this->getSiteID()." and tbl_statistics.member_id = tbl_member.id  and type=".Constants::Statistics_member_tradeMoney." ) as trade_money";
//
//        //交易次数
//        $extendData[] = "(select value from tbl_statistics where site_id=".$this->getSiteID()." and tbl_statistics.member_id = tbl_member.id and type=".Constants::Statistics_member_tradeTime." ) as trade_time";

        // 对于哪个人来说
        $financeMemberId = intval($searchInfo['finance_member_id']);
        if ($financeMemberId > 0) {
            $activeCommissionSql = " tbl_finance.member_id = '" . $financeMemberId . "' and tbl_finance.type = " . CoreConstants::FinanceType_Commission . " and tbl_finance.status = " . CoreConstants::FinanceStatus_Active . " and tbl_finance.in_type = " . CoreConstants::FinanceInType_Commission . " and tbl_finance.money > 0";
            // 会员的佣金贡献
            if ($searchInfo['offer_commission']) {
                $extendData[] = "(select sum(tbl_finance.money) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id where " . $activeCommissionSql . " and tbl_finance.member_id = '" . $financeMemberId . "' and tbl_order.member_id = tbl_member.id) as offer_commission";
            }
            // 会员的分销订单数
            if ($searchInfo['offer_order_num']) {
                $extendData[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id where " . $activeCommissionSql . " and tbl_order.member_id = tbl_member.id) as offer_order_num";
            }
            // 团队带来的佣金
            if ($searchInfo['sub_team_commission']) {
                $extendData[] = "(select sum(tbl_finance.money) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . MemberEntity::getSubUserSql("tbl_member.id", $maxLevel, 1, 'order_member') . " or tbl_member.id = tbl_order.member_id)) as sub_team_commission";
            }
            // 团队带来的分销订单数
            if ($searchInfo['sub_team_order_num']) {
                $extendData[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . MemberEntity::getSubUserSql("tbl_member.id", $maxLevel, 1, 'order_member') . " or tbl_member.id = tbl_order.member_id)) as sub_team_order_num";
            }
            // 分销团队人数
            if ($searchInfo['sub_team_member_num']) {
                $extendData[] = "(select count(1) from tbl_member as submember where (" . MemberEntity::getSubUserSql("tbl_member.id", $maxLevel) . ") and (" . MemberEntity::getSubUserSql($financeMemberId, $maxLevel) . ")) as sub_team_member_num";
            }
        }
        if (is_array($extendData)) {
            foreach ($extendData as $extendItem) {
                $expression->addSelect(DB::raw($extendItem));
            }
        }

        // 特殊统计
        if ($countExtend) {
            //$expression->addSelect(['member_count.deal_money_real', 'member_count.deal_times_real', 'member_count.buy_money_real', 'member_count.buy_times_real']);
            $expression->addSelect(['member_count.trade_money', 'member_count.trade_time']);
        }
        $expression->with(['label' => function ($query) {
            $query->orderBy('tbl_member_label.parent_id', "asc");
            $query->orderBy('sort', 'asc');
        }]);
        $list = $expression
            ->orderBy('tbl_member.id', 'desc')
            ->get();

        $last_page = ceil($total / $pageSize);

        // 处理数据
        $memberIds = [];
        foreach ($list as $item) {
            // 清理关键数据
            $item->password = '';
            $item->pay_password = '';
            $item->trade_money = $item->trade_money ? moneyCent2Yuan($item->trade_money) : 0;
            $item->trade_time = $item->trade_time ? $item->trade_time : 0;
            // 文字
            $item->status_text = CoreConstants::getMemberStatusText(intval($item->status));
            $item->terminal_type_text = CoreConstants::getTerminalTypeText(intval($item->terminal_type));
            $item->sex_text = CoreConstants::getSexText(intval($item->sex));
            if ($searchInfo['sub_team_commission'] && !$item->sub_team_commission) {
                $item->sub_team_commission = 0;
            }
            $item->agent_level_name = Constants::getAgentLevelTextForAdmin($item->agent_level);
            $memberIds[] = $item->id;
        }

        //二次查询，查出统计数据
        if ($countExtend) {
            //公众号粉丝
            $countFans = WxUserModel::query()->selectRaw('invite,count(id) as count')->where('site_id', $this->getSiteID())->whereIn('invite', $memberIds)->groupBy('invite')->get();
            //直系下级人数
            $countMembers = MemberModel::query()->selectRaw('invite1 as invite,count(id) as count')->where('site_id', $this->getSiteID())->whereIn('invite1', $memberIds)->groupBy('invite')->get();
            // 积分收入
            $countPointIn = PointModel::query()->selectRaw('member_id,sum(point) as point_in')->where('site_id', $this->getSiteID())->where('status', CoreConstants::PointStatus_Active)->where('point', '>', 0)->where('expiry_at', '>=', date('Y-m-d H:i:s'))->groupBy('member_id')->get();
            // 积分支出
            $countPointOut = PointModel::query()->selectRaw('member_id,sum(point) as point_out')->where('site_id', $this->getSiteID())->where('point', '<', 0)->groupBy('member_id')->get();
            // 余额收入
            $countBalanceIn = FinanceModel::query()->selectRaw('member_id,sum(money) as balance_in')->where('site_id', $this->getSiteID())->where('status', CoreConstants::FinanceStatus_Active)->where('money', '>', 0)->where('type', CoreConstants::FinanceType_Normal)->groupBy('member_id')->get();
            // 余额支出
            $countBalanceOut = FinanceModel::query()->selectRaw('member_id,sum(money) as balance_out')->where('site_id', $this->getSiteID())->where('status', '<>', CoreConstants::FinanceStatus_Invalid)->where('money', '<', 0)->where('type', CoreConstants::FinanceType_Normal)->groupBy('member_id')->get();
            // 区域代理等级名称
            $areaAgentLevelName = AreaAgentModel::query()->leftJoin('tbl_area_agent_level as level', 'tbl_area_agent.area_agent_level' , 'level.id')->whereIn('member_id', $memberIds)->where('tbl_area_agent.site_id', $this->getSiteID())->groupBy('member_id')->pluck('name', 'member_id')->all();
            //组合数据
            foreach ($list as $item) {
                //下级粉丝
                $fansNum = $countFans->where('invite', '=', $item->id)->first();
                $item->fans = $fansNum ? $fansNum->count : 0;
                //下线会员
                $memberNum = $countMembers->where('invite', '=', $item->id)->first();
                $item->directly_distributor_count = $memberNum ? $memberNum->count : 0;
                // 积分
                $pointIn = $countPointIn->where('member_id', '=', $item->id)->first();
                $item->point_in = $pointIn ? $pointIn->point_in : 0;
                $pointOut = $countPointOut->where('member_id', '=', $item->id)->first();
                $item->point_out = $pointOut ? $pointOut->point_out : 0;
                $item->point = intval($item->point_in) - abs(intval($item->point_out));
                //余额
                $balanceIn = $countBalanceIn->where('member_id', '=', $item->id)->first();
                $item->balance_in = $balanceIn ? $balanceIn->balance_in : 0;
                $balanceOut = $countBalanceOut->where('member_id', '=', $item->id)->first();
                $item->balance_out = $balanceOut ? $balanceOut->balance_out : 0;
                $item->balance = intval($item->balance_in) - abs(intval($item->balance_out));
                $item->area_agent_level_name = $areaAgentLevelName[$item->id];
            }
        }
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 获取此会员的自购次数(付款成功算起)
     * @return int
     */
    public function getBuyTimes()
    {
        if ($this->checkExist()) {
            return OrderModel::query()
                ->where('member_id', $this->getMemberId())
                ->where('site_id', $this->getSiteID())
                ->whereIn('status', BaseShopOrder::getPaidStatusList())
                ->count();
        } else {
            return 0;
        }
    }

    /**
     * 获取此会员的自购金额(付款成功算起)
     * @return int
     */
    public function getBuyMoney()
    {
        if ($this->checkExist()) {
            return OrderModel::query()
                ->where('member_id', $this->getMemberId())
                ->where('site_id', $this->getSiteID())
                ->whereIn('status', BaseShopOrder::getPaidStatusList())
                ->sum('money');
        } else {
            return 0;
        }

    }

    /**
     * 获取此会员的成交次数(过维权期算起)
     * @return int
     */
    public function getDealTimes()
    {
        if ($this->checkExist()) {
            return OrderModel::query()
                ->where('member_id', $this->getMemberId())
                ->where('site_id', $this->getSiteID())
                ->whereIn('status', BaseShopOrder::getDealStatusList())
                ->count();
        } else {
            return 0;
        }
    }

    /**
     * 获取此会员的成交金额(过维权期算起)
     * @return int 自购金额(单位：分)
     */
    public function getDealMoney()
    {
        if ($this->checkExist()) {
            return OrderModel::query()
                ->where('member_id', $this->getMemberId())
                ->where('site_id', $this->getSiteID())
                ->whereIn('status', BaseShopOrder::getDealStatusList())
                ->sum('money');
        } else {
            return 0;
        }
    }

    /**
     * 返回指定会员的下级会员数量
     * @return int
     */
    public function getTotalTeam()
    {
        if ($this->checkExist()) {
            $sql = "select count(1) as total_team from tbl_member where status = 1 and (" . Distributor::getSubUserSql(intval($this->getMemberId())) . ")";
            $res = MemberModel::runSql($sql);
            return intval($res[0]->total_team);
        } else {
            return 0;
        }
    }

    /**
     * 返回指定会员的直属下级分销商数量
     * @return int
     */
    public function getDirectlyDistributorNum()
    {
        if ($this->checkExist()) {
            $sql = "select count(1) as directly_under_distributor from tbl_member where status = 1 and is_distributor = 1 and invite1 = " . intval($this->getMemberId());
            $res = MemberModel::runSql($sql);
            return $res[0]->directly_under_distributor;
        } else {
            return 0;
        }
    }

    /**
     * 返回指定会员的直属下级会员数量
     * @return int
     */
    public function getDirectlyMemberNum()
    {
        if ($this->checkExist()) {
            $sql = "select count(1) as directly_under_member from tbl_member where status = 1 and is_distributor <> 1 and invite1 = " . intval($this->getMemberId());
            $res = MemberModel::runSql($sql);
            return $res[0]->directly_under_member;
        } else {
            return 0;
        }
    }

    /**
     * 返回指定会员的下属下级分销商数量
     * @return int
     */
    public function getSubDistributorNum()
    {
        if ($this->checkExist()) {
            $sql = "select count(1) as subordinate_distributor from tbl_member where status = 1 and is_distributor = 1 and (" . Distributor::getSubUserSql(intval($this->getMemberId())) . ")";
            $res = MemberModel::runSql($sql);
            return $res[0]->subordinate_distributor;
        } else {
            return 0;
        }
    }

    /**
     * 返回指定会员的下属下级会员数量
     * @return int
     */
    public function getSubMemberNum()
    {
        if ($this->checkExist()) {
            $sql = "select count(1) as subordinate_member from tbl_member where status = 1 and is_distributor <> 1 and (" . Distributor::getSubUserSql(intval($this->getMemberId())) . ")";
            $res = MemberModel::runSql($sql);
            return $res[0]->subordinate_member;
        } else {
            return 0;
        }
    }

    /**
     * 输出当前会员的上级链条
     * @return array
     */
    public function getParentChain()
    {
        if ($this->checkExist()) {
            $chain = [];
            $maxLevel = int($this->getDistributionSetting()->level);
            if ($maxLevel > CoreConstants::MaxInviteLevel) {
                $maxLevel = CoreConstants::MaxInviteLevel;
            }
            for ($i = 1; $i <= $maxLevel; $i++) {
                $chain[] = $this->getModel()['invite' . $i];
            }
            return $chain;
        } else {
            return [];
        }
    }

    /**
     * 检查手机是否存在
     * @param $mobile
     * @return bool
     */
    public function mobileExist($mobile)
    {
        if (empty(trim($mobile))) return false;

        $exist = MemberModel::where('site_id', $this->getSiteID())
            ->where('mobile', trim($mobile))
            ->first();

        return $exist;
    }

    /**
     * 检查邮箱是否存在
     * @param $email
     * @return bool
     */
    public function emailExist($email)
    {
        if (empty(trim($email))) return false;

        $exist = MemberModel::where('site_id', $this->getSiteID())
            ->where('email', trim($email))
            ->count();

        return $exist > 0;
    }

    /**
     * 获取 site_id
     * @return int|mixed
     */
    public function getSiteID()
    {
        return $this->member->getSiteId();
    }

    /**
     * 获取会员模型底层实例（MemberModal）
     * @param $useCache 是否使用缓存(默认使用)
     * @return null
     */
    public function getModel($useCache = true)
    {
        // cli模式强制不走缓存
        if (isInCli()) {
            $useCache = false;
        }
        if ($this->checkExist()) {
            // 不使用缓存 需要重新查找member model
            if (!$useCache) {
                $this->member->setCache($useCache);
                $member_id = $this->getMemberId();
                $this->member->find($member_id);
            }
            return $this->member->getModel();
        } else {
            return null;
        }
    }

    /**
     * 获取member实例
     * @return null|MemberEntity
     */
    public function getMember()
    {
        return $this->member;
    }

    /**
     * 获取会员ID
     * @return int
     */
    public function getMemberId()
    {
        if ($this->checkExist()) {
            return $this->member->getModelId();
        } else {
            return 0;
        }
    }

    /**
     * 登录
     * @throws \Exception
     */
    public function login()
    {
        if ($this->isActive()) {
            $this->member->login();
            return true;
        }
        return false;
    }

    /**
     * 设定上下级
     * @param $parentId
     * @param $orderId 在订单支付成功后再绑定上下级关系时需要，用来在绑定关系后分佣
     * @throws \Exception
     */
    public function setParent($parentId, $orderId = 0)
    {
        $parentId = intval($parentId);
        if ($this->checkExist()) {
            $pModel = MemberModel::find($parentId);
            $mobile = $pModel ? $pModel->mobile : '';
            //没有手机号，不绑定上下级，当上家不存在时，不检测，这是为了处理在首次交费时才绑定关系时，如果没有推荐人，可以设定为0的情况
            if ($pModel && !preg_match('/^\d{11}$/', $mobile)) return;
            $this->member->setParent($parentId, $orderId);
        }
    }

    /**
     * 设定推荐员工
     * @param $adminId
     * @throws \Exception
     */
    public function setFromAdmin($adminId)
    {
        $this->member->setFromAdmin($adminId);
    }

    /**
     * 获取微信的OpenId
     */
    public function getWxOpenId()
    {
        return $this->member->getOfficialAccountOpenId();
    }

    public function getAlipayUserId()
    {
        return $this->member->getAlipayUserId();
    }

    public function getAlipayAccount()
    {
        return $this->member->getAlipayAccount();
    }

    /**
     * 获取手机号码
     * @return string
     */
    public function getMobile()
    {
        if ($this->checkExist()) {
            return $this->getModel()->mobile;
        }
        return '';
    }

    /**
     * 获取某种类型的第三方信息
     * @param $authType
     * @return bool|\Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getAuthInfo($authType)
    {
        $authData = false;
        if ($this->checkExist()) {
            if ($authType == CoreConstants::MemberAuthType_WxOficialAccount) {
                $wxOpenId = $this->member->getOfficialAccountOpenId();
                if ($wxOpenId) {
                    $authData = WxUserModel::query()
                        ->where('site_id', $this->getSiteID())
                        ->where('openid', $wxOpenId)
                        ->first();
                }
            }
        }

        return $authData;
    }

    /**
     * 检查密码是否正确
     * @param $passwordCheck
     * @return bool
     */
    public function passwordCheck($passwordCheck)
    {
        if (!$this->checkExist() || empty($passwordCheck) || $this->passwordIsNull()) return false;
        return Hash::check($passwordCheck, $this->getModel()->password);
    }

    /**
     * 密码是否为空（自动生成的当作空）
     * @return bool
     */
    public function passwordIsNull()
    {
        if (!$this->checkExist()) return true;
        // 如果密码为空或者密码加密后长度小于16（没有填写密码的时候生成的随机窜是8位的）
        if (!$this->getModel()->password || strlen($this->getModel()->password) < 16) return true;
        return false;
    }

    /**
     * 检查支付密码是否正确
     * @param $passwordCheck
     * @return bool
     */
    public function payPasswordCheck($passwordCheck)
    {
        if (!$this->checkExist() || empty($passwordCheck) || $this->payPasswordIsNull()) return false;
        return Hash::check($passwordCheck, $this->getModel()->pay_password);
    }

    /**
     * 支付密码是否为空（自动生成的当作空）
     * @return bool
     */
    public function payPasswordIsNull()
    {
        if (!$this->checkExist()) return true;
        // 如果密码为空或者密码加密后长度小于16（没有填写密码的时候生成的随机窜是8位的）
        if (!$this->getModel()->pay_password || strlen($this->getModel()->pay_password) < 16) return true;
        return false;
    }

    /**
     * 匹配一个上级会员id是第几层
     * @param $inviteId
     * @return int
     */
    public function parseInviteLevel($inviteId)
    {
        if ($this->checkExist()) {
            for ($i = 1; $i <= CoreConstants::MaxInviteLevel; $i++) {
                if ($this->getModel()['invite' . $i] == $inviteId) {
                    return $i;
                }
            }
        }
        return 0;
    }

    /**
     * 处理一些数据，比如密码、支付密码等
     * @param array $data
     * @return array
     */
    private function processData(array $data)
    {
        // 密码
        if ($data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        // 支付密码
        if ($data['pay_password']) {
            $data['pay_password'] = Hash::make($data['pay_password']);
        } else {
            unset($data['pay_password']);
        }

        return $data;
    }

    /**
     * 特殊查询条件
     * @param array $params
     * @return array
     */
    private function getRelationSql(array $params = [])
    {
        $sqls = [];
        // 交易金额
        $sqls[] = "(select value from tbl_statistics where site_id=" . $this->getSiteID() . " and tbl_statistics.member_id = tbl_member.id  and type=" . Constants::Statistics_member_tradeMoney . " ) as trade_money";

        //交易次数
        $sqls[] = "(select value from tbl_statistics where site_id=" . $this->getSiteID() . " and tbl_statistics.member_id = tbl_member.id and type=" . Constants::Statistics_member_tradeTime . " ) as trade_time";

        return $sqls;
    }

    public static function getHeadUrl($headurl)
    {
        if (!$headurl) $headurl = "images/default_head.png";
        elseif (!preg_match('@^(http:|https:)@', $headurl)) $headurl = Site::getSiteComdataDir() . $headurl;
        return $headurl;
    }

    /**
     * 获取查找某会员的下级佣金的SQL
     * @param $memberId
     * @param int $startLevel
     * @param string $table
     * @return string
     */
    public static function getSubFinanceSql($memberId, $maxLevel = 0, $startLevel = 1, $table = '')
    {
        // 默认从配置读取
        if ($maxLevel <= 0) {
            $distributionSetting = new DistributionSetting();
            $setting = $distributionSetting->getSettingModel();
            $maxLevel = $setting->level;
        }

        return FinanceHelper::getSubUserSql($memberId, $maxLevel, $startLevel, $table);
    }

    /**
     * 获取分销商配置
     * @return null
     */
    private function getDistributionSetting()
    {
        if (is_null($this->distributionSetting)) {
            $setting = new DistributionSetting();
            $this->distributionSetting = $setting->getSettingModel();
        }

        return $this->distributionSetting;
    }

    /**
     * @param $type 根据不同的类型获取不同等级列表，无分页，用于产品列表
     * @return null
     */
    public static function getLevelList($type)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        switch (true) {
            case $type == Constants::LevelType_Member :
                $data = MemberLevelModel::query()
                    ->where('site_id', $siteId)
                    ->orderBy('weight', 'asc')
                    ->select(['id', 'name'])
                    ->get();
                break;
            case $type == Constants::LevelType_Distributor:
                $data = DistributionLevelModel::query()
                    ->where('site_id', $siteId)
                    ->orderBy('weight', 'asc')
                    ->select(['id', 'name'])
                    ->get();
                break;
            case $type == Constants::LevelType_Agent:
                $agentSetting = AgentBaseSetting::getCurrentSiteSetting();
                $agentLevel = $agentSetting->level;
                if ($agentLevel == 3) $data = [['id' => 1, 'name' => '一级代理'], ['id' => 2, 'name' => '二级代理'], ['id' => 3, 'name' => '三级代理']];
                if ($agentLevel == 2) $data = [['id' => 1, 'name' => '一级代理'], ['id' => 2, 'name' => '二级代理']];
                if ($agentLevel == 1) $data = [['id' => 1, 'name' => '一级代理']];
                break;
            case $type == Constants::LevelType_Dealer:
                $data = DealerLevelModel::query()
                    ->where('site_id', $siteId)
                    ->orderBy('weight', 'asc')
                    ->where('parent_id', 0)
                    ->select(['id', 'name'])
                    ->get();;
                break;
            case $type == Constants::LevelType_AreaAgent:
                $data = AreaAgentLevelModel::query()
                    ->where('site_id', $siteId)
                    ->where('status', 1)
                    ->orderBy('weight', 'asc')
                    ->select(['id', 'name'])
                    ->get();;
                break;
            case $type == Constants::LevelType_Supplier:
                $data = [];
                break;
            default:
                $data = MemberLevelModel::query()
                    ->where('site_id', $siteId)
                    ->orderBy('weight', 'asc')
                    ->select(['id', 'name'])
                    ->get();
                break;
        }
        return $data;
    }

    /**
     * 合并会员帐号
     * @param $fromMemberId 来源会员ID
     * @param $toMemberId 目标会员ID
     */
    public static function mergeMember($fromMemberId, $toMemberId)
    {
        DB::beginTransaction();
        try {
            /*
            暂时不做财务 积分 优惠券等的合并，因为如果用户在微信授权后送了一些 积分 优惠券 赠金 什么的，合并这些会导致重复
            */
            $sqls = [
                //"update tbl_finance set member_id = $toMemberId where member_id = $fromMemberId",
                //"update tbl_coupon_item set member_id = $toMemberId where member_id = $fromMemberId",
                //"update tbl_point set member_id = $toMemberId where member_id = $fromMemberId",
                "update tbl_order set member_id = $toMemberId where member_id = $fromMemberId",
                "update tbl_shopping_cart set member_id = $toMemberId where member_id = $fromMemberId",
                "update tbl_group_buying set head_member_id = $toMemberId where head_member_id = $fromMemberId",
                //"update tbl_member_address set member_id = $toMemberId where member_id = $fromMemberId"
            ];
            foreach ($sqls as $sql) {
                DB::update($sql);
            }
            //购物车进行数据合并
            $carts = ShoppingCartModel::query()->where('member_id', $toMemberId)->orderBy('id')->get();
            foreach ($carts as $item) {
                $old = $carts->where('product_id', '=', $item->product_id)->where('product_skus_id', '=', $item->product_skus_id)->where('id', '<', $item->id)->first();
                if ($old) {
                    $old->product_quantity += $item->product_quantity;
                    $old->save();
                    $item->delete();
                }
            }
            //处理粉丝记录
            $memberModel = MemberModel::find($toMemberId);
            DB::update("update tbl_wx_user set invite = " . $memberModel->invite1 . " where member_id = $toMemberId and site_id = " . $memberModel->site_id);
            //删除旧会员
            MemberModel::find($fromMemberId)->delete();
            //添加日志
            OpLog::Log(\App\Modules\ModuleShop\Libs\Constants::OpLogType_MemberMerge, $fromMemberId, $fromMemberId, $toMemberId);
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
        }
    }

    /**
     * 会员没有手机号时替换为--
     * @param $mobile
     * @param string $string
     * @return string
     */
    public static function memberMobileReplace($mobile, $string = '--')
    {
        if (!preg_match('/^\d{11}$/', $mobile)) {
            return $string;
        }
        return $mobile;
    }
}