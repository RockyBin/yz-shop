<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Http\Request;
use YZ\Core\Site\Site;
use YZ\Core\FileUpload\FileUpload;

/**
 * 通用配置设置
 * Class ConfigController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig
 */
class ConfigController extends BaseAdminController
{
    /**
     * 获取数据
     * @return array
     */
    public function getInfo()
    {
        try {
            $configModel = Site::getCurrentSite()->getConfig()->getModel();
            $sn = (new Site())->getSn();
            //标准商城不显示零售状态这个选择
            $showRetail = $sn->version == Constants::License_STANDARD ? false : true;
            $data = $configModel->toArray();
            $data['show_retail'] = $showRetail;
            //版权数据整理
            $data['copyright'] = Site::getCurrentSite()->getConfig()->getCopyRight();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存数据
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $param = $request->toArray();
            $config = Site::getCurrentSite()->getConfig();
            // 邀请码显示页面
            if (isset($param['show_code_pages'])) {
                $param['show_code_pages'] = $param['show_code_pages'] ? json_encode($param['show_code_pages']) : "{}";
            }
            // 版权设置
            if (isset($param['copyright_status'])) {
                // 版权logo
                if ($request->hasFile('copyright_logo')) {
                    $fileName = 'copyright_logo_' . time();
                    $filePath = Site::getSiteComdataDir('', true) . '/config';
                    $handle = new FileUpload($request->file('copyright_logo'), $filePath, $fileName);
                    $handle->save();
                    $param['copyright_logo'] = '/config/' . $handle->getFullFileName();
                }else{
                    if($param['copyright_logo'] == 'undefined') $param['copyright_logo'] = '';
                    $param['copyright_logo'] = str_replace(Site::getSiteComdataDir('', false),'',$param['copyright_logo']);
                }
                $param['copyright'] = [
                    'status' => intval($param['copyright_status']),
                    'style' => intval($param['copyright_style']),
                    'text' => $param['copyright_text'],
                    'logo' => $param['copyright_logo'],
                ];
                $param['copyright'] = json_encode($param['copyright']);
            }
            $config->save($param);
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}