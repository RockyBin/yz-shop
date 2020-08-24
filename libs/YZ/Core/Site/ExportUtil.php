<?php
//phpcodelock
namespace YZ\Core\Site;

use Illuminate\Support\Facades\DB;
use Ipower\Common\Util;

class ExportUtil {

	//当 dbpath = 'mysql:XXXXX' 时，表示用户要导出网站，这时应该生成生成SQL脚本，而不是导出为 sqlite ，导出为 sqlite 只用在备份方面
	// 'mysql:XXXXX' 中的 XXXX 是 SQL脚本 的保存目录
	//当是做网站备份时，注意要过滤掉 tbl_district 这两个数据比较多的表，这两个表是系统表，跟用户数据没有关系的
	//$onlyTables = array('table1' => 1,'table2' => 1); 以表名为键
	public static function exportData($dbpath, $UserID, $SiteIDs, $skipTables = array(),$onlyTables = null)
	{
		foreach($skipTables as $key => $val){
			$skipTables[$key] = strtolower($val);
		}
		if($onlyTables && is_array($onlyTables) && count($onlyTables) > 0){
			foreach($onlyTables as $key => $val){
				$onlyTables[strtolower($key)] = $val;
			}
		}
		$dbconn = DB::getPdo();
		$srcdb = new \Ipower\Db\DbLib($dbconn);
		$destdb = null;
		//try
		//{
			$tables = array();
			$arrtables = $srcdb->getTables();
			foreach ($arrtables as $table)
			{
				$tablename = strtolower($table);
				if (array_search($tablename,$skipTables) !== false) continue;
				if (preg_match('/^(tbl_)/',$tablename) || preg_match('/^(count_)/',$tablename) || preg_match('/^(wx_)/',$tablename))
				{
					if (strpos($tablename,"sysadmin") !== false) continue;
					if($onlyTables && is_array($onlyTables) && count($onlyTables) > 0){
						if(!array_key_exists($table,$onlyTables)) continue;
					}
					$tables[] = $table;
				}
			}

			$ismysql = false;
			if(preg_match('/^(mysql:)/i',$dbpath)){
				$destdb = $dbpath;
				$savepath = substr($dbpath,strpos($dbpath,':') + 1);
				\Ipower\Common\Util::emptydir($savepath);
				\Ipower\Common\Util::mkdirex($savepath);
				$ismysql = true;
			}else{
				$destconfig = array('type' => 'sqlite','database' => $dbpath);
				$destdb = new \Ipower\Db\DbLib($destconfig);
			}
			//创建表结构
			self::createTables($tables,$srcdb, $destdb);

			//导出数据
			foreach ($tables as $table)
			{
				if (strpos($table,"siteoplog") !== false) continue;
				if (strpos($table,"tbl_lock") !== false) continue;
				if (strpos($table,"adminlog") !== false) continue;
				if (strpos($table,"requests") !== false) continue;
				if (strpos($table,"phpsession") !== false) continue;
				$fields = $srcdb->getFields($table);
				$hasSiteIDCol = false;
				$hasWUserIDCol = false;
				$hasUserIDCol = false;
				foreach ($fields as $field)
				{
					if(strtolower($field['name']) == 'site_id') $hasSiteIDCol = true;
					if(strtolower($field['name']) == 'wuser_id') $hasWUserIDCol = true;
					if(strtolower($field['name']) == 'user_id') $hasUserIDCol = true;
				}

				if ($hasWUserIDCol)
				{
					$sql = "select * from " . $table . " where wuser_id=" . $UserID;
				}
				else if ($hasUserIDCol)
				{
					$sql = "select * from " . $table . " where user_id=" . $UserID;
				}
				else if ($hasSiteIDCol)
				{
					if (strtolower($table) == "tbl_zoneconf")
					{
						$sql = "select * from " . $table . " where site_id IN(" . $SiteIDs . ") or site_id = 0";
					}
					else
					{
						$sql = "select * from " . $table . " where site_id IN(" . $SiteIDs . ")";
					}
				}
				else
				{
					$sql = "select * from " . $table;
				}
				$rescount = $srcdb->query('select count(1) as count '.substr($sql,stripos($sql,'from')),'');
				$rowcount = $rescount[0]['count'];
				$pagesize = 500;
				$pagecount = ceil($rowcount/$pagesize);
				$pageno = 0;
				if($ismysql){
					$fd = fopen($savepath.DIRECTORY_SEPARATOR.'table_'.$table.'.php','w+');
					fwrite($fd,"<?\r\n\$table_{$table}_pk = '".$srcdb->getTablePk($table)."';\r\n");
					fwrite($fd,"\$table_$table = array(\r\n");
					
					while($pageno < $pagecount){
						$sqlpage = $sql." limit ".($pageno * $pagesize).",$pagesize"; 
						//echo $sqlpage."\r\n";
						$result = $srcdb->query($sqlpage,'',false);
						while ($row = $result->fetch(\PDO::FETCH_ASSOC))
						{
							fwrite($fd,var_export($row,true).",\r\n");
							unset($row);
						}
						$pageno++;
					}

					fwrite($fd,");\r\n?>");
					fclose($fd);
				}else{
					$destdb->execute("truncate table " . $table);
					
					while($pageno < $pagecount){
						$sqlpage = $sql." limit ".($pageno * $pagesize).",$pagesize"; 
						$result = $srcdb->query($sqlpage,'',false);
						while ($row = $result->fetch(\PDO::FETCH_ASSOC))
						{
							$destdb->insert($table,$row);
							unset($row);
						}
						$pageno++;
					}
				}
				unset($result);
			}
			unset($destdb);
		/*}catch(\Exception $ex){
			if($destdb != null) $destdb = null;
			if($srcdb != null) $srcdb = null;
			die("error:".$ex->getMessage());
		}*/
	}

