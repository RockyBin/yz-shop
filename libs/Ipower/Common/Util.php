<?
namespace Ipower\Common;

/**
 * 常用工具类
 * Class Util
 * @package Ipower\Common
 */
class Util
{
    /**
     * 此方法将xml的数据转为数组的形式，只支持最简单的XML，不支持xml的属性 SimpleXMLElement 只支持 utf-8 编码
     * @param $xmlobj
     * @param bool $flag
     * @return array
     */
    public static function xmlToArray($xmlobj,$flag = false){
        $arr = array();
        if(is_string($xmlobj)) $xmlobj = new \SimpleXMLElement($xmlobj);
        foreach($xmlobj as $name => $val){
            if(count($val) == 0){ //表示没有子节点
                $arr[$name] = strval($val);
            }else{
                if($flag) $arr[$name][] = self::xmlToArray($val,$flag);
                else $arr[$name] = self::xmlToArray($val,$flag);
            }
        }
        return $arr;
    }

    /**
     * 数组转为xml
     * @param $arr
     * @param int $deep
     * @return string
     */
    public static function arrToXml($arr,$deep = 0){
        $str = "";
        foreach($arr as $key => $val){
            $str .= str_repeat("\t",$deep)."<$key>";
            if(is_array($val)) $str .= self::arrToXml($val,$deep+1);
            else $str .= (is_string($val) ? "<![CDATA[$val]]>" : $val);
            $str .= "</$key>";
        }
        if($deep == 0) return "<?xml version='1.0' encoding='utf-8'?>$str";
        else return $str;
    }

    /**
     * 将 \uXXX 这种unicode形式的字符转为可读的文字
     * @param $unicode_str
     * @return mixed
     */
    public static function unicodeToUtf8($unicode_str) {
        $result = $unicode_str;
        preg_match_all("@(\\\\u([0-9a-f]{4}))@",$unicode_str,$arr,PREG_SET_ORDER) ;
        foreach($arr as $val)
        { 
            $utf8_str = $val[2];
            $code = intval(hexdec($utf8_str));
            //这里注意转换出来的code一定得是整形，这样才会正确的按位操作
            $ord_1 = decbin(0xe0 | ($code >> 12));
            $ord_2 = decbin(0x80 | (($code >> 6) & 0x3f));
            $ord_3 = decbin(0x80 | ($code & 0x3f));
            $utf8_str = chr(bindec($ord_1)) . chr(bindec($ord_2)) . chr(bindec($ord_3));
            $result = str_replace($val[0],$utf8_str,$result);
        }
        return $result;
    }

    /**
     * 将可读的文字转为 \uXXX 这种形式
     * @param $utf8_str
     * @return mixed
     */
    public static function utf8ToUnicode($utf8_str) {
        $result = $utf8_str;
        preg_match_all("/([\x{4e00}-\x{9fa5}])/u",$utf8_str,$arr);
        $arr = $arr[0];
        foreach($arr as $char){
          $unicode = 0;
          $unicode = (ord($char[0]) & 0x1F) << 12;
          $unicode |= (ord($char[1]) & 0x3F) << 6;
          $unicode |= (ord($char[2]) & 0x3F);
          $hex = "\\u".dechex($unicode);
          $result = str_replace($char,$hex,$result);
        }
        return $result;
    }

    /**
     * 将字符串按指定的字数符进行分割
     * @param $str
     * @param $charCount
     * @return array
     */
    function splitWithCharNum($str,$charCount){
        preg_match_all("/([\x{4e00}-\x{9fa5}\S\s])/u",$str,$arr);
        $arr = $arr[0];

        $strTmp = "";
        $length = count($arr);
        $arrout = array();
        $count = 0;
        for($i = 0;$i < $length;$i++)
        {
            $char = $arr[$i];
            $strTmp .= $char;
            $count++;
            if($count == $charCount)
            {
                $arrout[] = $strTmp;
                $strTmp = "";
                $count = 0;
            }
            if($strTmp != "" && $i == $length - 1) //** 已经到了结尾
            {
                $arrout[] = $strTmp;
            }
        }
        return $arrout;
    }

