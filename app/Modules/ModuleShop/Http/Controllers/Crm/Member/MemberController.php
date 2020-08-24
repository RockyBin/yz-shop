<?php

namespace App\Modules\ModuleShop\Http\Controllers\Crm\Member;

use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentLevel;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Crm\Member as CrmMember;
use App\Modules\ModuleShop\Libs\Member\MemberInfo;
use App\Modules\ModuleShop\Libs\Member\MemberLabel;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Crm\BaseCrmController;
use YZ\Core\Constants;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;


/**
 * 客户Controller
 * Class MemberLevelController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class MemberController extends BaseCrmController
{
    public function __construct()
    {
        $this->member = new Member(0, Site::getCurrentSite()->getSiteId(),false);
    }

    function add(Request $request)
    {
        // 如果只是绑定只需要输入电话号码就可以了，后期再更新
        if (!$request->input('bind')) {
            if (!$request->has('name')) {
                return makeApiResponseFail('请输入会员姓名');
            }
            $password = $request->input('password');
            if (!$password) {
                return makeApiResponseFail('请输入登录密码');
            } else {
                $pattern = "/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,16}$/";
                if (!preg_match($pattern, $password)) {
                    return makeApiResponseFail('密码格式不正确，请重新输入');
                }
            }
        }

        if (!$request->has('mobile')) {
            return makeApiResponseFail('请输入手机号码');
        } else {
            if (strlen($request->input('mobile')) != 11) {
                return makeApiResponseFail('手机号码格式不正确，请重新输入');
            }
        }

        $param = $request->toArray();
        if ($this->adminId) {
            $param['admin_id'] = $this->adminId;
        }
        $param['terminal_type'] = Constants::TerminalType_WxAppCrm;
        $result = $this->member->add($param);
        if ($result['code'] == 200) {
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $result['data']);
        } else {
            return makeApiResponse($result['code'], $result['msg'], $result['data']);
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    function getList(Request $request)
    {
        try {
            $params = $request->toArray();
            // 如果当前登录员工没有查看会员列表权限 只能列出自己名下的会员
            if (!SiteAdmin::hasPerm('member.view') || $params['admin_ids'] == -1) {
                $params['admin_ids'] = [$this->adminId];
            }
            $data = CrmMember::getList($params, $params['page'], $params['page_size']);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取客户列表高级筛选需要的数据
     * @return array
     */
    public function getMemberListSearchData()
    {
        try {
            $data = CrmMember::getMemberListSearchData($this->adminId);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    function getLabelList(Request $request)
    {
        $params['admin_id'] = $this->adminId;
        $params['member_id'] = $request->member_id ? $request->member_id : 0;
//        if (!$params['member_id']) {
//            return makeApiResponseFail('请传输正确的member_id');
//        }
        $data = (new MemberLabel())->getCrmMemberLabel($params);
        return makeApiResponseSuccess('ok', $data);
    }

    function getInfo(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $this->member = new Member($memberId, Site::getCurrentSite()->getSiteId());
            $memberModel = $this->member->getModel();
            // 有操作或者是所属员工，才可以查看
            if (SiteAdmin::hasPerm('member.detail.view') || $memberModel->admin_id == SiteAdmin::getLoginedAdminId()) {
                $data = CrmMember::getMemberBaseInfo($memberId);
            } else {
                return makeApiResponse(406, '您暂时没有此操作权限，请联系超级管理员！');
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    function edit(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请传输正确的member_id');
            }
            $this->member = new Member($request->member_id, Site::getCurrentSite()->getSiteId());
            $memberModel = $this->member->getModel();
            // 有操作或者是所属员工，才可以查看
            if (SiteAdmin::hasPerm('member.detail.view') || $memberModel->admin_id == SiteAdmin::getLoginedAdminId()) {
                if ($request->has("name")) {
                    $params['name'] = $request->name;
                }
                if ($request->has("nickname")) {
                    $params['nickname'] = $request->nickname;
                }

//            if ((!$request->level && !$request->dealer_level && !$request->dealer_hide_level) && $request->bind != 1) {
//                if (!$request->name) {
//                    return makeApiResponseFail('请填写正确的姓名');
//                }
//            }

                if ($request->has('dealer_level')) {
                    $params['dealer_level'] = $request->dealer_level;
                    $params['dealer_hide_level'] = 0; //修改了基础等级，隐藏等级要去掉
                }
                if ($request->has('dealer_hide_level')) {
                    $params['dealer_hide_level'] = $request->dealer_hide_level;
                    $parent = (new DealerLevel())->getInfo($params['dealer_hide_level']);
                    $params['dealer_level'] = $parent->parent_id;
                }
                if ($request->has('about')) $params['about'] = $request->about;
                if ($request->level) $params['level'] = $request->level;
                if ($request->has('label')) $params['label'] = $request->label;
                (new Member($request->member_id))->edit($params);
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(406, '您暂时没有此操作权限，请联系超级管理员！');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function editMemberLabel(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请输入会员ID');
            }
            $this->member = new Member($request->member_id, Site::getCurrentSite()->getSiteId());
            $memberModel = $this->member->getModel();
            if (SiteAdmin::hasPerm('member.detail.operate') || $memberModel->admin_id == SiteAdmin::getLoginedAdminId()) {
                (new MemberLabel())->editCrmMemberRelationLabel($request->member_id, $request->id);
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(406, '您暂时没有此操作权限，请联系超级管理员！');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    private function getMemberInfo($member_id)
    {
        $member = (new MemberInfo($member_id))->getMemberBaseInfo();
        $data['id'] = $member['id'];
        $data['nickname'] = $member['nickname'];
        $data['headurl'] = $member['headurl'];
        $data['name'] = $member['name'];
        $data['level_name'] = $member['level_name'];
        $data['dealer_level_name'] = $member['dealer_level_name'];
        $data['dealer_hide_level_name'] = $member['dealer_hide_level_name'];
        $data['distribution_level_name'] = $member['distribution_level_name'];
        $data['agent_level_name'] = $member['agent_level_name'];
        return $data;
    }

    function getMemberLevel(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请传输正确的member_id');
            }
            $params['member_id'] = $request->member_id;
            $member = new Member($params['member_id']);
            if (!($member->getModel())) {
                throw new \Exception('无此会员');
            }
            $params['status'] = $request->input('status', 1);
            $data['list'] = (new MemberLevel())->getList($params);
            $data['memberInfo'] = $this->getMemberInfo($request->member_id);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    function getDistributionLevel(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请传输正确的member_id');
            }
            $params['member_id'] = $request->member_id;
            $member = new Member($params['member_id']);
            if (!($member->getModel())) {
                throw new \Exception('无此会员');
            }
            if (!$member->isDistributor()) {
                throw new \Exception('此人不是分销商');
            }
            $params['status'] = $request->input('status', 1);
            $data['list'] = DistributionLevel::getList(false, $params);
            $data['memberInfo'] = $this->getMemberInfo($request->member_id);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    function getDealerLevel(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请传输正确的member_id');
            }
            $params['member_id'] = $request->member_id;
            $member = new Member($params['member_id']);
            $memberModel = $member->getModel();
            if (!$memberModel) {
                throw new \Exception('无此会员');
            }
            $dealerLevel = $memberModel->dealer_level;
            $dealerHideLevel = $memberModel->dealer_hide_level;
            if ($dealerLevel <= 0 && $dealerHideLevel <= 0) {
                throw new \Exception('此人不是经销商');
            }

            $params['get_hide_level'] = 1;
            $params['status'] = $request->input('status', 1);
            $data['list'] = DealerLevel::getLevelList($params);
            $data['memberInfo'] = $this->getMemberInfo($request->member_id);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    function getAgentLevel(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请传输正确的member_id');
            }
            $params['member_id'] = $request->member_id;
            $member = new Member($params['member_id']);
            $memberModel = $member->getModel();
            if (!$memberModel) {
                throw new \Exception('无此会员');
            }
            $agentLevel = $memberModel->agent_level;
            if ($agentLevel <= 0) {
                throw new \Exception('此会员不是代理商');
            }
            $params['status'] = $request->input('status', 1);
            $data['list'] = AgentLevel::getAgentList($params);
            if ($data) {
                $commision = (AgentBaseSetting::getCurrentSiteSetting())->commision;
                $commisionArr = $commision ? json_decode($commision, true) : [];
                foreach ($data['list'] as &$item) {
                    $item['commisions'] = $commisionArr[$item['id']];
                }
            }
            $data['memberInfo'] = $this->getMemberInfo($request->member_id);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改代理等级
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function setAgentLevel(Request $request)
    {
        try {
            $memberId = intval($request->get('member_id'));
            $agentLevel = intval($request->get('agent_level'));

            if (!$memberId || !$agentLevel) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $agentor = new Agentor($memberId);
            if (!$agentor->isActive()) {
                return makeApiResponseFail('代理未生效');
            }
            $result = $agentor->setAgentLevel($agentLevel);
            if ($result) {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
            } else {
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    /**
     * 编辑分销商等级和父级
     * @param Request $request
     * @return array
     */
    public function editDistributor(Request $request)
    {
        try {
            $id = $request->get('member_id');
            $distributor = new Distributor($id);
            if (!($distributor->getModel())) {
                throw new \Exception('无此分销商');
            }
            if ($request->has('level')) $info['level'] = $request->get('level');
            // 暂时不允许修改父级，后期有需要可修改
            // if ($request->has('parent_id')) $info['parent_id'] = $request->get('parent_id');
            $distributor->edit($info);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}