	public static function getProcedureInfo(/*\Ipower\Db\DbLib*/ $srcdb){
		$result = $srcdb->query('SHOW PROCEDURE STATUS');
		$result2 = $srcdb->query('SHOW FUNCTION STATUS');
		$result = array_merge($result,$result2);
		foreach($result as $row){
			$dbname = strtolower($row['Db']);
			if($dbname != strtolower(config('database.connections.mysql.database'))) continue;
			$pname = $row['Name'];
			$ptype = $row['Type'];
			$sql = '';
			if(strtolower($ptype) == 'procedure') $sql = "show create procedure `$pname`";
			elseif(strtolower($ptype) == 'function') $sql = "show create function `$pname`";
			$res = $srcdb->query($sql);
			foreach($res as $item){
				if(strtolower($ptype) == 'procedure'){
					$proc = $item['Create Procedure'];
					$proc = 'create '.substr($proc,strpos($proc,'PROCEDURE'));
					$proc = "DELIMITER @@\r\n".$proc."@@\r\nDELIMITER;";
				}elseif(strtolower($ptype) == 'function'){
					$proc = $item['Create Function'];
					$proc = 'create '.substr($proc,strpos($proc,'FUNCTION'));
					$proc = "DELIMITER @@\r\n".$proc."@@\r\nDELIMITER;";
				}
				$out[] = $proc."\r\n";
			}
		}
		return $out;
	}

	public static function createTables($tables,\Ipower\Db\DbLib $srcdb, $destdb){
		$ismysql = false;
		if(preg_match('/^(mysql:)/i',$destdb)){
			$savepath = substr($destdb,strpos($destdb,':') + 1).DIRECTORY_SEPARATOR.'createsqls';
			\Ipower\Common\Util::mkdirex($savepath);
			$ismysql = true;
		}
		
		//导出存储过程
		$procs = self::getProcedureInfo($srcdb);
		$row = 1;
		if(is_array($procs)) {
            foreach ($procs as $item) {
                $fd = fopen($savepath . DIRECTORY_SEPARATOR . 'create_procedure_' . ($row++) . '.sql', 'w+');
                fwrite($fd, $item . "\r\n");
                fclose($fd);
            }
        }
		
		foreach ($tables as $table)
		{
			$result = \Ipower\Db\SqliteUtil::getCreateTableSqlFromMysql($srcdb->getDbConn(),$table);
			if($ismysql){	
			    //生成建表的脚本
			    $fd = fopen($savepath.DIRECTORY_SEPARATOR.'create_table_'.$table.'.sql','w+');
				fwrite($fd,'drop table if exists '.$table.";\r\n");
				fwrite($fd,$result['create'].";\r\n");
				fclose($fd);
				
				//生成索脚本
				$row = 1;
                if(is_array($result['index'])) {
                    foreach ($result['index'] as $index) {
                        $fd = fopen($savepath . DIRECTORY_SEPARATOR . 'create_index_' . $table . '_' . ($row++) . '.sql', 'w+');
                        fwrite($fd, $index . ";\r\n");
                        fclose($fd);
                    }
                }
				
				//生成触发器脚本
				$row = 1;
                if(is_array($result['trigger'])) {
                    foreach ($result['trigger'] as $trigger) {
                        if ($table != 'tbl_user') { //导出的网站不需要实时生成 tbl_domain 的记录，就不需要这几个触发器了
                            $fd = fopen($savepath . DIRECTORY_SEPARATOR . 'create_trigger_' . $table . '_' . ($row++) . '.sql', 'w+');
                            fwrite($fd, $trigger . ";\r\n");
                            fclose($fd);
                        }
                    }
                }
			}else{
				$destdb->execute('drop table '.$table);
				$destdb->execute($result['create']);
				foreach($result['index'] as $index){
					$destdb->execute($index);
				}
				foreach($result['trigger'] as $trigger){
					$destdb->execute($trigger);
				}
			}
		}
	}

