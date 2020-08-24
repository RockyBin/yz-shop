<?php
//phpcodelock
namespace App\Modules\ModuleShop\Libs\UI\Cache;
use YZ\Core\Site\Site;

class MobiModuleCache {
        private static function getCacheDir($siteId){
            $dir = Site::getSiteComdataDir($siteId,true).'/MobiModuleCache';
			if(!file_exists($dir)) \Ipower\Common\Util::mkdirex($dir);
			return $dir;
		}

		public static function getCacheFile($siteId,$cacheKey){
			$dir = self::getCacheDir($siteId);
            $file = $dir.'/'.$cacheKey.'.cache';
            return $file;
        }
        
		//$cacheKey 一般是查找相应模块的sql语句的md5值
        public static function add($siteId,$cacheKey,$cacheData){
            //写入文件
            $fd = fopen(self::getCacheFile($siteId,$cacheKey),'w+');
			if(!$fd) throw new \Exception("cache file error");
            fwrite($fd, serialize($cacheData));
            fclose($fd);
        }
        
        public static function remove($siteId = 0,$cacheKey = ''){
            if(!$siteId){
                $siteId = Site::getCurrentSite()->getSiteId();
            }
			if($cacheKey){
                //清除缓存文件
				$file = self::getCacheFile($siteId,$cacheKey);
				if(file_exists($file)){
					unlink($file);
				}
			}else{
				$dir = self::getCacheDir($siteId);
				if(file_exists($dir)){
					\Ipower\Common\Util::emptydir($dir);
				}
			}
        }
        
        public static function get($siteId,$cacheKey){
			if($_GET['upm'] == '1' ) return null;
			//从文件中获取缓存
			$file = self::getCacheFile($siteId,$cacheKey);
            if(file_exists($file) && filemtime($file) > time() - 86400000){ //最多缓存1000天，相关于永久缓存
                $cache = unserialize(file_get_contents($file));
				return $cache;
            }
            return null;
        }
}