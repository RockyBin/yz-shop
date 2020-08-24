<?php
/**
 * Created by Aison
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShop;
use Illuminate\Http\Request;
use YZ\Core\Site\Config;
use YZ\Core\Site\Site;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\FileUpload\FileUpload;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\SiteConfig\WithdrawConfig;
use App\Modules\ModuleShop\Libs\Distribution\Become\BecomeDistributorHelper;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use App\Modules\ModuleShop\Libs\Member\MemberInfo;

/**
 * 分销中心
 * Class DistributionController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member
 */
class DistributionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 分销中心首页接口
     * @return array
     */
    public function index()
    {
        try {
            // 分销商设置信息
            $config = $this->getDistributionSetting();
            if ($config->level == 0) {
                return makeApiResponse(401, trans('shop-front.distributor.open_setting'));
            }
            $is_distributor = 0; // 是否分销商
            $distributor = new Distributor($this->memberId);
            $distributorData = [];
            if ($distributor->checkExist()) {
                if (intval($distributor->getModel()->status) == \App\Modules\ModuleShop\Libs\Constants::DistributorStatus_Active) {
                    $is_distributor = 1;
                }
                $distributorData = $distributor->getInfo([
                    'finance_member_id' => $this->memberId,
                    'return_parent_info' => true,
                    'return_total_team' => true,
                    'return_every_level_data' => true,
                    'return_subordinate_distributor' => true,
                    'return_subordinate_member' => true,
                    'return_sub_self_purchase_order_num' => true,
                    'return_sub_self_purchase_commission' => true,
                    'return_sub_team_order_num' => true,
                    'return_commission_order_num' => true,
                    'show_del' => true,
                ]);
                if (!$distributorData['parent_info']) {
                    $distributorData['parent_info']['nickname'] = '公司';
                    $shopConfig = new StoreConfig();
                    $distributorData['parent_info']['mobile'] = ($shopConfig->getInfo())['data']['custom_mobile'];
                }
                // 是否显示邀请码
                $inviteCodeShow = true;
                $inviteCode = $this->memberId;
                $siteConfig = Site::getCurrentSite()->getConfig()->getModel();
                if ($siteConfig['show_code_pages'] && $showPages = json_decode($siteConfig['show_code_pages'], true)) {
                    $inviteCodeShow = $showPages['distribution'] == 1;
                }
            }
            // 佣金数据
            $commissionData = $this->getCommissionData();
            // 会员信息
            $memberData = [];
            if ($is_distributor == 1) {
                // 统计团队信息
                $distributorTeamInfo = (new MemberInfo($this->memberId))->getDistributorInfo();
                $memberData = [
                    'id' => $distributorData['member_info']['id'],
                    'nickname' => $distributorData['member_info']['nickname'],
                    'headurl' => $distributorData['member_info']['headurl'],
                    'team_info' => $distributorTeamInfo,
                ];
            } else {
                $member = New Member($this->memberId);
                $memberData = [
                    'id' => $member->getMemberId(),
                    'nickname' => $member->getModel()->nickname,
                    'headurl' => $member->getModel()->headurl,
                    'agent_level' => $member->getModel()->agent_level,
                ];
            }

            $ShopConfig = (new Config(Site::getCurrentSite()->getSiteId()))->getModel();
            // 根据设置显示相应的等级名称
            $siteConfig = Site::getCurrentSite()->getConfig()->getModel();
            if ($distributorData['member_info']['agent_level'] > 0) {
                $agentLevelText = \App\Modules\ModuleShop\Libs\Constants::getAgentLevelTextForFront($distributorData['member_info']['agent_level'], $distributorData['member_info']['agent_level'] . "级代理");
            }
            if ($siteConfig->distribution_center_show_level == 1) {
                if ($distributorData['base_info']) {
                    $distributorData['base_info']->distributor_level_name = $agentLevelText ? $agentLevelText : $distributorData['base_info']->distributor_level_name;
                } else {
                    $distributorData['base_info']['distributor_level_name'] = $agentLevelText ? $agentLevelText : $distributorData['base_info']['distributor_level_name'];
                }
            }
            $withdrawConfig = New WithdrawConfig();
            $withdrawConfigData = $withdrawConfig->getInfo(0, true);

            unset($distributorData['member_info']);
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), [
                "is_distributor" => $is_distributor,
                "member" => $memberData,
                "distributor" => $distributorData,
                "commission" => $commissionData,
                "config" => $config,
                'withdraw_config' => $withdrawConfigData,
                "invite_code_show" => $inviteCodeShow,
                "invite_code" => $inviteCode,
                "show_small_shop" => SmallShop::getInfo(['member_id' => $this->memberId])['id'] ? true : false,
                "small_shop_status" => $ShopConfig->small_shop_status,
                "small_shop_optional_product_status" => $ShopConfig->small_shop_optional_product_status]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 团队列表
     * @param Request $request
     * @return array
     */
    public function getSubTeamList(Request $request)
    {
        try {
            $page = intval($request->input('page'));
            $pageSize = intval($request->input('page_size'));
            $teamLevel = intval($request->input('team_level'));
            if ($page < 1) $page = 1;
            if ($pageSize < 1) $pageSize = 20;

            $param = [
                'team_level' => $teamLevel,
                'finance_member_id' => $this->memberId,
                'sub_team_commission' => true,
                'sub_team_order_num' => true,
                'sub_team_member_num' => true,
                'count_extend' => true,
                'page' => $page,
                'page_size' => $pageSize,
            ];
            if ($request->keyword) {
                $param['keyword'] = $request->keyword;
            }
            if ($request->search_distributor_max_level) {
                $param['search_distributor_max_level'] = $request->search_distributor_max_level;
            }

            $member = new Member();
            $data = $member->getList($param);

            foreach ($data['list'] as $dataItem) {
                $dataItem['sub_team_commission'] = moneyCent2Yuan($dataItem['sub_team_commission']);
                $dataItem['deal_money'] = moneyCent2Yuan($dataItem['deal_money']);
                $dataItem['buy_money'] = moneyCent2Yuan($dataItem['buy_money']);
            }

            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 下级代理商列表
     * @param Request $request
     * @return array
     */
    public function getDistributorList(Request $request)
    {
        try {
            $subMemberId = $request->input('sub_member_id');
            if (empty($subMemberId)) {
                // 当前分销商
                $subMemberId = $this->memberId;
            } else {
                // 下级分销商
                $subDistributorData = $this->getDistributorInfo($subMemberId);
                if (!$subDistributorData['is_distributor']) {
                    return makeApiResponseFail(trans('shop-front.distributor.not_exist'));
                }
                // 验证当前用户查看权限
                if (!$this->checkRoleForSubMember($subMemberId, true)) {
                    return makeApiResponseFail(trans('shop-front.distributor.not_sub_distributor'));
                }
            }

            // 获取数据
            $data = $this->getSubDistributorData($subMemberId, $request->input('page'), $request->input('page_size'));
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 下级会员列表
     * @param Request $request
     * @return array
     */
    public function getMemberList(Request $request)
    {
        try {
            $subMemberId = $request->input('sub_member_id');
            if (empty($subMemberId)) {
                // 当前分销商会员列表
                $subMemberId = $this->memberId;
            } else {
                // 下级分销商会员列表
                if (!$this->checkRoleForSubMember($subMemberId, true)) {
                    // 验证查看权限
                    return makeApiResponseFail(trans('shop-front.member.not_sub_member'));
                }
            }

            // 获取数据
            $data = $this->getMemberData($subMemberId, $request->page, $request->page_size);
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 下级分销商信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            // 是否显示推荐码
            $inviteCodeShow = false;
            $inviteCode = '';
            $subMemberId = $request->input('sub_member_id');
            if (empty($subMemberId)) {
                // 当前分销商
                $subMemberId = $this->memberId;
                // 分销配置
                $settingModel = $this->getDistributionSetting();
                if ($settingModel && $settingModel->show_code) {
                    $inviteCodeShow = true;
                    $inviteCode = $this->memberId;
                }
            } else {
                // 下级分销商
                // 验证当前用户查看权限
                if (!$this->checkRoleForSubMember($subMemberId)) {
                    return makeApiResponseFail(trans('shop-front.distributor.not_sub_distributor'));
                }
            }
            // 获取数据
            $distributorData = $this->getDistributorInfo($subMemberId);
            // 如果不是分销商也不展示推荐码
            if (!$distributorData['is_distributor']) {
                $inviteCodeShow = false;
                $inviteCode = '';
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), [
                "is_distributor" => $distributorData['is_distributor'],
                "is_active" => $distributorData['is_active'],
                "distributor" => $distributorData['distributor'],
                "invite_code_show" => $inviteCodeShow,
                "invite_code" => $inviteCode,
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 佣金列表
     * @param Request $request
     * @return array
     */
    public function getCommissionList(Request $request)
    {
        try {
            $finance = new Finance($this->siteId);
            $param = $request->toArray();
            $param['single_member'] = true;
            $param['order_info'] = true;
            $param['member_id'] = $this->memberId;
            $param['types'] = Constants::FinanceType_Commission;
            $data = $finance->getList($param);
            // 处理数据
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $commissionText = "";
                    $item->money = moneyCent2Yuan(abs($item->money));
                    $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
                    $item->money_real = moneyCent2Yuan(abs($item->money_real));

                    if ($item->member_id == $item->from_member1 && $item->in_type == Constants::FinanceInType_Commission) {
                        $commissionText .= $item->buyer_nickname;
                        $item->created_at = date('Y.m.d H:i:s', strtotime($item->order_created_at));
                        if ($item->status == Constants::FinanceStatus_Freeze) {
                            $commissionText .= '-交易中';
                        } elseif ($item->status == Constants::FinanceStatus_Active) {
                            $commissionText .= '-交易成功';
                        } elseif ($item->status == Constants::FinanceStatus_Invalid) {
                            $commissionText .= '-交易失败';
                        }
                    } else if ($item->member_id != $item->from_member1 && $item->in_type == Constants::FinanceInType_Commission) {
                        $commissionText .= $item->buyer_nickname;
                        $item->created_at = date('Y.m.d H:i:s', strtotime($item->order_created_at));
                        if ($item->status == Constants::FinanceStatus_Freeze) {
                            $commissionText .= '-交易中';
                        } elseif ($item->status == Constants::FinanceStatus_Active) {
                            $commissionText .= '-交易成功';
                        } elseif ($item->status == Constants::FinanceStatus_Invalid) {
                            $commissionText .= '-交易失败';
                        }
                    } else if ($item->out_type == Constants::FinanceOutType_Withdraw || $item->out_type == Constants::FinanceOutType_CommissionToBalance) {
                        $commissionText .= trans('shop-front.diy_word.commission') . '提现';
                        if ($item->status == Constants::FinanceStatus_Active) {
                            $item->created_at = date('Y.m.d H:i:s', strtotime($item->active_at));
                        }
                    }
                    $item->commission_text = $commissionText;
                    $item->pay_type_text = Constants::getPayTypeText($item->pay_type, '');

                    if ($item['out_type'] == Constants::FinanceInType_CommissionToBalance) {
                        $item->out_account = trans('shop-front.diy_word.balance');
                    } else {
                        $item->out_account = Constants::getPayTypeWithdrawText($item->pay_type);
                    }
                }
            }
            // 查询基础信息
            if ($param['show_commission_info']) {
                // 佣金数据
                $commissionData = $this->getCommissionData();
                $data['commission'] = $commissionData;
                // 检查提现最小额度
                $canWithdraw = false;
                $commissionBalance = moneyYuan2Cent(floatval($commissionData['balance']));
                $withdrawConfig = new WithdrawConfig();
                $withdrawConfigData = $withdrawConfig->getInfo();
                $minWithdrawMoney = intval($withdrawConfigData->min_money);
                if ($commissionBalance > 0 && $commissionBalance >= $minWithdrawMoney) {
                    $canWithdraw = true;
                }
                $data['can_withdraw'] = $canWithdraw;
            }

            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 请求
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function apply(Request $request)
    {
        try {
            $config = $this->getDistributionSetting();
            if ($config->level == 0) {
                return makeApiResponse(401, trans('shop-front.distributor.open_setting'));
            }
            if ($config->apply_status == 0) {
                return makeApiResponse(402, trans('shop-front.distributor.open_apply_setting'));
            }
            // 检查是否已经申请或已经是分销商，如果是则直接返回200
            $distributor = new Distributor($this->memberId);
            if ($distributor->checkExist()) {
                $status = intval($distributor->getModel()->status);
                if ($status != -1 && intval($distributor->getModel()->is_del) != 1) {
                    return makeApiResponseSuccess(trans('shop-front.distributor.apply_success'), [
                        'distributor_status' => $status,
                    ]);
                }
            }
            $terminalType = getCurrentTerminal();
            $member = new Member($this->memberId, $this->siteId);
            $condition = DistributionSetting::getCurrentSiteSetting()->condition;

            $applyAgain = $request->apply_again;
            //当用户点击再次申请的时候，用户已经知道被拒绝的原因了，所以不需要再去判断用户是否被拒绝过。
            if (!$applyAgain) {
                // 判断用户是否有被拒绝过和获取用户被拒绝的信息
                $checkResult = BecomeDistributorHelper::checkApply($member);
                if ($checkResult) {
                    return makeApiResponseFail(trans('shop-front.distributor.reject'), $checkResult);
                }
            }

            //如果是表单申请，不需要自动提交申请，需要手动
            if ($condition !== LibsConstants::DistributionCondition_Apply) {
                $result = BecomeDistributorHelper::applyBecomeDistributor($member, $terminalType);
            }
            if ($result['code'] == 200) {
                return makeApiResponseSuccess(trans('shop-front.distributor.apply_success'), $result['data']);
            } else {
                //如果是表单申请，此时需要告诉前端，用户需要手动填写表单
                if ($condition === 1) {
                    return makeApiResponseFail(trans('shop-front.distributor.need_form'), ['condition_type' => LibsConstants::DistributionCondition_Apply]);
                } else {
                    $result['data']['setting'] = DistributionSetting::getCurrentSiteSetting();
                    return makeApiResponseFail($result['msg'], $result['data']);
                }

            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 上传 身份证 与 营业执照
     * @param Request $request
     * @return array
     */
    public function file(Request $request)
    {
        try {
            $distributor = new Distributor($this->memberId);
            if ($distributor->isActive()) {
                return makeApiResponseFail(trans('shop-front.member.is_distributor'));
            }

            $hasFile = false;

            // 身份证
            $idcard_file = '';
            if ($request->hasFile('idcard_file_data')) {
                $subPath = '/distributor/';
                $upload_filename = "idcard_" . $this->memberId . '_' . time();
                $upload_filepath = Site::getSiteComdataDir('', true) . $subPath;
                $upload_handle = new FileUpload($request->file('idcard_file_data'), $upload_filepath, $upload_filename);
                $upload_handle->save();
                $idcard_file = $subPath . $upload_handle->getFullFileName();
                $hasFile = true;
            }

            // 营业执照
            $business_license_file = '';
            if ($request->hasFile('business_license_file_data')) {
                $subPath = '/distributor/';
                $upload_filename = "business_license_" . $this->memberId . '_' . time();
                $upload_filepath = Site::getSiteComdataDir('', true) . $subPath;
                $upload_handle = new FileUpload($request->file('business_license_file_data'), $upload_filepath, $upload_filename);
                $upload_handle->save();
                $business_license_file = $subPath . $upload_handle->getFullFileName();
                $hasFile = true;
            }

            if (!$hasFile) {
                return makeApiResponseFail(trans('shop-front.distributor.file_required'));
            }

            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), [
                'idcard_file' => $idcard_file,
                'business_license_file' => $business_license_file
            ]);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 申请表单提交
     * @param Request $request
     * @return array
     */
    public function form(Request $request)
    {
        try {
            $terminalType = getCurrentTerminal();
            $member = new Member($this->memberId, $this->siteId);
            $param = $request->input();
            // 构造数据
            if ($param['extend_fields']) {
                $param['extend_fields'] = json_encode($param['extend_fields']);
            } else {
                $param['extend_fields'] = json_encode([]);
            }
            //表单提交，说明要再次申请，不需要检测是否被拒绝过。
            $param['applyAgain'] = true;
            $result = BecomeDistributorHelper::applyBecomeDistributor($member, $terminalType, $param);
            if ($result['code'] == 200) {
                return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $result['data']);
            } else {
                return makeApiResponseFail($result['msg'], $result['data']);
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取配置信息
     */
    public function config()
    {
        try {
            $distributionSetting = new DistributionSetting();
            $data = $distributionSetting->getInfo();
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 检查当前分销商是否有查看该下级分销商的权限
     * @param $subMemberId 要查看信息的会员id
     * @param bool $forSub 查看的信息是否有关下级的
     * @return bool
     */
    private function checkRoleForSubMember($subMemberId, $forSub = false)
    {
        $settingModel = $this->getDistributionSetting();
        $configMaxLevel = intval($settingModel->level);
        // 列表类其实是不能查看最下级的分销商列表和会员列表的，所以检查时最大等级减一
        if ($forSub) {
            $configMaxLevel = $configMaxLevel - 1;
        }
        if ($configMaxLevel <= 0) return false;

        $subMember = new Member($subMemberId);
        if (!$subMember->checkExist()) {
            return false;
        }

        $subMemberModel = $subMember->getModel();
        $hasRole = false;

        // 逐层检查
        for ($curLevel = 1; $curLevel <= $configMaxLevel; $curLevel++) {
            if ($subMemberModel['invite' . $curLevel] == $this->memberId) {
                $hasRole = true;
                break;
            }
        }

        return $hasRole;
    }

    /**
     * 获取下级分销商列表
     * @param $memberId
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    private function getSubDistributorData($memberId, $page = 1, $pageSize = 20)
    {
        $page = intval($page);
        $pageSize = intval($pageSize);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $param = [
            'status' => \App\Modules\ModuleShop\Libs\Constants::DistributorStatus_Active,
            'parent_id' => $memberId,
            'finance_member_id' => $this->memberId,
            'return_sub_team_commission' => true,
            'return_sub_team_order_num' => true,
            'return_total_team' => true,
            'page' => $page,
            'page_size' => $pageSize,
        ];

        $total = Distributor::getList(array_merge($param, ['return_total_record' => 1]));
        $pageCount = ceil($total / $pageSize);
        $list = Distributor::getList($param);

        // 计算是否可以展开下一层列表
        $showSubList = false;
        $settingModel = $this->getDistributionSetting();
        $configMaxLevel = intval($settingModel->level);
        if ($configMaxLevel > 1) {
            if ($memberId == $this->memberId) {
                $showSubList = true;
            } else {
                $subMember = new Member($memberId);
                $parseLevel = $subMember->parseInviteLevel($this->memberId);
                if ($parseLevel > 0 && $parseLevel < $configMaxLevel - 1) {
                    $showSubList = true;
                }
            }
        }

        return [
            "total" => $total,
            "page_size" => $pageSize,
            "current" => $page,
            "last_page" => $pageCount,
            "list" => $list,
            'show_sub_list' => $showSubList
        ];
    }

    /**
     * 获取分销商信息
     * @param $memberId
     * @return array
     */
    private function getDistributorInfo($memberId)
    {
        $is_distributor = false; // 是否分销商
        $distributor = new Distributor($memberId);
        $distributorData = [];
        if ($distributor->checkExist()) {
            $is_distributor = true;
            $distributorData = $distributor->getInfo([
                'finance_member_id' => $this->memberId,
                'return_parent_info' => true,
                'return_total_team' => true,
                'return_commission_money' => true,
                'return_directly_under_distributor' => true,
                'return_directly_under_member' => true,
                'return_subordinate_distributor' => true,
                'return_subordinate_member' => true,
                'return_commission_order_num' => true,
                'return_sub_team_commission' => true,
                'return_sub_team_order_num' => true,
                'return_sub_self_purchase_order_num' => true,
                'return_sub_self_purchase_commission' => true,
                'return_sub_directly_order_num' => true,
                'return_sub_subordinate_order_num' => true,
                'return_sub_directly_commission' => true,
                'return_sub_subordinate_commission' => true,
            ]);
            unset($distributorData['member_info']['password']);
            unset($distributorData['member_info']['pay_password']);
        }

        return [
            "is_distributor" => $is_distributor,
            "is_active" => $distributor->isActive(),
            "distributor" => $distributorData
        ];
    }

    /**
     * 获取会员列表（非分销商）
     * @param $memberId
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    private function getMemberData($memberId, $page = 1, $pageSize = 20)
    {
        $member = new Member(null, $this->siteId);
        $data = $member->getList([
            "status" => 1,
            "is_distributor" => 0,
            "parent_id" => $memberId,
            "finance_member_id" => $this->memberId,
            "count_extend" => true,
            "offer_commission" => true,
            "offer_order_num" => true,
            "page" => $page,
            "page_size" => $pageSize
        ]);
        foreach ($data['list'] as $item) {
            $item->offer_commission = moneyCent2Yuan($item->offer_commission);
        }
        return $data;
    }

    /**
     * 获取分销设置
     * @return null
     */
    private function getDistributionSetting()
    {
        $distributionSetting = new DistributionSetting();
        return $distributionSetting->getSettingModel();
    }

    /**
     * 获取佣金信息
     * @return array
     */
    private function getCommissionData()
    {
        // 可用佣金
        $commissionBalance = FinanceHelper::getMemberCommissionBalance($this->memberId);
        // 历史佣金
        $commissionHistory = FinanceHelper::getMemberTotalCommission($this->memberId);
        // 提现中佣金
        $commissionCheck = FinanceHelper::getMemberCommissionCheck($this->memberId);
        // 待结算佣金
        $commissionUnsettled = FinanceHelper::getMemberCommissionUnsettled($this->memberId);
        // 结算失败
        $commissionFail = FinanceHelper::getMemberCommissionFail($this->memberId);

        return [
            "balance" => moneyCent2Yuan(intval($commissionBalance)),
            "history" => moneyCent2Yuan(intval($commissionHistory)),
            "check" => moneyCent2Yuan(intval(abs($commissionCheck))),
            "unsettled" => moneyCent2Yuan(intval($commissionUnsettled)),
            "fail" => moneyCent2Yuan(intval($commissionFail)),
        ];
    }
}