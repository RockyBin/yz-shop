<?php

namespace App\Http\Controllers\SysManage\Site;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\SysManage\BaseSysManageController;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\PayConfigModel;
use YZ\Core\Model\SiteModel;

class SiteController extends BaseSysManageController
{
    /**
     * 获取网站列表
     * @return array
     */
    public function getList()
    {
        $query = SiteModel::query();
        $keyword = trim(Request::get('keyword'));
        if ($keyword) {
            $query->where('site_id', 'like', '%' . $keyword . '%');
            $query->orWhere('domains', 'like', '%' . $keyword . '%');
            $query->orWhere('name', 'like', '%' . $keyword . '%');
        }
        $status = trim(Request::get('status'));
        if ($status !== '') {
            $query->where('status', $status);
        }
        $count = $query->count('site_id');
        $pageSize = Request::get('pageSize');
        if (!$pageSize) $pageSize = 50;
        $pageCount = ceil($count / $pageSize);
        $currentPage = Request::get('page');
        if (!$currentPage) $currentPage = 1;
        $offset = ($currentPage - 1) * $pageSize;
        //DB::enableQueryLog();
        $list = $query->limit($pageSize)->offset($offset)->orderBy('site_id', 'desc')->get()->toArray();
        //dd(DB::getQueryLog());
        foreach ($list as $key => $val) {
            $sn = \YZ\Core\License\SNUtil::getSNInstance($val['sn']);
            $list[$key]['version'] = $sn->getCurLicenseText();
            $list[$key]['addfunc'] = explode(',', $sn->addFunctions);
            $list[$key]['status_text'] = \YZ\Core\Constants::getSiteStatusText(intval($val['status']));
        }
        return makeApiResponse(200, 'ok', ['list' => $list, 'pageCount' => $pageCount, 'currentPage' => $currentPage, 'pageSize' => $pageSize, 'total' => $count]);
    }