    /**
     * 导出网站时，相应的文件是否在强制加密的目录里面
     * @param $file
     * @param $forceDirs
     * @return bool
     */
    public static function inForceEncodeDir($file,$forceDirs){
        if(!$forceDirs) return false;
        if(!is_array($forceDirs)) $forceDirs = explode(',',$forceDirs);
        $file = str_replace("\\","/",$file);
        foreach($forceDirs as $dir){
            $dir = str_replace("\\","/",$dir);
            if(stripos($file,$dir) !== false) return true;
        }
        return false;
    }

    /**
     * 导出网站时，加密相应文件
     * @param $dir
     * @param $output_dir
     */
    private static function encodeFiles($dir,$output_dir){
        $hdir = opendir($dir);
        while($file = readdir($hdir)){
            if($file == '.' || $file == '..') continue;
            if(is_dir($dir.'/'.$file)){
                static::encodeFiles($dir.'/'.$file,$output_dir.'/'.$file);
            }else{
                if(substr($file,-4) != '.php') continue;
                $fileFullName = $dir.'/'.$file;
                $fileData = file_get_contents($fileFullName);
                if(strpos($fileData,"phpcodelock") === false) continue;
                $saveDir = $output_dir;
                $saveDir = preg_replace('@[\/]+@','/',$saveDir);
                Util::mkdirex($saveDir);
                $fileFullNameEnc = $saveDir.'/'.$file;
                $fileFullNameEnc = preg_replace('@[\/]+@','/',$fileFullNameEnc);
                encode_file($fileFullName,$fileFullName); //混淆加密
                yz_encode_file($fileFullName,$fileFullNameEnc); //我们的PHP扩展加密
            }
        }
        closedir($hdir);
    }

