<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Agent;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use YZ\Core\Model\MemberModel;

/**
 * 代理团队成员
 * Class AgentMembersController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\Agent
 */
class AgentMembersController extends BaseController
{
    /**
     * 基础数据
     * @return array
     */
    public function index()
    {
        try {
            $agentor = new Agentor($this->memberId);
            if (!$agentor->isActive()) {
                return makeServiceResultFail("未申请代理");
            }
            $countData = $agentor->getCountData([
                'team' => true,
                'team_contain_self' => true,
            ], true);

            // 基础设置
            $baseSetting = AgentBaseSetting::getCurrentSiteSettingFormat();
            return makeApiResponseSuccess('ok', [
                'count_data' => $countData,
                'base_setting' => $baseSetting,
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 团队成员列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $member = new Member($memberId);
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            $agentor = new Agentor($memberId);
            if (!$agentor->isActive()) {
                return makeServiceResultFail("未申请代理");
            }
            $params = $request->toArray();
            $params['show_sub_member_num'] = true;
            $params['contain_self'] = true;
            $params['show_reward_provide'] = true;
            $data = $agentor->getAgentSubMemberList($params);
            $data['list'] = $data['list']->ToArray();
            // 如果是所有成员，把自身也加进去放在最前面
            if (intval($params['page']) == 1 && intval($params['agent_level']) == -1) {
                $memberQuery = MemberModel::query()
                    ->leftJoin('tbl_member_level', 'tbl_member.level', 'tbl_member_level.id')
                    ->leftJoin('tbl_distributor', 'tbl_member.id', 'tbl_distributor.member_id')
                    ->leftJoin('tbl_distribution_level', 'tbl_distribution_level.id', 'tbl_distributor.level')
                    ->where('tbl_member.site_id', $this->siteId)
                    ->where('tbl_member.id', $this->memberId);
                $memberQuery->addSelect('tbl_member_level.name as member_level_name', 'tbl_distribution_level.name as distribution_level_name','tbl_member.mobile');
                $nativeSqlBindings = [];
                $nativeSqlList = $agentor->getNativeSqlList([
                    'show_sub_member_num' => true,
                    'show_reward_provide' => true,
                ], $nativeSqlBindings, 'tbl_member.id');
                if (count($nativeSqlList) > 0) {
                    foreach ($nativeSqlList as $nativeSqlItem) {
                        $memberQuery->addSelect(DB::raw($nativeSqlItem));
                    }
                    if (count($nativeSqlBindings) > 0) {
                        $memberQuery->addBinding($nativeSqlBindings, 'select');
                    }
                }
                $memberExtraData = $memberQuery->first();
                array_unshift($data['list'], [
                    'nickname' => $member->getModel()->nickname,
                    'headurl' => $member->getModel()->headurl,
                    'mobile' => $member->getModel()->mobile,
                    'is_distributor' => $member->getModel()->is_distributor,
                    'agent_level' => $member->getModel()->agent_level,
                    'member_created_at' => $member->getModel()->created_at,
                    'agent_upgrade_at' => $agentor->getModel()->upgrade_at,
                    'member_level_name' => $memberExtraData ? $memberExtraData->member_level_name : null,
                    'distribution_level_name' => $memberExtraData ? $memberExtraData->distribution_level_name : null,
                    'sub_member_num' => $memberExtraData ? $memberExtraData->sub_member_num : 0,
                    'reward_provide' => $memberExtraData ? $memberExtraData->reward_provide : 0,
                ]);
            }
            // 处理数据
            foreach ($data['list'] as &$item) {
                if (array_key_exists('reward_provide', $item)) {
                    $item['reward_provide'] = moneyCent2Yuan($item['reward_provide']);
                }
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}