    /**
     * 获取单个网站的信息
     * @return array
     */
    public function getSiteInfo()
    {
        try {
            $siteid = Request::get('siteid');
            $site = \YZ\Core\Site\SiteManage::getSiteInfo($siteid);
            if ($site['addfunc']) $site['addfunc'] = explode(',', $site['addfunc']);
            else $site['addfunc'] = [];
            $payConfig = PayConfigModel::where('site_id', $siteid)->first();
            $site['wxpay_service_mode'] = 0;
            if ($payConfig) $site['wxpay_service_mode'] = $payConfig->wxpay_service_mode;
            return makeApiResponse(200, 'ok', ['info' => $site]);
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 添加网站
     * @return array
     */
    public function addSite()
    {
        try {
            $name = Request::get('name');
            $module = Request::get('module');
            $domains = Request::get('domains');
            $expiry_at = Request::get('expiry_at');
            $status = Request::get('status');
            $version = Request::get('version');
            $addfunc = Request::get('addfunc');
            $username = Request::get('username');
            $password = Request::get('password');
            $fidprod = Request::get('fidprod');
            $wxpay_service_mode = Request::get('wxpay_service_mode');
            if (is_array($addfunc)) $addfunc = implode(',', $addfunc);
            // 商品sku数量
            $productSkuNum = Request::get('product_sku_num', []);
            $params = [];
            if ($productSkuNum && $productSkuNum['sku_name_num'] > 0 && $productSkuNum['sku_value_num'] > 0) {
                $params['product_sku_num'] = $productSkuNum;
            }
            $params['staff_num'] = Request::get('staff_num');
            $siteid = \YZ\Core\Site\SiteManage::addSite($name, $module, $domains, $expiry_at, $status, $version, $fidprod, $username, $password, $addfunc, $params);
            if ($wxpay_service_mode) {
                $payConfig = new PayConfigModel();
                $payConfig->site_id = $siteid;
                $payConfig->wxpay_service_mode = $wxpay_service_mode;
                $payConfig->save();
            }
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 修改网站
     * @return array
     */
    public function editSite()
    {
        try {
            $siteid = Request::get('siteid');
            $name = Request::get('name');
            $module = Request::get('module');
            $domains = Request::get('domains');
            $expiry_at = Request::get('expiry_at');
            $status = Request::get('status');
            $version = Request::get('version');
            $addfunc = Request::get('addfunc');
            $remark = Request::get('remark');
            $staff_num = Request::get('staff_num');
            $wxpay_service_mode = Request::get('wxpay_service_mode');
            if (is_array($addfunc)) $addfunc = implode(',', $addfunc);
            if (!$addfunc) $addfunc = "NONE"; //因为 SiteManage::editSite() 当 addFunc 传空时，会将原序列号里的附加功能解析出来，所以这里要将 $addfunc 设置为 "NONE"
            $fidprod = Request::get('fidprod');
            $info = [
                'name' => $name,
                'module' => $module,
                'domains' => $domains,
                'expiry_at' => $expiry_at,
                'status' => $status,
                'version' => $version,
                'fidprod' => $fidprod,
                'staff_num' => $staff_num,
                'remark' => $remark
            ];
            // 商品sku数量
            $productSkuNum = Request::get('product_sku_num', []);
            if ($productSkuNum && $productSkuNum['sku_name_num'] > 0 && $productSkuNum['sku_value_num'] > 0) {
                $info['product_sku_num'] = $productSkuNum;
            }
            \YZ\Core\Site\SiteManage::editSite($siteid, $info, ['addFunc' => $addfunc]);
            $payConfig = PayConfigModel::query()->where('site_id', $siteid)->first();
            if (!$payConfig) {
                $payConfig = new PayConfigModel();
                $payConfig->site_id = $siteid;
            }
            if ($payConfig->wxpay_service_mode != $wxpay_service_mode) {
                $payConfig->wxpay_service_mode = $wxpay_service_mode;
                $payConfig->save();
            }
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 删除网站
     * @return array
     */
    public function deleteSite()
    {
        try {
            $siteid = Request::get('siteid');
            \YZ\Core\Site\SiteManage::deleteSite($siteid);
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 清理网站数据
     */
    public function clearSite(){
        try {
            $siteId = Request::get('siteid');
            $type = 0;
            if($type == 0){ //清空运营数据，会员，粉丝，订单，账务，积分，优惠券等
                $tables = [
                    'tbl_after_sale'
                    ,'tbl_after_sale_item'
                    ,'tbl_agent'
                    ,'tbl_agent_parents'
                    ,'tbl_agent_performance'
                    ,'tbl_agent_performance_reward'
                    ,'tbl_agent_recommend_reward'
					,'tbl_area_agent'
					,'tbl_area_agent_apply'
					,'tbl_area_agent_apply_form_data'
					,'tbl_area_agent_performance'
                    ,'tbl_browse'
                    ,'tbl_cloudstock'
                    ,'tbl_cloudstock_purchase_order'
                    ,'tbl_cloudstock_purchase_order_history'
                    ,'tbl_cloudstock_purchase_order_item'
                    ,'tbl_cloudstock_shop_cart'
                    ,'tbl_cloudstock_sku'
                    ,'tbl_cloudstock_sku_log'
                    ,'tbl_cloudstock_sku_settle'
                    ,'tbl_cloudstock_take_delivery_order'
                    ,'tbl_cloudstock_take_delivery_order_item'
                    ,'tbl_cloudstock_take_delivery_shop_cart'
                    ,'tbl_count_client_map'
                    ,'tbl_count_visit_log'
                    ,'tbl_coupon_item'
                    ,'tbl_dealer'
                    ,'tbl_dealer_account'
                    ,'tbl_dealer_authcert_item'
                    ,'tbl_dealer_parents'
                    ,'tbl_dealer_performance_reward'
                    ,'tbl_dealer_recommend_reward'
                    ,'tbl_dealer_reward'
                    ,'tbl_dealer_sale_reward'
                    ,'tbl_distributor'
                    ,'tbl_finance'
                    ,'tbl_group_buying'
                    ,'tbl_live_chat'
                    ,'tbl_live_viewer'
                    ,'tbl_logistics'
                    ,'tbl_member'
                    ,'tbl_member_address'
                    ,'tbl_member_auth'
                    ,'tbl_member_parents'
                    ,'tbl_member_relation_label'
                    ,'tbl_member_statistics'
                    ,'tbl_member_withdraw_account'
                    ,'tbl_onlinepay_log'
                    ,'tbl_order'
                    ,'tbl_order_item'
                    ,'tbl_order_item_discount'
                    ,'tbl_order_members_history'
                    ,'tbl_point'
                    ,'tbl_shopping_cart'
                    ,'tbl_small_shop'
                    ,'tbl_small_shop_product'
                    ,'tbl_statistics'
					,'tbl_supplier'
					,'tbl_supplier_settle'
					,'tbl_supplier_settle_item'
                    //,'tbl_unique_log'
                    ,'tbl_verify_log'
                    ,'tbl_wx_user'
                ];
                foreach ($tables as $table){
                    $sql = 'delete from '.$table.' where site_id = '.$siteId;
                    BaseModel::runSql($sql);
                }
            }
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 登录指定网站
     *
     * @return void
     */
    public function loginSite()
    {
        $siteid = Request::get('siteid');
        $key = config("app.API_PASSWORD");
        $secKey = MD5($key . time() . $siteid);
        $url = getHttpProtocol() . "://" . getHttpHost() . "/shop/admin/autologin?loginTime=" . time() . "&InitSiteID=" . $siteid . "&HashKey=" . $secKey;
        header("Location: " . $url);
    }
}