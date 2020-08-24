<?php
/**
 * 客户逻辑类
 * User: liyaohui
 * Date: 2020/2/28
 * Time: 13:46
 */

namespace App\Modules\ModuleShop\Libs\Crm;


use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\MemberLabel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\MemberLabelModel;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use YZ\Core\License\SNUtil;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;

class Member
{
    /**
     * crm获取会员列表
     * @param $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getList($params, $page = 1, $pageSize = 20)
    {
        $page = $page > 0 ? $page : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 20;
        $query = MemberModel::query()
            ->leftJoin('tbl_distributor as dis', function ($q) {
                $q->on('dis.member_id', 'tbl_member.id')
                    ->where('dis.status', Constants::DistributorStatus_Active);
            })
            ->where('tbl_member.site_id', getCurrentSiteId());
        // 关键字搜索
        if (trim($params['keyword'])) {
            $keyowrd = '%' . trim($params['keyword']) . '%';
            $query->where(function ($q) use ($keyowrd) {
                $q->where('tbl_member.name', 'like', $keyowrd)
                    ->orWhere('tbl_member.nickname', 'like', $keyowrd)
                    ->orWhere('tbl_member.mobile', 'like', $keyowrd);
            });
        }
        // 所属员工
        if ($params['admin_ids'] && is_array($params['admin_ids'])) {
            $query->whereIn('tbl_member.admin_id', $params['admin_ids']);
        }
        // 会员等级
        if ($params['member_level'] && is_array($params['member_level'])) {
            $query->whereIn('tbl_member.level', $params['member_level']);
        }
        // 分销商等级
        if ($params['distribution_level'] && is_array($params['distribution_level'])) {
            $query->whereIn('dis.level', $params['distribution_level']);
        }
        // 代理等级
        if ($params['agent_level'] && is_array($params['agent_level'])) {
            $query->whereIn('tbl_member.agent_level', $params['agent_level']);
        }
        // 经销商等级
        if ($params['dealer_level'] && is_array($params['dealer_level'])) {
            $query->whereIn('tbl_member.dealer_level', $params['dealer_level']);
        }
        // 经销商隐藏等级
        if ($params['dealer_hide_level'] && is_array($params['dealer_hide_level'])) {
            $query->whereIn('tbl_member.dealer_hide_level', $params['dealer_hide_level']);
        }
        // 注册时间开始
        if ($params['starttime']) {
            $query->where('tbl_member.created_at', '>=', $params['starttime']);
        }
        // 注册时间接触
        if ($params['endtime']) {
            $query->where('tbl_member.created_at', '<=', $params['endtime']);
        }
        //状态
        if (isset($params['status'])) {
            $query->where('tbl_member.status', $params['status']);
        }
        // 标签
        if ($params['label_ids'] && is_array($params['label_ids'])) {
            $labelId = $params['label_ids'];
            $query->whereHas('label', function ($q) use ($labelId) {
                $q->whereIn('label_id', $labelId)
                    ->groupBy('member_id')
                    ->havingRaw('count(member_id)=?', [count($labelId)]);
            });
        }
        $total = $query->count();
        $lastPage = ceil($total / $pageSize);
        $query->leftJoin('tbl_site_admin as st', 'st.id', 'tbl_member.admin_id');
        $list = $query->with(['label' => function ($q) {
            $q->select(['tbl_member_label.name', 'tbl_member_label.id', 'tbl_member_label.admin_id'])
                ->orderBy('admin_id');
        }])
            ->select([
                'tbl_member.id',
                'tbl_member.nickname',
                'tbl_member.name',
                'tbl_member.mobile',
                'tbl_member.headurl',
                'tbl_member.status',
                'dis.level as distribution_level',
                'tbl_member.agent_level',
                'tbl_member.dealer_level',
                'tbl_member.dealer_hide_level',
                'tbl_member.admin_id',
                'tbl_member.level as member_level',
                'st.name as admin_name'
            ])
            ->forPage($page, $pageSize)
            ->orderByDesc('tbl_member.id')
            ->get();
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    /**
     * 获取客户列表页高级筛选需要的数据
     * @param $adminId
     * @return array
     */
    public static function getMemberListSearchData($adminId)
    {
        $data = [];
        $siteId = Site::getCurrentSite()->getSiteId();
        // 是否有会员列表查看权限 没有的话不列出员工列表
//        if (SiteAdmin::hasPerm('member.view')) {
//            $data['admin_list'] = SiteAdminModel::query()
//                ->where('site_id', $siteId)
//                ->where('id', '!=', $adminId)
//                ->select(['name', 'id'])
//                ->get()->toArray();
//        }
        $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
        // 会员等级列表
        $data['member_level_list'] = MemberLevelModel::query()
            ->where('site_id', $siteId)
            ->where('status', 1)
            ->orderBy('weight')
            ->select(['name', 'id'])
            ->get()->toArray();
        // 分销商等级 需要检测是否有分销功能
        if ($sn->hasPermission(Constants::FunctionPermission_ENABLE_DISTRIBUTION)) {
            // 是否开启分销
            $distributionSetting = DistributionSetting::getCurrentSiteSetting();
            if ($distributionSetting->level > 0) {
                $data['distribution_level_list'] = DistributionLevelModel::query()
                    ->where('site_id', $siteId)
                    ->where('status', 1)
                    ->orderBy('weight')
                    ->select(['name', 'id'])
                    ->get()->toArray();
            }
        }
        // 代理商
        if ($sn->hasPermission(Constants::FunctionPermission_ENABLE_AGENT)) {
            // 是否开启了代理
            $agentSetting = AgentBaseSetting::getCurrentSiteSetting();
            $data['agent_level'] = $agentSetting->level;
        }
        // 经销商等级
        if ($sn->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK)) {
            $dealerLevel = DealerLevelModel::query()
                ->where('site_id', $siteId)
                ->where('status', 1)
                ->orderByDesc('weight')
                ->select(['name', 'id', 'has_hide', 'parent_id'])
                ->get();
            $data['dealer_level_list'] = $dealerLevel->where('parent_id', 0)->values()->toArray();
            // 是否有隐藏等级权限
            if ($sn->hasPermission(Constants::FunctionPermission_ENABLE_DEALER_HIDE_LEVEL)) {
                $data['dealer_hide_level_list'] = $dealerLevel->where('parent_id', '>', 0)
                    ->values()->toArray();
            }
        }
        // 标签组
        $labels = MemberLabelModel::query()
            ->where('site_id', $siteId)
            ->whereIn('admin_id', [0, $adminId])
            ->select(['name', 'id', 'sort', 'parent_id', 'admin_id'])
            ->get();
        // 公共的标签
        $data['site_labels'] = $labels->where('parent_id', 0)->where('admin_id', 0)->sortByDesc('sort')->values()->toArray();
        foreach ($data['site_labels'] as &$item) {
            $sub = $labels->where('parent_id', $item['id'])->sortBy('sort')->values()->toArray();
            if ($sub) {
                $item['sub_levels'] = $sub;
            }
        }
        // 自定义标签
        $data['custom_labels'] = $labels->where('admin_id', $adminId)
            ->where('parent_id', '>', 0)
            ->sortBy('sort')->values()->toArray();
        return $data;
    }

    /**
     * 获取会员基础信息
     * @return mixed
     */
    public static function getMemberBaseInfo($memberId)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $model = MemberModel::query()->where('site_id', $siteId)->where('id', $memberId)->first();
        // 终端类型
        $model->terminal_type = CoreConstants::getTerminalTypeText($model->terminal_type);

        // 会员身份信息 账户情况
        $infoQuery = MemberModel::query()->where('tbl_member.site_id', $siteId)
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
        // 合并数据
        $data = $model->toArray();
        $data['agent_setting_level'] = $agentBaseConfig->level;
        $data['distribution_setting_level'] = $distributionConfig->level;
        $data['dealer_passed_at'] = $dealer->passed_at;
        $data['dealer_level_name'] = DealerLevelModel::query()->where('id', $model->dealer_level)->value('name');
        $data['dealer_hide_level_name'] = DealerLevelModel::query()->where('id', $model->dealer_hide_level)->value('name');
        $data['distribution_level_name'] = DistributionLevelModel::query()->where('id', $info->distributor_level)->value('name');
        $data['level_name'] = MemberLevelModel::query()->where('id', $data['level'])->value('name');
        $data['agent_level_name'] = Constants::getAgentLevelTextForFront($data['agent_level']);
        $data['label'] = (new MemberLabel())->getMemberRelationLabel($model->id, SiteAdmin::getLoginedAdminId());
        $data = array_merge($data, $info->toArray());
        return self::convertOutputMemberBaseData($data);
    }

    /**
     * 格式化会员基础数据
     * @param $data
     * @return mixed
     */
    public static function convertOutputMemberBaseData($data)
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

}