    /**
    * 对二维数组进行排除
    * 
    * @param mixed $data  要排序的数组
    * @param mixed $sortCriteria   排序的方式   
    * $sortCriteria = 
          array('field1' => array(SORT_DESC, SORT_NUMERIC), 
               'field3' => array(SORT_DESC, SORT_NUMERIC)
          );
    * @param mixed $caseInSensitive     是否大小写敏感
    */
    public static function multiSort($data, $sortCriteria, $caseInSensitive = true)
    {
        if( !is_array($data) || !is_array($sortCriteria)) return false;       
        $args = array(); 
        $i = 0;
        foreach($sortCriteria as $sortColumn => $sortAttributes)  
        {
            $colList = array(); 
            foreach ($data as $key => $row)
            { 
                $convertToLower = $caseInSensitive && (in_array(SORT_STRING, $sortAttributes) || in_array(SORT_REGULAR, $sortAttributes)); 
                $rowData = $convertToLower ? strtolower($row[$sortColumn]) : $row[$sortColumn]; 
                $colLists[$sortColumn][$key] = $rowData;
            }
            $args[] = &$colLists[$sortColumn];

            foreach($sortAttributes as $sortAttribute)
            {      
                $tmp[$i] = $sortAttribute;
                $args[] = &$tmp[$i];
                $i++;      
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return end($args);
    }

    /**
     * 清空目录
     * @param $path
     */
    public static function emptyDir($path)
    {
        if(is_dir($path))
        {
            if ($dir = opendir($path)) {
                while (($file = readdir($dir)) !== false) {
                    if($file != '.' && $file != '..'){
                        if(is_dir("$path/$file")){
                            self::emptyDir("$path/$file");
                            rmdir("$path/$file");
                        } else {
                            unlink("$path/$file");
                        }
                    }
                }
                closedir($dir);
            }
        }
    }

    /**
     * 列出目录下的所有文件
     * @param $path
     * @param array $files
     * @return array
     */
	public static function listDir($path,$files = array()){
		if(is_dir($path))
        {
            if ($dir = opendir($path)) {
                while (($file = readdir($dir)) !== false) {
                    if($file != '.' && $file != '..'){
                        if(is_dir($path.DIRECTORY_SEPARATOR.$file)){
                            $files = array_merge($files,self::listDir($path.DIRECTORY_SEPARATOR.$file));
                        } else {
                            $files[] = $path.DIRECTORY_SEPARATOR.$file;
                        }
                    }
                }
                closedir($dir);
            }
        }
		return $files;
	}

    /**
     * 删除目录
     * @param $path
     */
    public static function deleteDir($path){
        if(file_exists($path)){
           self::emptyDir($path);
           rmdir($path);
        }
    }

    /**
     * 分路径自动建立多级目录
     * @param $path
     * @return bool
     */
    public static function mkdirex($path){
        $path = strtr($path,'/',DIRECTORY_SEPARATOR);
        $path = strtr($path,"\\",DIRECTORY_SEPARATOR);
        $arr = explode(DIRECTORY_SEPARATOR,$path);
        for($i = 0;$i < count($arr);$i++){
            $arrtmp = array_slice($arr, 0, $i + 1);
            $dir = implode(DIRECTORY_SEPARATOR,$arrtmp);
            if(!$dir) continue;
            if(substr($dir,-1) == DIRECTORY_SEPARATOR) $dir = substr($dir,0,strlen($dir) - 1);
            if(!is_dir($dir)){
                mkdir($dir,0777,true);
                if(strtoupper(substr(PHP_OS,0,3)) !== 'WIN') @exec("chmod -R 777 $dir");
            }
        }
        return true;
    }

    /**
     * 合并两个文件夹
     * @param $src 源目录
     * @param $dest 目标目录
     * @param bool $overwrite 是否覆盖
     * @param string $excludeList 忽略列表
     */
	public static function mergeFolder($src, $dest, $overwrite = false,$excludeList = "")
	{
		$src = str_replace('/',DIRECTORY_SEPARATOR,$src);
		$dest = str_replace('/',DIRECTORY_SEPARATOR,$dest);
		if (self::isExclude($src, $excludeList)) return;
		if (file_exists($src))
		{
			$exists = false;
			self::mkdirex($dest);
			if(substr($src,-1) !== DIRECTORY_SEPARATOR) $src .= DIRECTORY_SEPARATOR;
			if(substr($dest,-1) !== DIRECTORY_SEPARATOR) $dest .= DIRECTORY_SEPARATOR;
			$dir = opendir($src);
			while($file = readdir($dir)){
				if($file == '.' || $file == '..') continue;
				$filepath = $src.$file;
				if(self::isExclude($filepath, $excludeList)) continue;
				if(is_file($dest.$file) && file_exists($dest.$file) && !$overwrite) continue;
				if(is_file($filepath)) copy($filepath,$dest.$file);
				else self::mergeFolder($filepath, $dest.$file, $overwrite, $excludeList);
			}
			closedir($dir);
		}
	}

    /**
     * 复制文件夹
     * @param $src 源目录
     * @param $dest 目标目录
     * @param bool $overwrite 是否覆盖
     * @param string $excludeList 忽略列表
     */
	public static function copyFolder($src, $dest, $overwrite = true,$excludeList = "")
	{
		$src = str_replace('/',DIRECTORY_SEPARATOR,$src);
		$dest = str_replace('/',DIRECTORY_SEPARATOR,$dest);
		if (self::isExclude($src, $excludeList)) return;
		if (file_exists($src))
		{
			$exists = false;
			if (file_exists($dest)) $exists = true;
			if ($exists && $overwrite) self::emptydir($dest);
			self::mkdirex($dest);
			if(substr($src,-1) !== DIRECTORY_SEPARATOR) $src .= DIRECTORY_SEPARATOR;
			if(substr($dest,-1) !== DIRECTORY_SEPARATOR) $dest .= DIRECTORY_SEPARATOR;
			$dir = opendir($src);
			while($file = readdir($dir)){
				if($file == '.' || $file == '..') continue;
				$filepath = $src.$file;
				if(self::isExclude($filepath, $excludeList)) continue;
				if(is_file($filepath)) copy($filepath,$dest.$file);
				else self::copyFolder($filepath, $dest.$file, $overwrite, $excludeList);
			}
			closedir($dir);
		}
    }
    
    /**
     * 复制文件
     *
     * @param [string] $src 源文件
     * @param [string] $dest 目标文件
     * @param boolean $overwrite 是否覆盖
     * @return boolean true = 复制成功，否则为失败
     */
    public static function copyFile($src, $dest, $overwrite = true){
        if(file_exists($dest) && !$overwrite) return false;
        if(!file_exists($src)) return false;
        $pathInfo = pathinfo($dest);
        if(!file_exists($pathInfo['dirname'])) static::mkdirex($pathInfo['dirname']);
        return copy($src, $dest);
    }

	//这个方法是 CopyFolder 内部调用的专用方法，用来判断哪些文件或文件夹不用处理
	private static function isExclude($fileOrDir, $excludeList)
	{
		if (!$excludeList) return false;
		$fileOrDir = strtolower(str_replace('/',DIRECTORY_SEPARATOR,$fileOrDir));
		$excludeList = strtolower(str_replace('/',DIRECTORY_SEPARATOR,$excludeList));
		$arr = explode(',',$excludeList);
		foreach ($arr as $item)
		{
			if (strpos($fileOrDir,trim($item)) !== false) return true;
		}
		return false;
	}

	/**
	 * 发送HTTP请求方法
	 * @param  string $url    请求URL
	 * @param  array  $params 请求参数
	 * @param  string $method 请求方法GET/POST
	 * @return array  $data   响应数据
	 */
	public static function http($url, $params, $method = 'GET', $header = array(), $multi = false){
	    $opts = array(
	        CURLOPT_TIMEOUT => 60,
	        CURLOPT_RETURNTRANSFER => 1,
	        CURLOPT_SSL_VERIFYPEER => false,
	        CURLOPT_SSL_VERIFYHOST => false,
	        CURLOPT_HTTPHEADER     => $header
	    );
	    /* 根据请求类型设置特定参数 */
	    switch(strtoupper($method)){
	        case 'GET':
	            $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
	            break;
	        case 'POST':
	            //判断是否传输文件
	            $params = $multi ? $params : http_build_query($params);
	            $opts[CURLOPT_URL] = $url;
	            $opts[CURLOPT_POST] = 1;
	            $opts[CURLOPT_POSTFIELDS] = $params;
	            break;
	        default:
	            throw new \Exception('request method not support');
	    }
	    /* 初始化并执行curl请求 */
	    $ch = curl_init();
	    curl_setopt_array($ch, $opts);
	    $data  = curl_exec($ch);
	    $error = curl_error($ch);
	    curl_close($ch);
	    if($error) throw new \Exception('http error: ' . $error);
	    return  $data;
	}
	
	
	/**
	 * 发送HTTP请求下载远程文件
	 * @param  string $url    请求URL
	 * @param  array  $savefile 保存文件路径
	 */
	public static function httpDownload($url, $saveToPath){
	    $ch = curl_init($url);
        $fp = fopen($saveToPath, "wb");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $res=curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if($error) throw new \Exception('httpDownload error: ' . $error);
        return $res;
	}

    /**
     * 把数组的健值的首字母变为小写
     * @param $arr 数组对象
     * @param bool $deep 是否深层处理，默认false
     * @return array
     */
    public static function arrayLcFirst($arr, $deep = false)
    {
        if (is_array($arr)) {
            $newArray = [];
            foreach ($arr as $key => $val) {
                //深层处理
                if (is_array($val) && $deep) {
                    $val = self::arrayLcFirst($val, $deep);
                }
                $newArray[lcfirst($key)] = $val;
            }
            return $newArray;
        }

        return $arr;
    }

    /**
     * 读取文件指定的行
     * @param $file 文件路径
     * @param int $lineOffset 从第几行开始
     * @param int $lineCount 取多读几行
     * @return bool|null|string
     */
    public static function getFileLines($file, $lineOffset = 1, $lineCount = 0){
        $lines = []; // 初始化返回
        $i = 1; // 行数
        $handle = fopen($file, "r");
        while (!feof($handle)) {
            $buffer = fgets($handle);
            $i++;
            if($i > $lineOffset + 1) $lines[] = trim($buffer);
            if($lineCount && $i > $lineOffset + $lineCount) break;
        }
        fclose($handle);
        return $lines;
    }

    /**
     * 根据IP地址返回IP所在地
     * @param string $ipAddr
     */
    public static function getIpLocation($ipAddr = ''){
        if(!$ipAddr) $ipAddr = getClientIP();
        $city = new \ipip\db\City(__DIR__.'/17monipdb/ipipfree.ipdb');
        $info = $city->findMap($ipAddr, 'CN');
        return $info;
    }
}