<?php
namespace YZ\Core\Common;

class WebSocket
{
    private $socketApiUrl;
    private $socketApiUser;
    private $socketApiPwd;

    public function __construct()
    {
        $this->socketApiUrl = config('app.WS_API_URL');
        $this->socketApiUser = config('app.WS_API_USER');
        $this->socketApiPwd = config('app.WS_API_PWD');
    }

    public function sendToClientId($clientId, $content)
    {
        $data = [
            'act' => 'sendMsg',
            'clientid' => $clientId,
            'msg' => json_encode($content)
        ];
        $result = $this->send($data);
		if (!$result['result']) {
			throw new \Exception(json_encode($result));
		}
    }

	public function sendToGroup($groupId, $content)
    {
        $data = [
            'act' => 'sendMsgToGroup',
            'groupid' => $groupId,
            'msg' => json_encode($content)
        ];
        $result = $this->send($data);
		if ($result['result'] < 0) {
			throw new \Exception(json_encode($result));
		}
    }

    public function bindClient($clientId, $uid)
    {
        $data = [
            'act' => 'bindUid',
            'clientid' => $clientId,
            'uid' => $uid
        ];
        $result = $this->send($data);
		if (!$result['result']) {
			throw new \Exception(json_encode($result));
		}
    }

    public function setExtInfo($clientId, $info)
    {
        $data = [
            'act' => 'setExtInfo',
            'clientid' => $clientId,
            'info' => json_encode($info)
        ];
        $result = $this->send($data);
        if (!$result['result']) {
            throw new \Exception(json_encode($result));
        }
    }

	public function bindGroup($clientId, $groupId)
    {
        $data = [
            'act' => 'bindGroupId',
            'clientid' => $clientId,
            'groupid' => $groupId
        ];
        $result = $this->send($data);
		if (!$result['result']) {
			throw new \Exception(json_encode($result));
		}
    }

	public function send($data = [])
    {
		$url = $this->socketApiUrl;
		$auth = [
            'username' => $this->socketApiUser,
            'password' => $this->socketApiPwd,
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "${auth['username']}:${auth['password']}");
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.2; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)'); // 模拟用户使用的浏览器
        @curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $r = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($httpcode > 300 || $httpcode == 0){
            throw new \Exception("CURL请求出错,code=$httpcode,".curl_error($curl));
        }
		$json = @json_decode($r, true);
        curl_close($curl);
        return $json ? $json : $r;
    }
}