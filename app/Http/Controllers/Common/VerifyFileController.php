<?php
namespace App\Http\Controllers\Common;

use YZ\Core\Common\ServerInfo;
use YZ\Core\Site\Site;
/**
 * 验证文件处理
 * Class VerifyFileController
 * @package App\Http\Controllers\Common
 */
class VerifyFileController
{
    public function index(){
        $filename = explode('?',ServerInfo::get('REQUEST_URI'))[0];
        $filename = substr($filename,1);
        $site = Site::getCurrentSite();
        $dir = Site::getSiteComdataDir($site->getSiteId(),true);
        $filepath = $dir.'/verify/'.$filename;
        if(file_exists($filepath)){
            $content = file_get_contents($filepath);
            $mime = "text/plain";
            if(preg_match('/\.(html|htm)$/i',$filename)) $mime = "text/html";
            return response($content)->header('Content-Type', $mime.';charset=UTF-8');
        }else{
            return response("File Not Found")->setStatusCode(404);
        }
    }
}