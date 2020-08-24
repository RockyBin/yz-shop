<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Ipower\Common\Util;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Site\Site;

class CustomWordController extends BaseAdminController
{
    /**
     * 获取数据
     * @return array
     */
    public function getInfo()
    {
        try {
            $langList = [App::getLocale()]; // 语言种类数据，暂且只处理cn
            $groupList = ['shop-front'];
            $siteId = Site::getCurrentSite()->getSiteId();
            $dataList = [];
            foreach ($langList as $langItem) {
                foreach ($groupList as $group) {
                    $defaultLangFile = base_path() . '/resources/lang/' . $langItem . '/' . $group . '.json';
                    if (file_exists($defaultLangFile)) {
                        $langData = json_decode(file_get_contents($defaultLangFile), true);
                        if ($langData && is_array($langData[CoreConstants::Keyword_CustomWord])) {
                            $defaultData = $langData[CoreConstants::Keyword_CustomWord];
                            foreach ($defaultData as $key => $val) {
                                $dataList[$langItem][$group][$key] = [
                                    'value' => '',
                                    'default' => $val,
                                ];
                            }
                        }
                    }
                }
            }
            foreach ($dataList as $lang => $groupList) {
                foreach ($groupList as $group => $langData) {
                    $customLangFile = Site::getSiteComdataDir($siteId, true) . '/lang/' . $lang . '/' . $group . '.json';
                    if (file_exists($customLangFile)) {
                        $customData = json_decode(file_get_contents($customLangFile), true);
                        if ($customData && is_array($customData[CoreConstants::Keyword_CustomWord])) {
                            $customWordData = $customData[CoreConstants::Keyword_CustomWord];
                            foreach ($dataList[$lang][$group] as $key => $val) {
                                $customDataVal = $customWordData[$key];
                                $dataList[$lang][$group][$key]['value'] = $customDataVal ? $customDataVal : '';
                            }
                        }
                    }
                }
            }

            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'lang' => $dataList,
            ]);

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
            $data = [];
            $siteId = Site::getCurrentSite()->getSiteId();
            $langData = trans('shop-front.' . CoreConstants::Keyword_CustomWord);
            $currentLang = App::getLocale();
            $customLangPath = Site::getSiteComdataDir($siteId, true) . '/lang/' . $currentLang;
            $customLangFile = $customLangPath . '/shop-front.json';
            if (!file_exists($customLangPath)) {
                Util::mkdirex($customLangPath);
            }
            if (file_exists($customLangFile)) {
                $data = json_decode(file_get_contents($customLangFile), true);
            }
            $customData = [];
            foreach ($langData as $langDataKey => $langDataValue) {
                $val = $request->get($langDataKey);
                if (trim($val)) {
                    $customData[$langDataKey] = trim($val);
                }
            }
            $data[CoreConstants::Keyword_CustomWord] = $customData;
            if (file_exists($customLangFile)) {
                unlink($customLangFile);
            }
            file_put_contents($customLangFile, json_encode($data));
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}