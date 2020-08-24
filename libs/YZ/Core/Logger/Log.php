<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 9:19
 */

namespace YZ\Core\Logger;


use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Log
{
    public static function writeLog($logname,$logstr){
        $logdir = base_path().DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR;
        $logdir .= date("Ymd"). DIRECTORY_SEPARATOR;
        if(!is_dir($logdir)){
            \Ipower\Common\Util::mkdirex($logdir);
        }
        $logfile = $logdir.$logname.'.txt';
        $fd = fopen($logfile,'a+');
        fwrite($fd,date("Y-m-d H:i:s")."\t".$logstr."\r\n");
        fclose($fd);
        @chmod ($logfile , 0777);
    }
}