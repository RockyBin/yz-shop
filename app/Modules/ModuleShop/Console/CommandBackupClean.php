<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Facades\DB;
use YZ\Core\Site\SiteManage;
use YZ\Core\Site\Site;

class CommandBackupClean extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'BackupClean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command For Clean Expiry Sites Backup';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
		echo "Run command : ".static::class;
        \YZ\Core\Logger\Log::writeLog("command",static::class.' start');
        $this->clearbackup();
        \YZ\Core\Logger\Log::writeLog("command",static::class.' completed');
    }

    //清查过期的备份
    public function clearbackup(){
        $list = DB::table('tbl_backuplog')->get();
        $autoBackupDirs = array();
        $manualBackupDirs = array();
        $siteroot = base_path();
        $siteroot = str_replace("\\","/",$siteroot);
        foreach($list as $item){
            $backdir = Site::getSiteBackupDir($item->site_id,true) . '/' . date('Y-m-d',strtotime($item->created_at));
            $backdir = str_replace("\\","/",$backdir);
            $info = array('id' => $item->id,'time' => strtotime($item->created_at));
            if(intval($item->type) === \YZ\Core\Constants::SiteBackupType_Manual) $manualBackupDirs[$backdir] = $info;
            else $autoBackupDirs[$backdir] = $info;
        }
        //删除过期的备份
        foreach($autoBackupDirs as $key => $info) {
            if($info['time'] < strtotime("-3 months")){
                if(!$manualBackupDirs[$key]){
                    echo "delete expiry backup ".$info['id']." , dir ".$key."\r\n";
                    \Ipower\Common\Util::deletedir($key);
                    DB::table('tbl_backuplog')->where('id',$info['id'])->delete();
                    unset($autoBackupDirs[$key]);
                }
            }
        }
        //遍历备份目录，将数据库里没有记录的文件夹删除
        $backupdir = $siteroot . "/backup";
        $hd = opendir($backupdir);
        while($file = readdir($hd)){
            if($file == '.' || $file == '..') continue;
            $subdir = $backupdir.'/'.$file;
            if(is_dir($subdir)){
                $hd2 = opendir($subdir);
                while($file2 = readdir($hd2)){
                    if(preg_match('/\d{4}\-\d{2}\-\d{2}/',$file2)){
                        $chkdir = $backupdir.'/'.$file.'/'.$file2;
                        $chkdir = str_replace("\\","/",$chkdir);
                        if(!$manualBackupDirs[$chkdir] && !$autoBackupDirs[$chkdir]){
	                        if(strtotime($file2) < strtotime("-6 months")){ //在备份记录里找不到，并且日期是半年前，基本上认为是已经过期的网站
	                            echo "clear dir $chkdir \r\n";
	                            \Ipower\Common\Util::deletedir($chkdir);
                        	}
                        }
                    }
                }
                closedir($hd2);
            }
        }
		closedir($hd);
		
        //遍历备份目录，清除没有有效备份数据的垃圾目录
        $backupdir = $siteroot . "/backup";
        $hd = opendir($backupdir);
        while($file = readdir($hd)){
            if($file == '.' || $file == '..') continue;
            $subdir = $backupdir.'/'.$file;
            $foundbackup = false;
            if(is_dir($subdir)){
                $hd2 = opendir($subdir);
                while($file2 = readdir($hd2)){
                    if(preg_match('/\d{4}\-\d{2}\-\d{2}/',$file2)){
                        $foundbackup = true;
                        break;
                    }
                }
                closedir($hd2);
            }
            if(!$foundbackup && preg_match('/\d{8,}/',$file)){
	            echo "clear user dir $subdir \r\n";
                \Ipower\Common\Util::deletedir($subdir);
            }
        }
        closedir($hd);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        /*return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];*/
		return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        /*return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];*/
		return [];
    }
}
