<?php
namespace YZ\Core\Member\ThirdLogin;

class QQLogin {
	private $_clientId = ""; // appid
	private $_clientSecret = ""; // appkey
	private $_redirectUri = ""; // 回调地址
	private $_state = ""; // 验证窜，防攻击
	private $_openId = ""; //qq唯一标示
	private $_code = ""; //code
	private $_accessToken = ""; // token
	private $_errMsg = "";
	private $_splitStr = "<br/>";

	public function getClientId()
	{
		return $this->_clientId;
	}

	public function getClientSecret()
	{
		return $this->_clientSecret;
	}

	public function getRedirectUri()
	{
		return $this->_redirectUri;
	}

	public function getState()
	{
		return $this->_state;
	}

	public function setState($value)
	{
		$this->_state = $value;
	}

	public function getOpenId()
	{
		return $this->_openId;
	}

	public function setOpenId($value)
	{
		$this->_openId = $value;
	}

	public function getCode()
	{
		return $this->_code;
	}

	public function setCode($value)
	{
		$this->_code = $value;
	}

	public function getAccessToken()
	{
		return $this->_accessToken;
	}

	public function setAccessToken($value)
	{
		$this->_accessToken = $value;
	}

	public function getErrMsg()
	{
		return $this->_errMsg;
	}

	public function setErrMsg($value)
	{
		$this->_errMsg = $value;
	}

	public function getSplitStr()
	{
		return $this->_splitStr;
	}

	public function setSplitStr($value)
	{
		$this->_splitStr = $value;
	}

    public function __construct($appId,$appSecret,$redirectUrl)
    {
		$this->_clientId = $appId;
		$this->_clientSecret = $appSecret;
		$this->_redirectUri = $redirectUrl;
		$this->_state = time();
    }

	//获取登陆字符串
	public function getLogonUrl()
	{
		return "https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id=" . $this->_clientId . "&state=" . $this->_state . "&redirect_uri=" . urlencode($this->_redirectUri);
	}

	//获取 token
	public function getToken()
	{
		if ($this->_code != "")
		{
			$postdata = "grant_type=authorization_code&client_id=" . $this->_clientId . "&client_secret=" . $this->_clientSecret . "&code=" . $this->_code. "&redirect_uri=" . urlencode($this->_redirectUri);
			$url = "https://graph.qq.com/oauth2.0/token";
			$result = $this->httprequest( $url,'post',$postdata );
			//echo 'getToken: '.$result;
			if (strpos($result,"access_token=") !== false && strpos($result,"refresh_token=") !== false)
			{
				$dataArr = explode('&',$result);
				foreach ($dataArr as $strTmp)
				{
					$paraArr = explode('=',$strTmp);
					if (count($paraArr) > 1)
					{
						if (strtolower($paraArr[0]) == "access_token")
						{
							$this->_accessToken = $paraArr[1];
							break;
						}
					}
				}
			}
			else
			{
				$ht = $this->parseCallback($result);
				foreach ($ht as $key => $val)
				{
					$this->_errMsg .= $key.":".$val.$this->_splitStr;
				}
			}
		}
		//echo 'access_token:'.$this->_accessToken;exit;
	}

	//获取基本信息
	public function getUserInfo()
	{
		$result = array();
		try
		{
			if ($this->_accessToken != "" && $this->_openId != "")
			{				
				$postdata = "access_token=" . $this->_accessToken."&oauth_consumer_key=".$this->_clientId."&openid=".$this->_openId;
				$url = "https://graph.qq.com/user/get_user_info";
				$json = $this->httprequest($url,'post',$postdata );
				$result = json_decode($json,true);
				$result['openid'] = $this->_openId;
			}
		}
		catch (\Exception $e)
		{
			$this->_errMsg .= $e->getMessage() . $this->_splitStr;
		}

		return $result;
	}

	//处理callback
	public function parseCallback($str)
	{
		$result = array();
		try
		{
			$str = trim($str);
			if (substr($str,0,8) == "callback")
			{
				$json = trim(str_replace(');','',str_replace('callback(','',$str)));
				$result = json_decode($json,true);
			}
		}
		catch (\Exception $e)
		{
			$this->_errMsg .= $e->getMessage() . $this->_splitStr;
		}

		return $result;
	}

	//获取Openid
	public function getOpenId2()
	{
		if ($this->_accessToken != "")
		{
			$postdata = "access_token=" . $this->_accessToken;
			$url = "https://graph.qq.com/oauth2.0/me";
			$result = $this->httprequest($url,'post',$postdata );

			$ht = $this->parseCallback($result);
			$this->_openId = trim($ht["openid"]);
		}
	}

	private function httprequest($url,$method = 'GET',$postdata){
		$ch = curl_init ();
		curl_setopt($ch, CURLOPT_URL, $url );
		if(strtolower($method) == 'post'){
			curl_setopt($ch, CURLOPT_POST, 1 );
			if($postdata) curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata );
		}
		curl_setopt($ch, CURLOPT_HEADER, 0 );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($ch);
		if($errno = curl_errno($ch)) {
			$error_message = curl_strerror($errno);
			echo "cURL error ({$errno}):\n {$error_message}";
		}
		curl_close($ch);
		return $result;
	}
}
