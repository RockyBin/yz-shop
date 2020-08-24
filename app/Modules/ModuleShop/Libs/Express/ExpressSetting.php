<?php
/**
 * 快递设置
 * User: liyaohui
 * Date: 2020/7/8
 * Time: 15:38
 */

namespace App\Modules\ModuleShop\Libs\Express;


use App\Modules\ModuleShop\Libs\Model\Express\ExpressSettingModel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\DistrictModel;

class ExpressSetting
{
    protected $siteId;
    protected $model;

    /**
     * ExpressSetting constructor.
     * @param null $siteId
     */
    public function __construct($siteId = null)
    {
        if (!$siteId) {
            $this->siteId = getCurrentSiteId();
        } else {
            $this->siteId = $siteId;
        }
        $this->model = ExpressSettingModel::query()->where('site_id', $this->siteId)->first();
        if (!$this->model) {
            $this->model = new ExpressSettingModel();
            $this->model->site_id = $this->siteId;
        }
    }

    /**
     * 检测状态
     * @return bool
     * @throws \Exception
     */
    public function checkStatus($throw = true)
    {
        if (!$this->model->status) {
            if ($throw) {
                throw new \Exception('快递功能未开启', 410);
            } else {
                return false;
            }
        }
        if (!$this->model->app_key || !$this->model->app_secret) {
            if ($throw) {
                throw new \Exception('快递接口未配置', 411);
            } else {
                return false;
            }
        }
        if (!$this->model->access_token) {
            if ($throw) {
                throw new \Exception('未授权', 412);
            } else {
                return false;
            }
        }
        if (!$this->checkAccessTokenExpires()) {
            if ($throw) {
                throw new \Exception('授权已过期', 413);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * access_token是否过期
     * @return bool
     */
    public function checkAccessTokenExpires()
    {
        return $this->model->expires_at && time() < strtotime($this->model->expires_at);
    }

    /**
     * 是否需要更新access_token
     * @return bool
     */
    public function needRefreshToken()
    {
        return $this->model->access_token && time() > strtotime($this->model->expires_at);
    }

    /**
     * @return ExpressSettingModel
     */
    public function getModel()
    {
        return $this->model;
    }

    public function getInfo()
    {
        $data = $this->model->toArray();
        $data['authorize_status'] = $this->getAuthorizeStatus();
        $data['callback_full_url'] = $this->getCallbackUrl();
        unset($data['access_token'], $data['refresh_token'], $data['openid'], $data['expires_at']);
        return $data;
    }

    /**
     * 获取授权状态
     * @return int
     */
    public function getAuthorizeStatus()
    {
        // 未授权
        if (!$this->model->access_token) {
            return 0;
        } else {
            // 已过期
            if (!$this->model->expires_at) {
                return -1;
            }
            if (time() >= strtotime($this->model->expires_at)) {
                return -1;
            }
            // 未过期
            return 1;
        }
    }

    /**
     * 保存设置
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function edit($params)
    {
        if (!$params['app_key'] || !$params['app_secret']) {
            throw new \Exception('请填入接口配置');
        }
        if (!$params['prov'] || !$params['city'] || !$params['district'] || !$params['address']) {
            throw new \Exception('请填入发货设置');
        }
        // 根据发货设置生成地址字符串
        $addressList = DistrictModel::query()
            ->whereIn('id', [$params['prov'], $params['city'], $params['district']])
            ->orderBy('level')
            ->pluck('name')->toArray();
        $params['address_text'] = implode('', $addressList) . ' ' . $params['address'];
        // 如果域名修改了 需要重新授权
        if ($this->model->expires_at && $params['redirect_domain'] != $this->model->redirect_domain) {
            $params['expires_at'] = date('Y-m-d H:i:s');
        }
        return $this->model->fill($params)->save();
    }

    /**
     * 生成授权地址
     * @param string $state
     * @return string
     */
    public function authorize($state = '') {
        // 获取当前毫秒
        $timestamp = $this->getMsectime();

        $redirectUri = $this->getCallbackUrl();
        $data = [
            "client_id" => $this->model->app_key,
            "response_type" => "code",
            "redirect_uri" => $redirectUri,
            "state" => $state,
            "timestamp" => $timestamp
        ];
        $sign = $this->generateSign($data);
        return "https://b.kuaidi100.com/open/oauth/authorize?response_type=code&client_id=" . $this->model->app_key . "&redirect_uri=".urlencode($redirectUri) . "&state=" . $state . "&timestamp=" . $timestamp . "&sign=".$sign;
    }

    /**
     * 生成调用接口的sign
     * @param $data
     * @return string
     */
    public function generateSign($data) {
        $appSecret = $this->model->app_secret;
        ksort($data);
        $str = '';
        foreach($data as $key => $val) {
            $str.= $key . $val;
        }
        $str = $appSecret . $str . $appSecret;
        $sign = strtoupper(md5($str));
        return $sign;
    }

    /**
     * 获取回调的完整地址
     * @return string
     */
    public function getCallbackUrl()
    {
        return "http://" . $this->model->redirect_domain . '/shop/front/express/callback';
    }

    /**
     * 获取当前的毫秒数
     * @return int
     */
    public function getMsectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        return intval((floatval($msec) + floatval($sec)) * 1000);
    }

    /**
     * @param array $data
     * @return bool
     */
    private function editAuthorizeData($data)
    {
        // 存入数据库
        $this->model->access_token = $data['access_token'];
        $this->model->refresh_token = $data['refresh_token'];
        $this->model->openid = $data['openid'];
        // 过期时间
        $this->model->expires_at = date('Y-m-d H:i:s', time() + $data['expires_in']);
        return $this->model->save();
    }

    /**
     * 用授权得到的code换取accessToken
     * @param $code
     * @return mixed
     * @throws \Exception
     */
    public function accessToken($code) {
        $timestamp = $this->getMsectime();
        $data = [
            "client_id" => $this->model->app_key,
            "client_secret" => $this->model->app_secret,
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => $this->getCallbackUrl(),
            "timestamp" => $timestamp
        ];
        $sign = self::generateSign($data);
        $data['sign'] = $sign;
        $res = ExpressHelper::httpsRequest("/open/oauth/accessToken", $data);
        $resArr = json_decode($res, true);
        Log::writeLog('expressAccessToken', 'siteId: ' . $this->siteId . "\r\n" . var_export($resArr, true));
        if (isset($resArr['status']) && $resArr['status'] != 200) {
            throw new \Exception($resArr['message']);
        }
        return $this->editAuthorizeData($resArr);
    }

    /**
     * 刷新授权token
     * @return bool
     * @throws \Exception
     */
    public function refreshToken() {
        $timestamp = $this->getMsectime();
        $data = [
            "client_id" => $this->model->app_key,
            "client_secret" => $this->model->app_secret,
            "refresh_token" => $this->model->refresh_token,
            "grant_type" => "refresh_token",
            "timestamp" => $timestamp
        ];
        $sign = self::generateSign($data);
        $data['sign'] = $sign;
        $res = ExpressHelper::httpsRequest("/open/oauth/refreshToken", $data);
        $resArr = json_decode($res, true);
        Log::writeLog('expressRefreshToken', 'siteId: ' . $this->siteId . "\r\n" . var_export($resArr, true));
        if (isset($resArr['status']) && $resArr['status'] != 200) {
            throw new \Exception($resArr['message']);
        }
        return $this->editAuthorizeData($resArr);
    }
}