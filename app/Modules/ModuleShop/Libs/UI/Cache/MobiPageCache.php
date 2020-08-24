<?php
//phpcodelock
namespace App\Modules\ModuleShop\Libs\UI\Cache;
use YZ\Core\Site\Site;

class MobiPageCache {
        private static function getCacheDir($siteId){
            $dir = Site::getSiteComdataDir($siteId,true).'/MobiPageCache';
			if(!file_exists($dir)) \Ipower\Common\Util::mkdirex($dir);
			return $dir;
		}

		public static function getCacheFile($siteId,$pageId){
			$dir = self::getCacheDir($siteId);
            $file = $dir.'/page_'.$pageId.'.cache';
            return $file;
        }
        
		//$pageId 页面的ID
        public static function add($siteId,$pageId,$cacheData){
            //写入文件
            $fd = fopen(self::getCacheFile($siteId,$pageId),'w+');
			if(!$fd) throw new \Exception("cache file error");
            fwrite($fd, serialize($cacheData));
            fclose($fd);
        }
        
        public static function remove($siteId = 0,$pageId = 0){
            if(!$siteId){
                $siteId = Site::getCurrentSite()->getSiteId();
            }
			if($pageId){
                //清除缓存文件
				$file = self::getCacheFile($siteId,$pageId);
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
        
        public static function get($siteId,$pageId){
			//从文件中获取缓存
			$file = self::getCacheFile($siteId,$pageId);
            if(file_exists($file) && filemtime($file) > time() - 86400000){ //最多缓存1000天，相关于永久缓存
                $cache = unserialize(file_get_contents($file));
				return $cache;
            }
            return null;
        }
}