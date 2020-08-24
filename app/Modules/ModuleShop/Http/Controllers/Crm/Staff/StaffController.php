<?php

namespace App\Modules\ModuleShop\Http\Controllers\Crm\Staff;

use App\Modules\ModuleShop\Libs\Crm\Auth;
use App\Modules\ModuleShop\Libs\Crm\Staff;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLabel;
use App\Modules\ModuleShop\Libs\SharePaper\Mobi\Paper;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Crm\BaseCrmController;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;


/**
 * 客户Controller
 * Class MemberLevelController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class StaffController extends BaseCrmController
{
    public function __construct()
    {
        parent::__construct();
        $this->member = new Member(0, Site::getCurrentSite()->getSiteId());
    }

    public function getInfo()
    {

        $staff = new Staff(SiteAdmin::getLoginedAdminId());
        $siteAdmin = $staff->getModel()->toArray();
        $shopConfig = (new ShopConfig())->getInfo();
        $siteAdmin['shop_name'] = $shopConfig['info']['name'];
        $siteAdmin['shop_logo'] = $shopConfig['info']['logo'];
        return makeApiResponseSuccess('ok', $siteAdmin);
    }

    public function edit(Request $request)
    {
        $siteAdmin = new SiteAdmin($this->adminId);
        $params = $request->toArray();
        $siteAdmin->edit($params);
        return makeApiResponseSuccess('ok');
    }

    public function getShopList()
    {
        $list = SiteAdmin::getShopList();
        return makeApiResponseSuccess('ok', $list);
    }

    public function getlist(Request $request)
    {
        $params = $request->toArray();
        $params['order_by'] = [['field' => 'member_count', 'sort_rule' => 'desc'], ['field' => 'created_at', 'sort_rule' => 'desc']];
        $data = Staff::getList($params);
        return makeApiResponseSuccess('ok', $data);
    }


    /**
     * 获取首页需要的数据
     * @param Request $request
     * @return array
     */
    function getHomePageData(Request $request)
    {
        try {
            $staff = new Staff(SiteAdmin::getLoginedAdminId());
            $data = $staff->getHomePageData($this->formatHomeRequest($request));
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 处理员工首页需要的参数
     * @param Request $request
     * @return array
     */
    private function formatHomeRequest(Request $request)
    {
        $memberCountAll = $request->input('member_count_all', 0);
        // 权限控制
        if ($memberCountAll == 1 && SiteAdmin::hasPerm('member.view')) {
            $memberCountAll = 1;
        } else {
            $memberCountAll = 0;
        }
        return ['member_count_all' => $memberCountAll];
    }

    /**
     * 获取会员首页 客户统计
     * @param Request $request
     * @return array
     */
    public function getHomePageMemberCount(Request $request)
    {
        try {
            $params = $this->formatHomeRequest($request);
            $staff = new Staff(SiteAdmin::getLoginedAdminId());
            return makeApiResponseSuccess('ok', $staff->getHomePageMemberCount($params['member_count_all']));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    public function bind(Request $request)
    {
        try {
            if (!$request->site_id) {
                return makeApiResponseFail('请传输正确的siteId');
            }
            if (!$request->mobile) {
                return makeApiResponseFail('请传输正确的mobile');
            }
            if (!$request->password) {
                return makeApiResponseFail('请传输正确的password');
            }
            $params = $request->toArray();
            $res = Auth::bind($params);
            if ($res) {
                return makeApiResponseSuccess('ok', $res);
            } else {
                return makeApiResponseFail('绑定失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function unbind(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('请传输正确的id');
            }
            if (!$request->site_id) {
                return makeApiResponseFail('请传输正确的site_id');
            }
            Auth::unbind($request->id, $request->site_id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}