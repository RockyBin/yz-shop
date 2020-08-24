<?php
//phpcodelock
namespace App\Http\Controllers\Common;

use Illuminate\Support\Facades\Request;
use YZ\Core\Site\Site;
/**
 * 视频相关工具
 * Class VideoUtilController
 * @package App\Http\Controllers\Common
 */
class VideoUtilController
{
	/**
	 * 获取抖音、快手等的视频播放地址
	 */
    public function getSrc(){
		try {
			$url = Request::get('url'); //抖音、快手等的分享链接
			$url = $this->transUrl($url);
			$src = '';
			if (stripos($url,'m.huya.com') !== false) { //虎牙的
				$html = $this->getContentByCurl($url);
			} elseif (stripos($url,'now.qq.com/h5/record.html') !== false) { //NOW回放
				$html = $this->getContentBySplash($url);
			} elseif (stripos($url,'.inke.cn') !== false) { //映客的
				$urlinfo = parse_url($url);
				$query = [];
				parse_str($urlinfo['query'], $query);
				$src = $this->getInkeLiveStream($query['uid'], $query['liveid']);
			} else { //其它的，通过爬虫工具来搞
				$html = $this->getContentByPuppeteer($url);
			}
			if (!$src && $html){
				preg_match('/<video[^>]+/i',$html,$match);
				preg_match('/src="([^"]+)"/i',$match[0],$match);
				$src = $match[1];
				if (substr($src, 0, 2) == "//") {
					$protocol = stripos($url,"https:") !== false ? "https:" : "http:";
					$src = $protocol.$src; //自动补全URL协议
				}
			}
			return makeApiResponseSuccess('ok',['src' => $src]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
	
	// 有些分享在地址是不会输入video标签的，需要对URL进行一些转换
	public function transUrl($url){
		if (stripos($url,".yy.com/share/") !== false){
			//原始网址为 https://www.yy.com/share/i/1675145377/68152500/68152500/1585450?version=7.28.1&edition=1&platform=5&config_id=79&userUid=0 
			preg_match_all("/(\d+)/",$url,$m);
			$roomid = $m[0][2];
			if($roomid) $url = "https://wap.yy.com/mobileweb/".$roomid."/".$roomid;
		} elseif (stripos($url,"www.yy.com") !== false) {
			$url = str_replace("www.yy.com/", "wap.yy.com/mobileweb/",$url);
		} elseif (stripos($url,"www.huya.com") !== false) {
			$url = str_replace("www.huya.com", "m.huya.com",$url);
		} elseif (stripos($url,"now.qq.com/h5/index.html") !== false){
			preg_match("/roomid=(\d+)/",$url,$m);
			$roomid = $m[1];
			if($roomid) $url = "https://now.qq.com/pcweb/story.html?roomid=".$roomid;
		} 
		return $url;
	}

	/**
	 * 使用PHP原生的方式读取页面内容，适合那些直接在页面上输出 video 标签的视频网站
	 */
	private function getContentByCurl($url){
		$header = [
			"User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1"
		];
		$result = \Ipower\Common\Util::http($url, [], $method = 'GET', $header);
        return $result;
	}
	
	/**
	 * 使用 splash 获取页面源码 https://github.com/scrapinghub/splash
	 */
	private function getContentBySplash($url){
		$url = "http://122.114.18.93:8050/render.html?url=".urlencode($url)."&timeout=10&wait=1&images=0";
		$params = [
			"headers" => [
				"User-Agent" => "Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1"
			]
		];
		$postdata = json_encode($params);
        $options = array(
            'http' => array(
                'method' => "POST",
                'header' => 'Content-Type: application/json',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
	}

	/**
	 * 使用 puppeteer 获取页面源码
	 */
	private function getContentByPuppeteer($url){
		$result = file_get_contents("http://h561.72dns.net:20002/?cmd=getcontent&waitForSelector=video&url=".$url);
        return $result;
	}

	/**
	 * 使用 PhantomJs 获取页面源码
	 */
	private function getContentByPhantomJs($url){
		$result = file_get_contents("http://h561.72dns.net:20001/spider2.php?cmd=getcontent&url=".$url);
        return $result;
	}

	/**
	 * 使用 python selenium 获取页面源码
	 */
	private function getContentByPython($url){
		$result = file_get_contents("http://h561.72dns.net:20001/spider3.php?cmd=getcontent&url=".$url);
        return $result;
	}
	
	/**
	 * 使用映客API获取视频地址  映客获取单个主播API地址为：http://baseapi.busi.inke.cn/live/LiveInfo?uid=映客UID&liveid=映客直播ID
	 * 这个API网上没有找到官方公开的资料，后面可能会不能用，这时可以用 getContentByPuppeteer() 来获取
	 * @param $ykuid 映客ID，可为空
	 * @param $ykliveid 映客直播ID，不能为空
	 */
    public static  function getInkeLiveStream($ykuid, $ykliveid)
    {
        $process = curl_init('http://baseapi.busi.inke.cn/live/LiveInfo?uid=' . $ykuid . '&liveid=' . $ykliveid);
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
        $return = curl_exec($process);
        curl_close($process);
        $decode_html_info = json_decode($return);
        $url = '';
        if ($decode_html_info->message = 'success') {
            if(isset($decode_html_info->data->live_addr[0])){
				/*
				//这个是flv地址，用于PC的
                if(isset($decode_html_info->data->live_addr[0]->stream_addr)){
                    $url = $decode_html_info->data->live_addr[0]->stream_addr;
                }*/
				//这个是m3u8地址，用于手机的
				if(isset($decode_html_info->data->live_addr[0]->hls_stream_addr)){
                    $url = $decode_html_info->data->live_addr[0]->hls_stream_addr;
                }
            }
        }
        //返回空即为已停止直播
        return $url;
    }
}