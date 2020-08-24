<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\App;
use YZ\Core\Constants;
use YZ\Core\Site\Site;

/**
 * 此 controller 用来将语言生成json给前端使用
 * Class LangJsonController
 * @package App\Http\Controllers
 */
class LangJsonController extends BaseController
{
    public function index()
    {
        $currentLang = App::getLocale();
        $defaultLangPath = base_path() . '/resources/lang/' . $currentLang;
        $dir = opendir($defaultLangPath);
        $langs = [];
        while ($file = readdir($dir)) {
            if ($file != '.' && $file != '..' && preg_match('/\.json$/i', $file)) {
                $group = substr($file, 0, -5);
                $langs[$group] = json_decode(file_get_contents($defaultLangPath . '/' . $file), true);
            }
        }

        $siteId = Site::getCurrentSite()->getSiteId();
        $siteLangPath = Site::getSiteComdataDir($siteId, true) . '/lang/' . $currentLang;
        if (file_exists($siteLangPath)) {
            $dir = opendir($siteLangPath);
            $siteLangs = [];
            while ($file = readdir($dir)) {
                if ($file != '.' && $file != '..' && preg_match('/\.json$/i', $file)) {
                    $group = substr($file, 0, -5);
                    $siteLangs[$group] = json_decode(file_get_contents($siteLangPath . '/' . $file), true);
                }
            }
            myArrayMerge($langs, $siteLangs);
        }

        // 特殊处理
        foreach ($langs as $group => &$lang) {
            if ($lang[Constants::Keyword_CustomWord]) {
                pregReplaceForLang($lang, $lang[Constants::Keyword_CustomWord]);
            }
        }

        return $langs;
    }
}
