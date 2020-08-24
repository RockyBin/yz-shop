<?php

namespace App\Modules\ModuleShop\Http\Controllers\Crm\Staff;

use App\Modules\ModuleShop\Libs\Crm\StaffVisitLog;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberInfo;
use App\Modules\ModuleShop\Libs\Member\MemberLabel;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Crm\BaseCrmController;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;


/**
 * 客户记录Controller
 * Class MemberLevelController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class StaffVisitLogController extends BaseCrmController
{

    public function getInfo(Request $request)
    {
        if (!$request->id) {
            return makeApiResponseFail('请传输正确id');
        }
        $staffVisitLog = new StaffVisitLog($request->id);
        if (!$staffVisitLog->getModel()) {
            return makeApiResponseFail('无此拜访记录--id=' . $request->id . '=--site_id=' . Site::getCurrentSite()->getSiteId());
        }
        $staffVisitLogModel = $staffVisitLog->getModel();
        $member = (new MemberInfo($staffVisitLogModel->member_id))->getMemberBaseInfo();

        $memberData['nickname'] = $member['nickname'];
        $memberData['headurl'] = $member['headurl'];
        $memberData['name'] = $member['name'];
        $data = array_merge($staffVisitLogModel->toArray(), $memberData);
        return makeApiResponseSuccess('ok', $data);
    }

    public function add(Request $request)
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
            if (!StaffVisitLog::checkPerm($this->adminId, $params['member_id'])) {
                return makeServiceResult(501, '您没有该权限,\n请联系超级管理员');
            }
            $info = $request->toArray();
            $info['site_id'] = Site::getCurrentSite()->getSiteId();
            $info['admin_id'] = $this->adminId;
            StaffVisitLog::add($info);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }

    }

    public function edit(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('请传输正确id');
            }
            $staffVisitLog = new StaffVisitLog($request->id);
            $model = $staffVisitLog->getModel();
            if (!$model) {
                return makeApiResponseFail('无此拜访记录');
            }
            if (!StaffVisitLog::checkPerm($this->adminId, $model->member_id)) {
                return makeServiceResult(501, '您没有该权限,\n请联系超级管理员');
            }
            $staffVisitLog->edit(['content' => $request->input('content')]);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }


    public function getList(Request $request)
    {
        if (!$request->member_id) {
            return makeApiResponseFail('请传输正确的member_id');
        }
        $params = $request->toArray();
        $data = StaffVisitLog::getList($params);
        $member = (new MemberInfo($request->member_id))->getMemberBaseInfo();
        $data['member_info']['id'] = $member['id'];
        $data['member_info']['nickname'] = $member['nickname'];
        $data['member_info']['headurl'] = $member['headurl'];
        $data['member_info']['name'] = $member['name'];
        return makeApiResponseSuccess('ok', $data);
    }


    public function delete(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('请传输正确id');
            }
            $staffVisitLog = new StaffVisitLog($request->id);
            $model = $staffVisitLog->getModel();
            if (!$model) {
                return makeApiResponseFail('无此拜访记录');
            }
            if (!StaffVisitLog::checkPerm($this->adminId, $model->member_id)) {
                return makeServiceResult(501, '您没有该权限,\n请联系超级管理员');
            }
            $staffVisitLog->delete();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}