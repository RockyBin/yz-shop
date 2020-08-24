<?
//加密工具
include dirname(__FILE__).'/functions.php';
include dirname(__FILE__).'/obfuscator/yakpro-po-php7/yakpro-po-cgi.php';
function copy_need_encode_files($path,$recurse_level = 0,$copyAll = false)
{
	global $siteroot, $copydir;
	$path = realpath($path);
	$noNeedDir = strtolower($path) == strtolower(realpath($siteroot."/.git")) ||
		strtolower($path) == strtolower(realpath($siteroot."/app/Http/Controllers/SysManage")) || 
		strtolower($path) == strtolower(realpath($siteroot."/app/Modules/ModuleShop/Config")) || 
		strtolower($path) == strtolower(realpath($siteroot."/sn.config")) || 
		strtolower($path) == strtolower(realpath($siteroot."/public/72ad")) || 
		strtolower($path) == strtolower(realpath($siteroot."/public/codetool")) || 
		strtolower($path) == strtolower(realpath($siteroot."/public/comdata")) || 
		strtolower($path) == strtolower(realpath($siteroot."/public/tmpdata"));
	if($noNeedDir) return;

	if(is_dir($path))
	{
		if ($dir = opendir($path)) {
			while (($file = readdir($dir)) !== false) {
				if($file != '.' && $file != '..'){
					if(is_dir("$path/$file")){
						copy_need_encode_files("$path/$file",$recurse_level+1,$copyAll);
					} else {
						$file = realpath("$path/$file");
						if(substr($file,-4) == '.php' || $copyAll){
							if(!$copyAll){
								$filedata = file_get_contents($file);
								$fileContentCheck = strpos($filedata,"phpcodelock") !== false;
							}
							if($fileContentCheck || $copyAll){
								$destfile = str_replace($siteroot,'',$file);
								$filename = basename($file);
								$destdir = $copydir.substr($destfile,0,strrpos($destfile,DIRECTORY_SEPARATOR));
								mkdirex($destdir);
								$destfile = $destdir.DIRECTORY_SEPARATOR.$filename;
								copy($file,$destfile);
								$GLOBALS['files'][] = $destfile;
								//echo "$file,$destfile <br>";
							}
						}
					}
				}
			}
			closedir($dir);
		}
	}
}

function encode_files($dir,$output_dir){
	$hdir = opendir($dir);
	while($file = readdir($hdir)){
		if($file == '.' || $file == '..') continue;
		if(is_dir($dir.'/'.$file)){
			encode_files($dir.'/'.$file,$output_dir.'/'.$file);
		}else{
			if(substr($file,-4) != '.php') continue;
			$filefullname = $dir.'/'.$file;
			$filedata = file_get_contents($filefullname);
			if(strpos($filedata,"phpcodelock") === false) continue;
			$savedir = $output_dir;
			$savedir = preg_replace('@[\/]+@','/',$savedir);
			mkdirex($savedir);
			$filefullname_enc = $savedir.'/'.$file;
			$filefullname_enc = preg_replace('@[\/]+@','/',$filefullname_enc);
			encode_file($filefullname,$filefullname);
			//obfuscate2($filefullname,$filefullname);
			yz_encode_file($filefullname,$filefullname_enc);
			echo "$filefullname -> $filefullname_enc encrypted \r\n";
		}
	}
	closedir($hdir);
}

function obfuscate2($fileInut,$fileOutput){
	$sData = file_get_contents($fileInut);
	$sData = str_replace(array('<?php', '<?', '?>'), '', $sData); // Strip PHP open/close tags
	$sObfusationData = new Obfuscator($sData, 'Class/Code NAME');
	file_put_contents($fileOutput, '<?php ' . "\r\n" . $sObfusationData);
}

$siteroot = realpath(dirname(__FILE__).'/../../');
$copydir = realpath(dirname(__FILE__).'/../../../YZ-Shop-Enc');
$outputdir = realpath(dirname(__FILE__).'/../../../YZ-Shop-Enc');
$GLOBALS['files'] = array();
emptydir($copydir);
emptydir($outputdir);
if(!is_dir($copydir)) mkdir($copydir);
copy_need_encode_files($siteroot,0,true);
encode_files($copydir,$outputdir);
echo "done";
?>