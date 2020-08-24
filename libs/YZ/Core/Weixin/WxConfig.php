<?php

namespace YZ\Core\Weixin;

use YZ\Core\Model\WxConfigModel;
use YZ\Core\Site\Site;

/**
 * 站点的微信公众号配置表
 * Class WxConfigModel
 * @package YZ\Core\Site
 */
class WxConfig
{
    private $_model = null;

    public function __construct($siteId = '')
    {
        if (!$siteId) {
            $siteId = Site::getCurrentSite()->getSiteId();
        }

        $this->_model = WxConfigModel::query()->where('site_id', $siteId)->first();
        // 如果没有，就新建一个
        if (!$this->_model) {
            $this->_model = new WxConfigModel();
            $this->_model->fill([
                'site_id' => $siteId,
                'token' => randString(16),
                'type' => 1
            ]);
            $this->_model->save();
            $this->_model = WxConfigModel::query()->where('site_id', $siteId)->first();
        }
    }

    /**
     * 获取数据模型
     * @return null|WxConfigModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 获取微信配置所属的站点ID
     * @return mixed
     */
    public function getSiteId()
    {
        return $this->getModel()->site_id;
    }

    /**
     * 获取绑定的域名
     * @return string
     */
    public function getDomain()
    {
        if ($this->checkExist()) {
            return trim($this->getModel()->domain);
        } else {
            return '';
        }
    }

    /**
     * 是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 公众号信息是否齐全
     * @return bool
     */
    public function infoIsFull()
    {
        if ($this->checkExist() && $this->_model->wxid && $this->_model->appid && $this->_model->appsecret && $this->_model->domain) {
            return true;
        }
        return false;
    }

    /**
     * 保存
     * @param array $param
     */
    public function save(array $param)
    {
        if ($param) {
            $this->_model->fill($param);
            $this->_model->save();
        }
    }

    /**
     * 检查配置信息是否齐全
     * @param bool $throw   是否抛出错误
     * @return bool
     * @throws \Exception
     */
    public static function checkConfig($throw = false)
    {
        $config = new WxConfig();
        if ($config->checkExist() && $config->infoIsFull()) {
            return true;
        }
        else {
            if ($throw) {
                throw new \Exception('请配置完整公众号信息');
            }
            return false;
        }
    }
}