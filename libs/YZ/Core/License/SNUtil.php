<?php
//phpcodelock
namespace YZ\Core\License;
class SNUtil {

    private static $platformSn = null;

	/*
	 * 获取指定网站的序列号信息
	 * @param mixed $site tbl_site 的一行或id
	 */
	public static function getSNInstanceBySite($site){
	    if(is_numeric($site)) $site = \YZ\Core\Model\SiteModel::find($site)->toArray();
	    return self::getSNInstance($site['sn']);
	}

    /**
     * 根据序列号字符串解析出序列号对象
     * @param $sn
     * @return AbstractSN
     */
	public static function getSNInstance($sn) : AbstractSN{
	    if (!$sn) return new UnknowSN();
        try{
            $des = new \Ipower\Common\CryptDes();
            $str = $des->decrypt($sn);
            $arr = explode('@',$str);
            if($arr[4]) {
                $class = "\\" . $arr[4];
                return $class::createInstanceWithPlainText($str);
            }else{
                return UnknowSN::createInstanceWithPlainText($str);
            }
        }catch(\Exception $ex){
            return new UnknowSN();
        }
	}

    /**
     * 判读此系统是否是支持多用户的平台版，实际上是用来区别导出的网站和我们自己用的SAAS平台
     * @return bool
     * @throws \Exception
     */
	public static function isPlatformVersion(){
	   if(!self::$platformSn) self::$platformSn = self::getSNInstance(self::readFileSN());
	   return self::$platformSn->version == 9;
	}

    /**
     * 读取平台的序列号文件信息，用来判读此系统是否是支持多用户的平台版本，导出的网站是不应该有此文件的
     * @return bool|string
     * @throws \Exception
     */
	private static function readFileSN(){
	    $oresult = "";
	    try{
	        $snfile = base_path(). DIRECTORY_SEPARATOR . "sn.config";
	        if (file_exists($snfile)) $oresult = file_get_contents($snfile);
	    }
	    catch(\Exception $ex) { throw new \Exception("ERROR：读取文件出错<br />" . $ex->getMessage()); }
	    return $oresult;
	}
}