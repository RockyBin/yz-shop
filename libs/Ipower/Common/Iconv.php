<?
namespace Ipower\Common;
/**
 * 文本编码转为工具，主要用来进行繁简转换
 * Class Iconv
 * @package Ipower\Common
 */
class Iconv
{
    private static function init(){
        define("MEDIAWIKI_PATH", dirname(__FILE__)."/../../mediawiki/mediawiki-1.15.2/");
        
        /* Include our helper class */
        require_once dirname(__FILE__)."/../../mediawiki/mediawiki-zhconverter.inc.php";
    }
    
    public static function gbToBig5($text)
    {
        //self::init();
        /* Convert it, valid variants such as zh, zh-cn, zh-tw, zh-sg & zh-hk */
        //return \MediaWikiZhConverter::convert($text, "zh-tw");

		$chinese = new \Ipower\Common\Chartable();
		return $chinese->gb2312_big5($text);
    }

    public static function big5ToGB($text)
    {
        //self::init();
        /* Convert it, valid variants such as zh, zh-cn, zh-tw, zh-sg & zh-hk */
        //return \MediaWikiZhConverter::convert($text, "zh-cn");

		$chinese = new \Ipower\Common\Chartable();
		return $chinese->big5_gb2312($text);
    }

    public static function detect_encoding($file) {
		$list = array('GBK', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1');
		$str = file_get_contents($file);
		foreach ($list as $item) {
		$tmp = mb_convert_encoding($str, $item, $item);
			if (md5($tmp) == md5($str)) {
				return $item;
			}
		}
		return null;
	}

	 public static function convert_file($file,$charset){
		$fileenc = self::detect_encoding($file);
		if(strtolower($fileenc) != strtolower($charset)){
			$content = file_get_contents($file);
			$content = mb_convert_encoding($content, $charset, $fileenc);
			$fd = fopen($file,"w+");
			fwrite($fd,$content);
			fclose($fd);
		}
	}
}