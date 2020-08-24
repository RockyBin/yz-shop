<?php

namespace App\Modules\ModuleShop\Libs\Crm;


use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\SiteAdminModel;

class WxDecryptData
{
    private $appid;
    private $sessionKey;

    /**
     * 构造函数
     * @param $sessionKey string 用户在小程序登录后获取的会话密钥
     * @param $appid string 小程序的appid
     */
    public function __construct($appid, $sessionKey)
    {
        $this->sessionKey = $sessionKey;
        $this->appid = $appid;
    }


    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $data string 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptData($encryptedData, $iv)
    {
        if (strlen($this->sessionKey) != 24) {
            return makeServiceResultFail('sessionKey错误');
        }
        $aesKey = base64_decode($this->sessionKey);


        if (strlen($iv) != 24) {
            return makeServiceResultFail('iv错误');
        }
        $aesIV = base64_decode($iv);

        $aesCipher = base64_decode($encryptedData);

        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);

        $dataObj = json_decode($result);
        if ($dataObj == NULL) {
            Log::writeLog('deencry',json_encode('iv:'.$iv.'--'.'encryptedData:'.$encryptedData.'----'.'sessionKey:'.$this->sessionKey));
            return makeServiceResultFail('解析错误1', ['iv' => $iv, 'encryptedData' => $encryptedData, 'sessionKey' => $this->sessionKey]);
        }
        if ($dataObj->watermark->appid != $this->appid) {
            Log::writeLog('deencry',json_encode('iv:'.$iv.'--'.'encryptedData:'.$encryptedData.'----'.'sessionKey:'.$this->sessionKey));
            return makeServiceResultFail('解析错误2');
        }
        return makeServiceResultSuccess('ok', $result);
    }

}