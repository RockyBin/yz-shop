<?php
//phpcodelock
namespace YZ\Core\Common;
use YZ\Core\Locker\Locker;

class WxAppUtil
{
    /**
     * 获取小程序上传服务器，按队列来
     *
     * @return [string] $server
     */
    public static function getWxAppUploadServer(){
        $lock = null;
        try {
            $servers = explode(",",config("app.WXAPP_UPLOAD_SERVERS"));
            $lock = new Locker('getWxAppUploadServer',60);
            $checkfile = public_path().'/tmpdata/WxAppUploadServer.txt';
            if ($lock->Lock()) {
	            $lastserver = "";
	            if(file_exists($checkfile)){
	                $lastserver = trim(file_get_contents($checkfile));
	            }
	            if($lastserver === "") $lastserver = -1;
                //排除机制，不返回上次使用的服务器
                for($i = $lastserver + 1;$i < count($servers);$i++){
                    $server = $servers[$i].'wxAppUploader.php';
                    $opts = array(
                        'http'=>array(
                            'method'=>"GET",
                            'timeout'=>5,
                        )
                    );
                    $context = stream_context_create($opts);
                    //检测服务器是否存活
                    $html = file_get_contents($server, false, $context);
                    if(!$html) continue;
                    file_put_contents($checkfile,$i);
                    return ['code' => 200,'msg' => 'ok','server' => $server];
                }
                //如果上面排除的不能获取到服务器，默认返回第一个
                file_put_contents($checkfile,0);
                return ['code' => 200,'msg' => 'ok','server' => $servers[0].'wxAppUploader.php'];
            }else{
	            //如果上面排除的不能获取到服务器，默认返回第一个
                file_put_contents($checkfile,0);
                return ['code' => 200,'msg' => 'ok','server' => $servers[0].'wxAppUploader.php'];
            }
        }catch(\Exception $ex){
            return ['code' => 500,'msg' => $ex->getMessage()];
        }finally{
            if($lock) $lock->unlock();
        }
    }
}