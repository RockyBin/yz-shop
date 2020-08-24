<?php
/**
 * 代理商相关业务
 * User: liyaohui
 * Date: 2019/6/27
 * Time: 14:54
 */

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Member\Member;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberModel;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Constants as CoreConstants;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\Member\Member as AppMember;

class Agent
{
    use DispatchesJobs;
    protected $siteId = 0;

    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
    }

    /**
     * 获取代理列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAgentList($params, $page = 1, $pageSize = 20)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $agent = AgentModel::query()->where('tbl_agent.site_id', $siteId);
        // $agent->whereIn('tbl_agent.status', [Constants::AgentStatus_Active,Constants::AgentStatus_Cancel]);
        // 手机号 昵称搜索
        $agent->leftJoin('tbl_member as member', 'member.id', '=', 'tbl_agent.member_id');
        $agent->leftJoin('tbl_site_admin as admin', 'member.admin_id', '=', 'admin.id');
        if (isset($params['keyword'])) {
            if ($params['keyword_type'] == 2) {
                $agent->where(function ($query) use ($params) {
                    $query->where('admin.mobile', 'like', '%' . $params['keyword'] . '%');
                    $query->orWhere('admin.name', 'like', '%' . $params['keyword'] . '%');
                });
            } else {
                $agent->where(function ($query) use ($params) {
                    $query->where('member.nickname', 'like', '%' . $params['keyword'] . '%');
                    $query->orWhere('member.mobile', 'like', '%' . $params['keyword'] . '%');
                    $query->orWhere('member.name', 'like', '%' . $params['keyword'] . '%');
                });
            }
        }
        if (isset($params['status']) && $params['status'] != -99) {
            $agent->where('tbl_agent.status', $params['status']);
        } else {
            $agent->whereIn('tbl_agent.status', [Constants::AgentStatus_Active, Constants::AgentStatus_Cancel]);
        }
        // 等级搜索
        if (isset($params['agent_apply_level'])) {
            $params['agent_apply_level'] = myToArray($params['agent_apply_level']);
            $agent->whereIn('member.agent_level', $params['agent_apply_level']);
        }
        // 成为代理时间搜索
        if (isset($params['passed_at_start'])) {
            $agent->where('passed_at', '>=', $params['passed_at_start']);
        }
        if (isset($params['passed_at_end'])) {
            $agent->where('passed_at', '<=', $params['passed_at_end']);
        }

        // 统计数据条数
        $total = $agent->count();
        $last_page = ceil($total / $pageSize);
        // 父级数据
        $agent->leftJoin('tbl_member as parent', 'member.agent_parent_id', '=', 'parent.id');

        // 统计下级数量
        $agent->leftJoin('tbl_agent_parents as agent_count', 'agent_count.parent_id', '=', 'tbl_agent.member_id');

        // 使用member_id分组
        $agent->groupBy('tbl_agent.member_id');
        // 要查找的字段 使用子查询统计分红
        $nativeSubQueryString = '';
        $nativeSubQueryBindings = [];
        $nativeSubQueryList = self::getNativeSubQuery($params, $nativeSubQueryBindings);
        if (count($nativeSubQueryList) > 0) {
            $nativeSubQueryString = implode(',', $nativeSubQueryList) . ',';
        }
        //团队数量需要加上自己，所以加1
        $agent->selectRaw('SUM(CASE agent_count.agent_level WHEN 2 THEN 1 ELSE 0 END) AS agent_total2,
            SUM(CASE agent_count.agent_level WHEN 3 THEN 1 ELSE 0 END) AS agent_total3,
            COUNT(agent_count.id)+1 AS agent_total,
            ' . $nativeSubQueryString . '
            tbl_agent.member_id,
            member.agent_level as agent_apply_level,
            tbl_agent.status,
            passed_at,
            member.nickname,
            member.mobile,
            member.name,
            member.agent_parent_id,
            parent.nickname as parent_nickname,
            member.headurl,
            admin.name as admin_name,
            admin.mobile as admin_mobile,
            tbl_agent.cancel_history_agent_level,
            parent.mobile as parent_mobile', $nativeSubQueryBindings);
        $agent->forPage($page, $pageSize);
        $agent->orderBy('tbl_agent.passed_at', 'desc');
        $list = $agent->get();
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
     * @param $params
     * @param array $bindings
     * @return array
     */
    private static function getNativeSubQuery($params, array &$bindings)
    {
        $list = [];
        // 总分红
        if ($params['show_agent_commission']) {
            $list[] = '(select sum(money) from tbl_finance where member_id = tbl_agent.member_id and money>0 and type=? and site_id=? and status=?) as agent_commission';
            $list[] = '(select sum(if(`status`=? and money>0,money,0) + if(`status`<>? and money<0,money,0)) from tbl_finance where tbl_finance.member_id = tbl_agent.member_id and tbl_finance.type=?) as agent_commission_balance';
            $bindings[] = CoreConstants::FinanceType_AgentCommission;
            $bindings[] = Site::getCurrentSite()->getSiteId();
            $bindings[] = CoreConstants::FinanceStatus_Active;
            $bindings[] = CoreConstants::FinanceStatus_Active;
            $bindings[] = CoreConstants::FinanceStatus_Invalid;
            $bindings[] = CoreConstants::FinanceType_AgentCommission;
        }
        // sum(if(`status`=? and money>0,money,0) + if(`status`<>? and money<0,money,0)) as agent_commission_balance
        return $list;
    }

    /**
     * 获取申请代理的列表
     * @param $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getApplyAgentList($params, $page = 1, $pageSize = 20)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $agent = AgentModel::query()->where('tbl_agent.site_id', $siteId);
        // 默认按审核时间排序
        if (isset($params['status'])) {
            $agent->where('tbl_agent.status', $params['status']);
            if ($params['status'] == Constants::AgentStatus_WaitReview) {
                $agent->orderBy('tbl_agent.created_at', 'desc');
            } else {
                $agent->orderBy('tbl_agent.passed_at', 'desc');
            }
        } else {
            $agent->where('tbl_agent.status', Constants::AgentStatus_WaitReview);
            $agent->orderBy('tbl_agent.created_at', 'desc');
        }

        // 手机号 昵称搜索
        $agent->leftJoin('tbl_member as member', 'member.id', '=', 'tbl_agent.member_id');

        if (isset($params['keyword'])) {
            $agent->where(function ($query) use ($params) {
                $query->where('member.nickname', 'like', '%' . $params['keyword'] . '%');
                $query->orWhere('member.name', 'like', '%' . $params['keyword'] . '%');
                $query->orWhere('member.mobile', 'like', '%' . $params['keyword'] . '%');
            });
        }
        // 等级搜索
        if (isset($params['agent_apply_level'])) {
            $agent->where('agent_apply_level', $params['agent_apply_level']);
        }
        // 申请成为代理时间搜索
        if (isset($params['created_at_start'])) {
            $agent->where('tbl_agent.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $agent->where('tbl_agent.created_at', '<=', $params['created_at_end']);
        }
        // 申请成为代理时间搜索
        if (isset($params['passed_at_start'])) {
            $agent->where('tbl_agent.passed_at', '>=', $params['passed_at_start']);
        }
        if (isset($params['passed_at_end'])) {
            $agent->where('tbl_agent.passed_at', '<=', $params['passed_at_end']);
        }
        // 统计数据条数
        $total = $agent->count();
        $last_page = ceil($total / $pageSize);
        // 会员等级
        $agent->leftJoin('tbl_member_level as mlevel', 'mlevel.id', '=', 'member.level');
        // 分销等级
        $agent->leftJoin('tbl_distributor as distri', function ($join) {
            $join->on('distri.member_id', '=', 'tbl_agent.member_id')
                ->where('distri.status', Constants::DistributorStatus_Active);
        });
        $agent->leftJoin('tbl_distribution_level as dlevel', 'dlevel.id', '=', 'distri.level');

        // 要查找的字段
        $agent->select([
            'tbl_agent.member_id',
            'agent_apply_level',
            'member.nickname',
            'member.mobile as member_mobile',
            'member.headurl',
            'member.name',
            'mlevel.name as level_name',
            'dlevel.name as distribution_level',
            'tbl_agent.*'
        ]);
        $agent->forPage($page, $pageSize);
        $list = $agent->get();
        foreach ($list as &$item) {
            $item->clound_condition = json_decode($item->clound_condition);
            if ($item->initial_pay_type) {
                $item->initial_pay_info = self::getInitialPayInfo($item->initial_pay_type);
                //0:线上支付 1:线下支付
                $item->offline_or_online = in_array($item->initial_pay_type, CoreConstants::getOfflinePayType(true)) ? 1 : 0;
            }
            if ($item->initial_pay_history_info) {
                $item->initial_pay_history_info = json_decode($item->initial_pay_history_info);
            }
            if ($item->initial_pay_certificate) {
                $item->initial_pay_certificate = explode(',', $item->initial_pay_certificate);
            }
            if ($item->initial_money) {
                $item->initial_money = moneyCent2Yuan($item->initial_money);
            }
            $item->member_mobile = \App\Modules\ModuleShop\Libs\Member\Member::memberMobileReplace($item->member_mobile);
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
     * 获取加盟费详细信息
     * @param $params
     */
    public static function getInitialPayInfo($paytype)
    {
        switch (true) {
            case $paytype == CoreConstants::PayType_WeixinQrcode:
                $type = Constants::PayConfigType_WxPay;
                break;
            case $paytype == CoreConstants::PayType_AlipayQrcode || $paytype == CoreConstants::PayType_AlipayAccount:
                $type = Constants::PayConfigType_AliPay;
                break;
            case $paytype == CoreConstants::PayType_Bank :
                $type = Constants::PayConfigType_BankPay;
                break;
            case $paytype == CoreConstants::PayType_Balance :
                $type = Constants::PayConfigType_BankPay;
                break;
            case $paytype == CoreConstants::PayType_Weixin :
                $type = Constants::PayConfigType_WxPay;
                break;
            case $paytype == CoreConstants::PayType_Alipay :
                $type = Constants::PayConfigType_AliPay;
                break;
            case $paytype == CoreConstants::PayType_TongLian :
                $type = Constants::PayConfigType_TongLian;
                break;
            default:
                $type = null; //拿取所有
        }

        $payConfig = new PayConfig($type);
        $payInfo = $payConfig->getInfo();
        return $payInfo;
    }

    /**
     * 获取业绩统计
     * @param $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getPerformanceList($params, $page = 1, $pageSize = 20)
    {
        $showAll = $params['is_all'] ? true : false;
        $siteId = Site::getCurrentSite()->getSiteId();
        $query = DB::query()->from('tbl_agent')
            ->leftJoin('tbl_member', 'tbl_agent.member_id', 'tbl_member.id')
            ->leftJoin('tbl_member as parent', 'parent.id', 'tbl_member.agent_parent_id')
            ->where('tbl_agent.site_id', $siteId);
        // 代理状态
        if (is_numeric($params['status'])) {
            $query->where('tbl_agent.status', $params['status']);
        }
        // 代理等级
        if (is_numeric($params['agent_level'])) {
            $agentLevel = intval($params['agent_level']);
            if ($agentLevel >= 0) {
                $query->where('tbl_member.agent_level', $params['agent_level']);
            }
        }
        // 上级领导
        if (is_numeric($params['agent_parent_id'])) {
            $agentParentId = intval($params['agent_parent_id']);
            if ($agentParentId >= 0) {
                $query->where('tbl_member.agent_parent_id', $params['agent_parent_id']);
            } else if ($agentParentId == -2) {
                $query->where('tbl_member.agent_parent_id', '>', 0);
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
                $query->whereIn('tbl_agent.member_id', $memberIds);
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
        $timeParam = AgentPerformance::parseTime($countType, $countYear, $countNum);
        $query->addSelect(DB::raw("(select sum(money) from tbl_agent_performance where tbl_agent_performance.member_id = tbl_agent.member_id and count_period = '" . $period . "' and order_time >= '" . $timeParam['start'] . " 00:00:00' and order_time <= '" . $timeParam['end'] . " 23:59:59') as performance"));
        // 统计数量
        $total = DB::query()->from(DB::raw("({$query->toSql()}) as tmp"))
            ->mergeBindings($query)->count();
        $query->addSelect('tbl_agent.member_id', 'tbl_member.agent_level as member_agent_level', 'tbl_member.nickname as member_nickname', 'tbl_member.mobile as member_mobile', 'tbl_member.name as member_name', 'tbl_member.headurl as member_headurl', 'tbl_member.agent_parent_id as agent_parent_id', 'parent.nickname as agent_parent_agent_level', 'parent.nickname as agent_parent_nickname', 'parent.name as agent_parent_name', 'parent.mobile as agent_parent_mobile', 'parent.headurl as agent_parent_headurl');
        // 排序
        if ($params['order_by'] == 'performance') {
            if ($params['order_sort'] == 'asc') {
                $query->orderBy('performance');
            } else {
                $query->orderByDesc('performance');
            }
        } else {
            $query->orderByDesc('tbl_agent.passed_at');
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
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
            'time_start' => $timeParam['start'],
            'time_end' => $timeParam['end'],
            'time_sign' => $timeParam['sign'],
        ];
    }

    /**
     * 后台添加代理
     * @param array $params 参数
     * @param bool $returnCheck 是否返回检测数据
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function adminAddAgent($params, $returnCheck = false)
    {
        $agentCheck = $this->becomeAgentBefore($params, true);
        if ($agentCheck && $agentCheck->status == Constants::AgentStatus_Active) {
            throw new \Exception("代理已存在");
        }

        $nowDate = Carbon::now();
        // 如果代理记录存在 直接改变状态为生效即可
        if ($agentCheck) {
            // 待审核和已取消资格的 后台让用户自己处理 这里返回数据即可
            if (
                $returnCheck
                && ($agentCheck->status == Constants::AgentStatus_WaitReview
                    || $agentCheck->status == Constants::AgentStatus_Cancel)
            ) {
                return $agentCheck;
            }
            $agent = $agentCheck;
        } else {
            $agent = new AgentModel();
            $agent->site_id = $this->siteId;
            $agent->member_id = $params['member_id'];
            // 申请和通过时间一样
            $agent->created_at = $nowDate;
        }

        // 后台添加的 默认状态为生效
        $agent->status = Constants::AgentStatus_Active;
        $agent->agent_apply_level = $params['agent_apply_level'];
        // 通过时间
        $agent->passed_at = $agent->upgrade_at = Carbon::now();
        $save = $agent->save();
        if ($save) {
            $this->becomeAgentAfter($agent);
            return true;
        } else {
            throw new \Exception("新增代理失败");
        }
    }

    /**
     * 前台申请代理
     * @param $params
     * @return array|bool
     * @throws \Exception
     */
    public function applyAgent($params)
    {
        $agentCheck = $this->becomeAgentBefore($params, true);
        // 先获取需要的字段
        $agentApplySetting = new AgentApplySetting();
        $agentForm = $agentApplySetting->getApplyForm();
        if (!$agentForm['defaultFields'] && !$agentForm['extendFields']) {
            return makeServiceResult(400, "表单字段数据错误");
        }
        // 检测是否有加盟协议 并同意
        if ($agentForm['agreement']['show'] == 1 && !$params['agreement']) {
            return makeServiceResult(400, "请先同意协议");
        }
        // 检测旧有的代理状态
        $agent = $agentCheck;
        if (!$agent) $agent = new AgentModel();
        if ($agentCheck && in_array($agent->status, [Constants::AgentStatus_WaitReview, Constants::AgentStatus_Active])) {
            return makeServiceResult(400, "您已经申请过代理，请不要重复申请");
        }
        // 用来标识是个人申请还是公司申请 0为个人 1为公司
        $applyType = $params['business_type'] ?: 0;
        // 如果是个人 把不需要的字段剔除
        if ($applyType == 0) {
            unset($agentForm['defaultFields']['company']);
            unset($agentForm['defaultFields']['business_license']);
            unset($agentForm['defaultFields']['business_license_file']);
        }
        // 预设字段
        foreach ($agentForm['defaultFields'] as $key => $val) {
            $formVal = trim($params[$key]);
            // 必填项不能为空
            if ($val && $formVal === '') {
                return makeServiceResult(400, "必填项不能为空");
            }
            $agent->$key = $formVal;
        }
        $extendForm = [];
        $extendFieldsValue = collect($params['extend_fields'] ?: []);
        // 自定义字段
        foreach ($agentForm['extendFields'] as $key => $val) {
            $formExtVal = $extendFieldsValue->where('name', $val['name'])->first();
            $formExtVal = trim($formExtVal['value']);
//            $formExtVal = trim($params['extend_fields'][$val['name']]);
            // 必填项
            if ($val['show'] && $val['require'] && $formExtVal === '') {
                return makeServiceResult(400, "必填项不能为空");
            }
            $extendForm[] = ['name' => $val['name'], 'value' => $formExtVal];
        }
        // 如果有地址
        if (isset($agentForm['defaultFields']['address'])) {
            if ($agentForm['defaultFields']['address'] && (!$params['prov'] || !$params['city'] || !$params['area'])) {
                return makeServiceResult(400, "请选择地址");
            }
            $agent->prov = $params['prov'] ?: 0;
            $agent->city = $params['city'] ?: 0;
            $agent->area = $params['area'] ?: 0;
        }
        $agent->extend_fields = $extendForm ? json_encode($extendForm) : null;
        $agent->business_type = $applyType; // 申请类型
        // 用户申请的 默认为等待审核
        $agent->status = Constants::AgentStatus_WaitReview;
        $agent->initial_pay_status = 0;
        $agent->site_id = $this->siteId;
        $agent->agent_apply_level = $params['agent_apply_level'];
        // 控制器去保证是当前登录的会员id
        $agent->member_id = $params['member_id'];
        // 申请时间
        $agent->created_at = Carbon::now();
        // 如果申请过并且被拒绝 先删掉拒绝的申请记录
        if ($agentCheck) {
            $agentCheck->delete();
        }

        // 加盟费
        $agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
        $needInitialFee = $agentBaseSetting->need_initial_fee;
        if ($needInitialFee) {
            $initial_money = 0;
            if ($agentBaseSetting->initial_fee) {
                $initial_money = json_decode($agentBaseSetting->initial_fee, true);
                $initial_money = moneyYuan2Cent($initial_money[$params['agent_apply_level']]);
            }
            $agent->initial_money = $initial_money;
            $agent->initial_pay_type = $params['initial_pay_type'];
            $agent->initial_pay_history_info = json_encode($this->initialHistoryInfo($params['initial_pay_type']));
            if ($params['initial_pay_certificate']) $agent->initial_pay_certificate = $params['initial_pay_certificate'];
            if ($initial_money && ($agent->initial_pay_type == CoreConstants::PayType_Balance || in_array($agent->initial_pay_type, CoreConstants::getOnlinePayType()))) {
                $agent->status = Constants::AgentStatus_Applying;
                $agent->initial_pay_status = 0;
            }
            if (in_array($agent->initial_pay_type, CoreConstants::getOfflinePayType())) {
                $agent->initial_pay_status = 1;
            }
        }
        $save = $agent->save();
        if ($save) {
            return true;
        } else {
            return makeServiceResultFail("提交失败");
        }
    }

    /**
     * 支付代理申请表单
     * @param int $memberId 会员ID
     * @param int $payType 支付类型
     * @param $vouchers 支付凭证
     *   当使用余额支付时，它是支付密码，此时数据格式为字符串
     *   当使用线下支付时，它是用户上传的线下支付凭证图片(最多三张)，此时数据格式为 \Illuminate\Http\UploadedFile|array $voucherFiles
     *   当使用线上支付时，它是支付成功后的入账财务记录
     * @param integer $feeType 1=加盟费，2=保证金(未实现)
     * @return void
     */
    public function payFee($memberId, $payType, $vouchers, $feeType = 1)
    {
        $agent = AgentModel::query()->where('member_id', $memberId)->first();
        // 余额支付的情况
        if ($payType == CoreConstants::PayType_Balance) {
            // 如果是余额支付 要验证支付密码
            $member = new AppMember($memberId);
            if ($member->payPasswordIsNull()) {
                return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
            }
            if (!$member->payPasswordCheck($vouchers)) {
                return makeApiResponse(406, trans('shop-front.shop.pay_password_error'));
            }
            // 扣钱
            if ($feeType === 1) {
                FinanceHelper::payAgentInitialMoneyWithBalance($member->site_id, $memberId, 'AgentInitial' . date('YmdHis'), $agent->initial_money);
                $agent->initial_pay_status = 1;
                $agent->status = Constants::AgentStatus_WaitReview;
            }
            $agent->save();
        }
        // 线下支付的情况(无需处理，在保存申请表单时已处理)

        // 线上支付的情况
        if (in_array($payType, \YZ\Core\Constants::getOnlinePayType())) {
            // 验证线上支付记录
            if ($vouchers['money'] == $agent->initial_money && intval($vouchers['status']) == 1) {
                if ($feeType === 1) $agent->initial_pay_status = 1;
                $agent->status = Constants::AgentStatus_WaitReview;
                $agent->initial_pay_certificate = $vouchers['tradeno'];
            }
            $agent->save();
        }

        return makeApiResponse(200, 'ok');
    }

    /**
     * 申请代理时，加盟费的相关历史数据
     * @return Agent
     * @throws \Exception
     */
    public function initialHistoryInfo($initialPayType)
    {
        $initialPayInfo = self::getInitialPayInfo($initialPayType);
        $bank = null;
        $accountName = null;
        $account = null;
        switch (true) {
            case $initialPayType == CoreConstants::PayType_WeixinQrcode:
                $account = $initialPayInfo->wx_qrcode;
                break;
            case $initialPayType == CoreConstants::PayType_AlipayQrcode :
                $account = $initialPayInfo->alipay_qrcode;
                break;
            case $initialPayType == CoreConstants::PayType_AlipayAccount:
                $account = $initialPayInfo->alipay_account;
                $accountName = $initialPayInfo->alipay_name;
                break;
            case $initialPayType == CoreConstants::PayType_Bank :
                $account = $initialPayInfo->bank_account;
                $accountName = $initialPayInfo->bank_card_name;
                $bank = $initialPayInfo->bank;
                break;
            default:
                $account = null;
        }
        $info = Payment::makeOffLinePaymentReceiptInfo($initialPayType, $account, $bank, $accountName);
        return $info;
    }


    /**
     * 成为/申请 代理前的检测
     * @param $params
     * @param bool $returnAgentData 是否返回代理记录
     * @return Agent|bool|\Illuminate\Database\Eloquent\Model|null|object
     * @throws \Exception
     */
    public function becomeAgentBefore($params, $returnAgentData = false)
    {
        // 申请的等级 不能超过后台设置的等级
        $agentSetting = (new AgentBaseSetting())->getSettingModel();
        if (!$agentSetting->level) {
            throw new \Exception("代理功能未开启");
        }
        if ($params['agent_apply_level'] && $params['agent_apply_level'] > $agentSetting->level) {
            throw new \Exception("代理等级错误");
        }
        $member = new Member($params['member_id'], $this->siteId);
        // 会员是否存在
        if (!$member->checkExist()) {
            throw new \Exception("会员不存在");
        }
        // 是否绑定了手机号
        $member->checkBindMobile();

        // 是否已是代理
        if ($agent = $this->checkAgentExist($params['member_id'])) {
            if ($returnAgentData) {
                return $agent;
            }
            throw new \Exception("代理记录已存在");
        }
    }

    /**
     * 成为代理之后的操作
     * @param $agent
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    private function becomeAgentAfter($agent)
    {
        // 会员表更新一下代理等级
        MemberModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $agent->member_id)
            ->update(['agent_level' => $agent->agent_apply_level]);

        // 更新团队关系
        AgentHelper::dispatchResetAgentParentsJob($agent->member_id);
        // 推荐奖励
        AgentReward::grantRecommendReward($agent->member_id, $agent->agent_apply_level);
        // 成为代理通知
        $this->dispatch(new MessageNotice(CoreConstants::MessageType_Agent_Agree, $agent));
        //改为用队列处理  代理商升级
        $this->dispatch(new UpgradeAgentLevelJob($agent->member_id));
    }


    /**
     * 审核代理
     * @param $param
     * @return int
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function verifyAgent($param)
    {
        // 构造更新的数据
        $update = [
            'passed_at' => Carbon::now(),
            'upgrade_at' => Carbon::now(),
        ];
        if ($param['status'] == Constants::AgentStatus_Active) {
            $update['status'] = Constants::AgentStatus_Active;
        } else if ($param['status'] == Constants::AgentStatus_RejectReview) {
            $update['status'] = Constants::AgentStatus_RejectReview;
            // 拒绝原因
            $update['reject_reason'] = $param['reject_reason'] ?: '';
        }
        $query = AgentModel::query()
            ->where('site_id', $this->siteId)
            ->where('status', Constants::AgentStatus_WaitReview);
        if (is_array($param['member_id'])) {
            $query->whereIn('member_id', $param['member_id']);
        } else {
            $query->where('member_id', $param['member_id']);
        }
        $save = $query->update($update);
        if ($save) {
            $memberIds = myToArray($param['member_id']);
            foreach ($memberIds as $memberId) {
                $agentModel = AgentModel::query()->where('site_id', $this->siteId)->where('member_id', $memberId)->first();
                if ($agentModel) {
                    if ($param['status'] == Constants::AgentStatus_Active) {
                        $this->becomeAgentAfter($agentModel);
                        // 新增加盟费财务记录
                        if ($param['receive_initial_money']) $this->addAgentInitialFinance($agentModel);
                    } else if ($param['status'] == Constants::AgentStatus_RejectReview) {
                        // 申请代理被拒通知
                        MessageNoticeHelper::sendMessageAgentReject($agentModel);
                    }
                }
            }
        }
        return $save;
    }

    /**
     * 新增加盟费财务记录
     * @param $agent
     * @return mixed
     */
    public function addAgentInitialFinance($agent)
    {
        if ($agent->initial_money && $agent->initial_pay_type) {
            $siteId = $agent->site_id;
            $memberId = $agent->member_id;
            $orderId = 'JMF_' . date('YmdHis');
            $money = $agent->initial_money;
            $payType = $agent->initial_pay_type;
            FinanceHelper::addAgentInitialMoney($siteId, $memberId, $orderId, $money, $payType);
        }
    }

    /**
     * 删除拒绝申请代理的记录
     * @param $memberId
     * @return mixed
     */
    public function delAgentRejectApplyData($memberId)
    {
        $del = AgentModel::query()->where('site_id', $this->siteId)
            ->where('status', Constants::AgentStatus_RejectReview)
            ->where('member_id', $memberId)
            ->delete();
        return $del;
    }

    /**
     * 恢复代理
     * @param $memberId
     * @return bool
     * @throws \Exception
     */
    public function resumeAgent($memberId)
    {
        $agent = $this->checkAgentExist($memberId);
        $agent->status = Constants::AgentStatus_Active;
        $save = $agent->save();
        if ($save) {
            $this->resumeAgentAfter($agent);
            // 成为代理通知
            MessageNoticeHelper::sendMessageAgentAgree($agent);
        } else {
            throw new \Exception("恢复代理出错");
        }
    }

    /**
     * 恢复代理之后的操作
     * @param $agent
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function resumeAgentAfter($agent)
    {
        // 会员表更新一下代理等级为0
        $this->resumeAgentUpdateMember($agent);
        // 更新团队关系
        AgentHelper::dispatchResetAgentParentsJob($agent->member_id);
        //改为用队列处理  代理商升级
        $this->dispatch(new UpgradeAgentLevelJob($agent->member_id));
    }

    /**
     * 恢复代理之后对会员代理的相关操作
     * @param $agent
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function resumeAgentUpdateMember($agent)
    {
        $agentModel = AgentModel::query()
            ->where('site_id', $this->siteId)
            ->where('member_id', $agent->member_id)
            ->first();
        $historyAgentLevel = $agentModel->cancel_history_agent_level;
        $agentModel->cancel_history_agent_level = 0;
        $agentModel->save();
        $member = MemberModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $agent->member_id)
            ->first();
        $member->agent_level = $historyAgentLevel;
        $member->save();
    }

    /**
     * 取消代理
     * @param $memberId
     * @return bool
     * @throws \Exception
     */
    public function cancelAgent($memberId)
    {
        $agent = $this->checkAgentExist($memberId);
        $agent->status = Constants::AgentStatus_Cancel;
        $save = $agent->save();
        if ($save) {
            $this->cancelAgentAfter($agent);
        } else {
            throw new \Exception("取消代理出错");
        }
    }

    /**
     * 取消代理之后的操作
     * @param $agent
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancelAgentAfter($agent)
    {
        // 会员表更新一下代理等级为0
        $this->cancelAgentUpdateMember($agent);
        // 更新团队关系
        AgentHelper::dispatchResetAgentParentsJob($agent->member_id);
        //改为用队列处理  代理商升级
        $this->dispatch(new UpgradeAgentLevelJob($agent->member_id));
    }

    /**
     * 取消代理之后对会员代理的相关操作
     * @param $agent
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancelAgentUpdateMember($agent)
    {
        $member = MemberModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $agent->member_id)
            ->first();
        $historyAgentLevel = $member->agent_level;
        $member->agent_level = 0;
        $member->save();
        $agentModel = AgentModel::query()
            ->where('site_id', $this->siteId)
            ->where('member_id', $agent->member_id)
            ->first();
        $agentModel->cancel_history_agent_level = $historyAgentLevel;
        $agentModel->save();
    }

    /**
     * 检测代理是否存在
     * @param int $memberId 会员id
     * @param bool $throwError 是否抛出错误
     * @return bool|\Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function checkAgentExist($memberId, $throwError = false)
    {
        $agent = AgentModel::query()->where('site_id', $this->siteId)
            ->where('member_id', $memberId)
            ->first();
        if (!$agent) {
            if ($throwError) {
                throw new \Exception("代理记录不存在");
            } else {
                return false;
            }
        }
        return $agent;
    }

    /**
     * @param UploadedFile $file 上传的文件
     * @param int $memberId 会员id
     * @param string $type 文件是身份证还是营业执照
     * @return string           返回保存后的文件路径
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, $memberId, $type = '')
    {
        $subPath = '/agent/';
        $upload_filename = "{$type}_" . $memberId . '_' . genUuid(8);
        $upload_filepath = Site::getSiteComdataDir('', true) . $subPath;
        $upload_handle = new FileUpload($file, $upload_filepath, $upload_filename);
        $upload_handle->reduceImageSize(1500);
        $file = $subPath . $upload_handle->getFullFileName();
        return $file;
    }
}