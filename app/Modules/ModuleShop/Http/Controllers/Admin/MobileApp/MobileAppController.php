<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\MobileApp;

use App\Modules\ModuleShop\Libs\Model\MobileAppModel;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Ipower\Common\Util;
use YZ\Core\Site\Site;

class MobileAppController extends BaseAdminController
{
    public function __construct()
    {

    }

    public function save(Request $request)
    {
        try {
            $info = $request->all();
            //保存新增或更改的模块
            $id = $info['id'];
            if ($id) {
                $model = MobileAppModel::where(['site_id' => getCurrentSiteId(), 'id' => $request->get('id')])->first();
            } else {
                $model = MobileAppModel::where(['site_id' => getCurrentSiteId(), 'device_type' => $request->get('device_type')])->first();
            }
            if (!$model) {
                $model = new MobileAppModel();
                $model->site_id = getCurrentSiteId();
                $model->created_at = date('Y-m-d H:i:s');
            }
            $model->updated_at = date('Y-m-d H:i:s');
            $info['url'] = getHttpProtocol() . "://" . $info['domain'] . '/shop/front/#/?device_type=' . $info['device_type'];
            $model->fill($info);
            $result = \Ipower\Common\Util::http(config('app.APP_PACK_API') , [
                'appName' => $info['name'],
                'appOutPutDir' => 'ShopApp_' . getCurrentSiteId(),
                'iconImgUrl' => getHttpProtocol() . "://" . getHttpHost() . $info['logo'],
                'startImgUrl' => getHttpProtocol() . "://" . getHttpHost() . $info['lunch_image'],
                'webUrl' => $info['url']
            ]);
            if (strpos($result, 'SUCCESS:') === 0) {
                $model->save();
                $appDownUrl = trim(substr($result, (strpos($result, ':') === false ? 0 : strpos($result, ':') + 1)));
                $saveDir = Site::getSiteComdataDir(0,true) . '/mobileapp/';
                if(strpos($appDownUrl,'.exe') !== false) $fileName = 'app_' . $model->id . '.exe';
                else $fileName = 'app_' . $model->id . '.apk';
                $SavePath = $saveDir . $fileName;
                //判断目录是否存在 不存在则创建
                if (!file_exists($saveDir)) {
                    Util::mkdirex($saveDir);
                }
                \Ipower\Common\Util::httpDownload($appDownUrl, $SavePath);
                return makeApiResponse(200, 'ok', $model->toArray());
            } else {
                return makeApiResponse(500, "生成App失败：" . $result);
            }

        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    public function getInfo(Request $request)
    {
        if ($request->get('id')) {
            $data = MobileAppModel::where(['site_id' => getCurrentSiteId(), 'id' => $request->get('id')])->first();
        } else {
            $data = MobileAppModel::where(['site_id' => getCurrentSiteId(), 'device_type' => $request->get('device_type')])->first();
        }
        if ($data) {
            if ($data) $data = $data->toArray();
            $data['domain'] = parse_url($data['url'])['host'];
            $data['download_url'] = getHttpProtocol() . "://" . getHttpHost() . Site::getSiteComdataDir() . '/mobileapp/app_' . $data['id'] . '.exe';
        }
        $data['domains'] = Site::getCurrentSite()->getUserDomain(true);
        return makeApiResponse(200, 'ok', $data);
    }
}