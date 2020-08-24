<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Wx;

use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Model\DomainModel;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Model\VerifyFileModel;
use YZ\Core\Model\WxMenuModel;
use YZ\Core\Model\WxNewsItemModel;
use YZ\Core\Model\WxNewsModel;
use YZ\Core\Model\WxReplyModel;
use YZ\Core\Model\WxTemplateModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\VerifyFile;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\FileUpload\FileUpload;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class WxConfigController extends BaseAdminController
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
            $config = new WxConfig();
            $configData = $config->getModel();
            // 微信验证文件
            $verifyFile = VerifyFileModel::query()->where('site_id', $siteId)->where('type', Constants::VerifyFileType_MP_Verify)->first();
            if ($verifyFile) {
                $configData->mp_verify = $verifyFile->path;
            } else {
                $configData->mp_verify = '';
            }
            // 域名列表
            $domainList = Site::getCurrentSite()->getUserDomain(true);

            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'config' => $configData,
                'domain_list' => $domainList,
                'url' => '/core/wechat/index'
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
            $site_id = Site::getCurrentSite()->getSiteId();
            $config = new WxConfig();
            $param = $request->input();
            // logo
            if ($request->hasFile('logo_file')) {
                $fileName = 'logo_' . $site_id . '_' . time();
                $filePath = Site::getSiteComdataDir('', true) . '/wx';
                $handle = new FileUpload($request->file('logo_file'), $filePath, $fileName);
                $handle->save();
                $param['logo'] = '/wx/' . $handle->getFullFileName();
            }
            // 二维码
            if ($request->hasFile('qrcode_file')) {
                $fileName = 'qrcode_' . $site_id . '_' . time();
                $filePath = Site::getSiteComdataDir('', true) . '/wx';
                $handle = new FileUpload($request->file('qrcode_file'), $filePath, $fileName);
                $handle->save();
                $param['qrcode'] = '/wx/' . $handle->getFullFileName();
            }
            // 验证文件
            if ($request->hasFile('mp_verify_file')) {
                $verifyFileData = $_FILES['mp_verify_file'];
                // 保存文件
                $verifyFile = new VerifyFile();
                $verifyFile->checkFile($verifyFileData);
                $verifyFile->deleteByType(Constants::VerifyFileType_MP_Verify);
                $verifyFile->edit(null, Constants::VerifyFileType_MP_Verify, null, $verifyFileData);
            }

            $config->save($param);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 解绑
     * @param Request $request
     * @return array
     */
    public function unBind(Request $request)
    {
        try {
            $siteId = Site::getCurrentSite()->getSiteId();
            // 清理菜单
            WxMenuModel::query()->where('site_id', $siteId)->delete();
            // 清理自定义回复
            WxReplyModel::query()->where('site_id', $siteId)->delete();
            // 清理素材
            WxNewsItemModel::query()->where('site_id', $siteId)->delete();
            WxNewsModel::query()->where('site_id', $siteId)->delete();
            // 把模板消息的template_id置空
            WxTemplateModel::query()->where('site_id', $siteId)->update([
                'template_id' => null,
            ]);
            // 清理粉丝
            WxUserModel::query()->where('site_id', $siteId)->where('platform',Constants::Fans_PlatformType_WxOfficialAccount)->delete();
            // 清理会员微信绑定
            MemberAuthModel::query()->where('site_id', $siteId)->where('type', Constants::MemberAuthType_WxOficialAccount)->delete();
            // 清楚微信公众号验证文件
            VerifyFileModel::query()->where('site_id', $siteId)->where('type', Constants::VerifyFileType_MP_Verify)->delete();
            // 重新加载配置
            $config = new WxConfig();
            $config->save([
                'name' => '',
                'wxid' => '',
                'appid' => '',
                'wx_no' => '',
                'qrcode' => '',
                'logo' => '',
                'appsecret' => '',
                'token' => '',
                'type' => '',
                'domain' => ''
            ]);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}