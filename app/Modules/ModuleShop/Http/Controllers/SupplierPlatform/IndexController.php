<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Model\VerifyFileModel;
use YZ\Core\Model\WxAppModel;
use YZ\Core\Site\Site;
use YZ\Core\License\SNUtil;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformAdmin;
use YZ\Core\Site\SslCert;

class IndexController extends BaseSupplierPlatformController
{
    /**
     * 后台首页
     * @return array
     */
    public function index()
    {
        try {

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getSiteInfo()
    {
        try {
            $supplierPlatformAdmin = SupplierPlatformAdmin::getLoginedSupplierPlatformAdmin();
            $site = Site::getCurrentSite();
            $sn = SNUtil::getSNInstanceBySite($site->getSiteId());
            $LicensePerm = $sn->getPermission(1);
            $returnData = [
                'siteComdataPath' => Site::getSiteComdataDir(),
                'supplierPlatformAdmin' => $supplierPlatformAdmin,
                'LicensePerm' => $LicensePerm,
            ];

            // 未登录的要跳转到登录页面
            if (!$supplierPlatformAdmin) {
                return makeServiceResult(403, '请先登录', $returnData);
            } else {
                return makeApiResponseSuccess('ok', $returnData);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 信息
     * @param Request $request
     * @return array
     */
    public function getWxappConfigInfo()
    {
        try {
            $siteId = Site::getCurrentSite()->getSiteId();
            $configData = WxAppModel::query()->where(['site_id' => getCurrentSiteId()])->first();
            if ($configData) {
                // 微信验证文件
                $verifyFile = VerifyFileModel::query()->where('site_id', $siteId)->where('type', \YZ\Core\Constants::VerifyFileType_WxApp_Verify)->first();
                if ($verifyFile) {
                    $configData->verify = $verifyFile->path;
                } elseif($configData) {
                    $configData->verify = '';
                }
            }

            // 域名列表
            $domainList = Site::getCurrentSite()->getUserDomain(true);

            // SSL证书情况
            $sslDomainList = [];
            $sslList = (new SslCert())->getList();
            foreach ($sslList as $item){
                $arr = preg_split("/[\s,]+/i",$item['domains']);
                foreach ($arr as $dom){
                    $sslDomainList[$dom] = 1;
                }
            }
            //过滤没有证书的域名
            $newDomainList = [];
            foreach ($domainList as $k => $item){
                $tmp = explode('.',$item->domain);
                array_shift($tmp);
                $wildcard = "*.".implode('.',$tmp);
                if ($sslDomainList[$item->domain] || $sslDomainList[$wildcard]){
                    $newDomainList[] = $item;
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'config' => $configData,
                'domain_list' => $newDomainList,
            ]);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
