<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Member;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Member\MemberInfo;
use App\Modules\ModuleShop\Libs\Member\MemberLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Site\Site;
use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use YZ\Core\Common\DataCache;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Site\SiteAdminAllocation;

/**
 * 会员 Controller
 * Class MemberController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class MemberController extends BaseAdminController
{
    private $member;
    private $memberLevel;
    private $privateColumns = ['password', 'pay_password']; // 这些字段不返回给前端

    public function __construct()
    {
        $this->memberLevel = new MemberLevel();
        $this->member = new Member(0, Site::getCurrentSite()->getSiteId());
    }

    /**
     * 展示列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $param['count_extend'] = true; // 输出统计数据
            $data = $this->member->getList($param);
            if ($data && $data['list']) {
                $list = $data['list']->toArray();
                foreach ($list as &$item) {
                    $item = $this->convertOutputData($item);
                }
                unset($item);
                $data['list'] = $list;
            }

            // 会员等级列表
            $levelData = $this->memberLevel->getList();
            $data['member_level_list'] = $levelData['list'];

            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo(Request $request)
    {
        try {
            if ($request->id) {
                $this->member = new Member($request->id, Site::getCurrentSite()->getSiteId());
                $data = $this->member->getInfo(true);
                if ($data) {
                    $data = $this->convertOutputData($data->toArray());
                    return makeApiResponseSuccess('ok', $data);
                } else {
                    return makeApiResponseFail(trans("shop-admin.common.data_error"));
                }
            } else {
                return makeApiResponseFail(trans("shop-admin.common.data_error"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 创建新的会员
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            DataCache::setData(Constants::GlobalsKey_PointAtAdmin, true);
            $param = [];
            if ($request->has('mobile')) {
                $param['mobile'] = $request->mobile;
            }
            if ($request->has('password')) {
                $param['password'] = $request->password;
            }
            if ($request->has('nickname')) {
                $param['nickname'] = trim($request->get('nickname'));
            }
            if ($request->has('name')) {
                $param['name'] = trim($request->get('name'));
            }
            if ($request->get('admin_id_type') == 1) {
                $param['admin_id'] = (new SiteAdminAllocation())->allocate();
            } else if ($request->get('admin_id_type') == 2 && $request->has('admin_id')) {
                $param['admin_id'] = trim($request->get('admin_id'));
            } else if ($request->has('admin_id')) {
                $param['admin_id'] = trim($request->get('admin_id'));
            }
            $param['terminal_type'] = CodeConstants::TerminalType_Manual; // 手工录入
            $param['has_bind_invite'] = 1; //后台添加的，都认为是已经绑定过推荐人
            $result = $this->member->add($param);
            if ($result['code'] == 200) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $result['data']);
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑的会员信息
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function edit(Request $request)
    {
        try {
            DataCache::setData(Constants::GlobalsKey_PointAtAdmin, true);
            $this->member = new Member($request->id, Site::getCurrentSite()->getSiteId());
            $param = [];
            if ($request->has('mobile')) {
                $param['mobile'] = $request->mobile;
            }
            if ($request->has('level')) {
                $param['level'] = $request->level;
            }
            if ($request->has('sex')) {
                $param['sex'] = $request->sex;
            }
            if ($request->has('age')) {
                $param['age'] = $request->age;
            }
            if ($request->has('birthday')) {
                $param['birthday'] = $request->birthday;
            }
            if ($request->has('prov')) {
                $param['prov'] = $request->prov;
            }
            if ($request->has('city')) {
                $param['city'] = $request->city;
            }
            if ($request->has('area')) {
                $param['area'] = $request->area;
            }
            if ($request->has('nickname')) {
                $param['nickname'] = $request->nickname;
            }
            if ($request->has('name')) {
                $param['name'] = $request->name;
            }
            if ($request->has('password')) {
                $param['password'] = $request->password;
            }
            if ($request->has('about')) {
                $param['about'] = $request->about;
            }
            if ($request->has('pay_password')) {
                $param['pay_password'] = $request->pay_password;
            }
            if ($request->has('parent_id') && is_numeric($request->get('parent_id'))) {
                $param['parent_id'] = intval($request->get('parent_id'));
            }
            if ($request->has('admin_id') && is_numeric($request->get('admin_id'))) {
                $param['admin_id'] = intval($request->get('admin_id'));
            }
            if ($request->has('dealer_level')) {
                $param['dealer_level'] = $request->dealer_level;
                $param['dealer_hide_level'] = 0; //修改了基础等级，隐藏等级要去掉
            }
            if ($request->has('dealer_hide_level')) {
                $param['dealer_hide_level'] = $request->dealer_hide_level;
            }
            $result = $this->member->edit($param);
            if ($result['code'] == 200) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $result['data']);
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑会员基础信息（这个接口的权限要独立做）
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    /**
     * 编辑的会员信息
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function editBaseInfo(Request $request)
    {
        try {
            DataCache::setData(Constants::GlobalsKey_PointAtAdmin, true);
            $this->member = new Member($request->id, Site::getCurrentSite()->getSiteId());
            $memberModel = $this->member->getModel();
            $param = [];
            // 有操作或者是所属员工，才可以修改
            if (SiteAdmin::hasPerm('member.detail.operate') || $memberModel->admin_id == SiteAdmin::getLoginedAdminId()) {
                if ($request->has('sex')) {
                    $param['sex'] = $request->sex;
                }
                if ($request->has('age')) {
                    $param['age'] = $request->age;
                }
                if ($request->has('birthday')) {
                    $param['birthday'] = $request->birthday;
                }
                if ($request->has('prov')) {
                    $param['prov'] = $request->prov;
                }
                if ($request->has('city')) {
                    $param['city'] = $request->city;
                }
                if ($request->has('area')) {
                    $param['area'] = $request->area;
                }
                if ($request->has('nickname')) {
                    $param['nickname'] = $request->nickname;
                }
                if ($request->has('name')) {
                    $param['name'] = $request->name;
                }
                if ($request->has('about')) {
                    $param['about'] = $request->about;
                }
                $result = $this->member->edit($param);
                if ($result['code'] == 200) {
                    return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $result['data']);
                } else {
                    return makeApiResponse($result['code'], $result['msg'], $result['data']);
                }
            } else {
                return makeApiResponse(406, '您暂时没有此操作权限，请联系超级管理员！');
            }

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除会员信息
     * @param Request $request
     * @return array
     */
    public function status(Request $request)
    {
        try {
            if ($request->id) {
                $status = $request->status ? 1 : 0;
                $this->member = new Member($request->id, Site::getCurrentSite()->getSiteId());
                $result = $this->member->status($status);
                if ($result['code'] == 200) {
                    return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
                } else {
                    return makeApiResponse($result['code'], $result['msg'], $result['data']);
                }
            } else {
                return makeApiResponseFail(trans("shop-admin.common.data_error"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 导出数据
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function Export(Request $request)
    {
        try {
            $param = $request->all();
            $param['count_extend'] = true;
            $data = $this->member->getList($param);

            $exportHeadings = [
                '会员ID',
                '会员昵称',
                '会员姓名',
                '手机号码',
                '会员等级',
                '交易次数',
                '交易金额',
                '余额',
                '积分',
                '直推会员人数',
                '直推粉丝人数',
                '所属员工',
                '状态',
            ];
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $item = $this->convertOutputData($item);
                    $exportData[] = [
                        $item->id,
                        $item->nickname,
                        $item->name ? $item->name : '--',
                        "\t" . $item->mobile . "\t",
                        $item->level_name != '' ? $item->level_name : '暂无',
                        $item->trade_time ? $item->trade_time : '0',
                        $item->trade_money ?: '0',
                        $item->balance ?: '0',
                        $item->point > 0 ? $item->point : '0',
                        $item->directly_distributor_count ? $item->directly_distributor_count : '0',
                        $item->fans ? $item->fans : '0',
                        $item->admin_id ? ($item->admin_name . '/' . $item->position . '/' . $item->admin_mobile) : '--',
                        $item->status_text
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'HuiYuan-' . date("YmdHis") . '.xlsx', $exportHeadings);

            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getMemberInfo(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $data = (new MemberInfo($memberId))->getMemberBaseInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getDistributorInfo(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $data = (new MemberInfo($memberId))->getDistributorInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getDistributorSubList(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $data = (new MemberInfo($memberId))->getDistributorSubList($request->all());
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getAgentInfo(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $data = (new MemberInfo($memberId))->getAgentInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getAgentSubList(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $data = (new MemberInfo($memberId))->getAgentSubList($request->all());
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getDealerInfo(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $data = (new MemberInfo($memberId))->getDealerInfo();
            $levels = DealerLevel::getCachedLevels(['order_by' => ['weight', 'desc']], false);
            $data['levels'] = $levels;
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getDealerSubList(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $data = (new MemberInfo($memberId))->getDealerSubList($request->all());
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getMemberInfoBaseConfig()
    {
        try {
            $data = MemberInfo::getBaseInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */
    public function convertOutputData($item)
    {
        // 清楚私密数据
        foreach ($this->privateColumns as $privateColumn) {
            unset($item[$privateColumn]);
        }

        $keys = ['buy_money', 'buy_money_real', 'deal_money', 'deal_money_real', 'balance', 'balance_history'];
        foreach ($keys as $key) {
            if ($item[$key]) {
                $item[$key] = moneyCent2Yuan(intval($item[$key]));
            } else {
                $item[$key] = '0';
            }
        }

        return $item;
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
                (new MemberLabel())->editMemberRelationLabel($request->member_id, $request->id);
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(406, '您暂时没有此操作权限，请联系超级管理员！');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getLevelList(Request $request)
    {
        try {
            if (!$request->type) {
                return makeApiResponseFail('请输入正确的类型');
            }
            $data = Member::getLevelList($request->type);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
