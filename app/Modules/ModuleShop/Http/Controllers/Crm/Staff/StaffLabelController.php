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
 * 客户标签Controller
 * Class MemberLevelController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class StaffLabelController extends BaseCrmController
{
    private $memberLabel;

    /**
     * 初始化
     * MemberConfigController constructor.
     */
    public function __construct()
    {
        $this->memberLabel = new \App\Modules\ModuleShop\Libs\Member\MemberLabel();
    }


    public function getList(Request $request)
    {
        try {
            $list = $this->memberLabel->getList($request->toArray(), $request->page, $request->page_size);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getInfo()
    {
        try {
            $data = $this->memberLabel->getInfo(['admin_id' => $this->adminId]);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    function editLabel(Request $request)
    {
        $params = $request->toArray();
        $params['admin_id'] = $this->adminId;
        $data = $this->memberLabel->edit($params);
        return makeApiResponseSuccess('ok', $data);
    }

    function deleteLabel(Request $request)
    {
        if (!$request->id) {
            return makeApiResponseFail('ok');
        }
        $data = $this->memberLabel->delete($request->id);
        return makeApiResponseSuccess('ok', $data);
    }

    function addCustomLabel(Request $request)
    {
        try {
            $param = $request->toArray();
            $param['admin_id'] = $this->adminId;
            $data = $this->memberLabel->addCustomLabel($param);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}