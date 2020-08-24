<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\WxWork;

use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Model\VerifyFileModel;
use YZ\Core\Model\WxWorkModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\VerifyFile;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\FileUpload\FileUpload;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Weixin\WxWork;

class ConfigController extends BaseAdminController
{
    /**
     * 信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $siteId = Site::getCurrentSite()->getSiteId();
            $configData = WxWorkModel::query()->where(['site_id' => getCurrentSiteId(),'mode' => 1])->first();
            if ($configData) {
                // 微信验证文件
                $verifyFile = VerifyFileModel::query()->where('site_id', $siteId)->where('type', Constants::VerifyFileType_WxWork_Verify)->first();
                if ($verifyFile) {
                    $configData->verify = $verifyFile->path;
                } elseif($configData) {
                    $configData->verify = '';
                }
            }

            // 域名列表
            $domainList = Site::getCurrentSite()->getUserDomain(true);

            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'config' => $configData,
                'domain_list' => $domainList,
            ]);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $model = WxWorkModel::query()->where(['site_id' => getCurrentSiteId(),'mode' => 1])->first();
          	$param = $request->input();
          	if(!$model){
              	$model = new WxWorkModel();
            	$param['site_id'] = getCurrentSiteId();
              	$param['mode'] = 1;
              	$param['status'] = 1;
            }
            // 验证文件
            if ($request->hasFile('verify_file')) {
                $verifyFileData = $_FILES['verify_file'];
                // 保存文件
                $verifyFile = new VerifyFile();
                $verifyFile->checkFile($verifyFileData);
                $verifyFile->deleteByType(Constants::VerifyFileType_WxWork_Verify);
                $verifyFile->edit(null, Constants::VerifyFileType_WxWork_Verify, null, $verifyFileData);
            }
            $model->fill($param);
            $model->save();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}