	public static function exportSite($siteId){
        $pidFile = public_path().'/tmpdata/export/'.$siteId.'.pid';
        $canExport = !file_exists($pidFile) || (file_exists($pidFile) && filemtime($pidFile) < (time() - 1800));
        if(!$canExport){
            throw new \Exception('您在30分钟内曾经运行过导出任务，不能频繁操作，请过30分钟再试');
        }
        $fd = fopen($pidFile,"w+");
        fwrite($fd,date('Y-m-d H:i:s'));
        fclose($fd);

        require_once(public_path().'/codetool/obfuscator/yakpro-po-php7/yakpro-po-cgi.php');
        $siteRoot = base_path();
        $prefix = 'site_'.$siteId.'_'.substr(md5(mt_rand()),0,6);
        $copyDir = public_path().'/tmpdata/export/'.$prefix;

        //导出网站文件
        $excludes = [
            '.git',
            '.svn',
            '.idea',
            '.env',
            'sn.config',
            '/app/Http/Controllers/SysManage',
            $siteRoot.'/storage',
            '/public/72ad',
            '/public/codetool',
            '/public/comdata',
            '/public/tmpdata',
            '/public/tools'
        ];
        \Ipower\Common\Util::copyFolder($siteRoot, $copyDir, true, implode(',',$excludes));

        //加密
        self::encodeFiles($copyDir,$copyDir);

        //替换ENV文件
        $env = file_get_contents(base_path().'/.env');

        $env = preg_replace('/ALIPAY_PAY_SANDBOX=true/i','ALIPAY_PAY_SANDBOX=false',$env);
        $env = preg_replace('/ALIPAY_LOGIN_SANDBOX=true/i','ALIPAY_LOGIN_SANDBOX=false',$env);

        $env = preg_replace('/DB_DATABASE\s*=\s*[^\r\n]+/i','DB_DATABASE=$DB_DATABASE$',$env);
        $env = preg_replace('/DB_HOST_READ\s*=\s*[^\r\n]+/i','DB_HOST_READ=$DB_HOST_READ$',$env);
        $env = preg_replace('/DB_PORT_READ\s*=\s*[^\r\n]+/i','DB_PORT_READ=$DB_PORT_READ$',$env);
        $env = preg_replace('/DB_USERNAME_READ\s*=\s*[^\r\n]+/i','DB_USERNAME_READ=$DB_USERNAME_READ$',$env);
        $env = preg_replace('/DB_PASSWORD_READ\s*=\s*[^\r\n]+/i','DB_PASSWORD_READ=$DB_PASSWORD_READ$',$env);
        $env = preg_replace('/DB_HOST_WRITE\s*=\s*[^\r\n]+/i','DB_HOST_WRITE=$DB_HOST_WRITE$',$env);
        $env = preg_replace('/DB_PORT_WRITE\s*=\s*[^\r\n]+/i','DB_PORT_WRITE=$DB_PORT_WRITE$',$env);
        $env = preg_replace('/DB_USERNAME_WRITE\s*=\s*[^\r\n]+/i','DB_USERNAME_WRITE=$DB_USERNAME_WRITE$',$env);
        $env = preg_replace('/DB_PASSWORD_WRITE\s*=\s*[^\r\n]+/i','DB_PASSWORD_WRITE=$DB_PASSWORD_WRITE$',$env);

        $env = preg_replace('/REDIS_HOST\s*=\s*[^\r\n]+/i','REDIS_HOST=$REDIS_HOST$',$env);
        $env = preg_replace('/REDIS_PASSWORD\s*=\s*[^\r\n]+/i','REDIS_PASSWORD=$REDIS_PASSWORD$',$env);
        $env = preg_replace('/REDIS_PORT\s*=\s*[^\r\n]+/i','REDIS_PORT=$REDIS_PORT$',$env);

        $env = preg_replace('/CRM_APP_ID\s*=\s*[^\r\n]+/i','CRM_APP_ID=XXXX',$env);
        $env = preg_replace('/CRM_APP_SECRET\s*=\s*[^\r\n]+/i','CRM_APP_SECRET=XXXX',$env);

        $env = preg_replace('/\bAPI_USER\s*=\s*[^\r\n]+/i','API_USER=XXXX',$env);
        $env = preg_replace('/\bAPI_PASSWORD\s*=\s*[^\r\n]+/i','API_PASSWORD=XXXX',$env);

        file_put_contents($copyDir.'/.env',$env);

        //生成一些系统目录
        Util::mkdirex($copyDir.'/storage/framework/cache');
        Util::mkdirex($copyDir.'/storage/framework/sessions');
        Util::mkdirex($copyDir.'/storage/framework/views');
        Util::mkdirex($copyDir.'/storage/framework/testing');
        Util::mkdirex($copyDir.'/storage/logs');

        //导出数据库信息
        $skipTables = array();
        $skipTables[] = "tbl_count_visit_log";
        $skipTables[] = "tbl_lock";
        $skipTables[] = "tbl_sysadmin";
        $skipTables[] = "tbl_sysadmin_log";
        $skipTables[] = "tbl_unique_log";
        $skipTables[] = "tbl_count_client_map";
        $skipTables[] = "tbl_onlinepay_log";
        $dstDb = $copyDir . DIRECTORY_SEPARATOR . 'app_data';
        static::exportData("mysql:" . $dstDb, 0, $siteId, $skipTables);

        //删除以前的旧压缩文件
        $hdir = opendir(public_path().'/tmpdata/export');
        while($file = readdir($hdir)){
            if(preg_match('/^'.$prefix.'/i',$file)) @unlink(public_path().'/tmpdata/export/'.$file);
        }
        closedir($hdir);

        //压缩打包
        $zipFile = public_path().'/tmpdata/export/'.$prefix.'.zip';
        $zip = new \Ipower\Common\Zip();
        $zip->zipDir($copyDir, $zipFile);
        @unlink($pidFile);
        Util::deletedir($copyDir);
        return $prefix.'.zip';
    }
}