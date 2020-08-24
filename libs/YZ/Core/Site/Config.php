<?php

namespace YZ\Core\Site;

use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\License\SNUtil;
use YZ\Core\Model\ConfigModel;
use YZ\Core\Model\SmsConfigModel;
use YZ\Core\Model\PayConfigModel;

/**
 * 站点的配置对象，如站点的支付设置，短信设置等很多配置信息都在这里
 * Class Config
 * @package YZ\Core\Site
 */
class Config
{
    private $_model = null;
    private $_sn = null;
    private $_siteId = 0;
    private $_smsConfigModel = null;
    private $_payConfigModel = null;

    public function __construct($siteId)
    {
        $this->_siteId = $siteId;
        $this->_model = $this->findBySiteId($siteId);
        if (!$this->_model) {
            $this->_model = new ConfigModel();
            $this->_model->site_id = $this->_siteId;
            $this->_model->save();
            $this->_model = $this->findBySiteId($siteId);
        }
        $this->_sn = SNUtil::getSNInstanceBySite($siteId);
    }

    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->site_id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 返回短信设置
     * @return SmsConfigModel
     */
    public function getSmsConfig()
    {
        if (!$this->_smsConfigModel) {
            $this->_smsConfigModel = SmsConfigModel::query()->where('site_id', $this->_siteId)->first();
        }
        if ($this->_smsConfigModel) {
            return $this->_smsConfigModel;
        } else {
            return new SmsConfigModel();
        }
    }

    /**
     * 返回支付设置
     * @return PayConfigModel
     */
    public function getPayConfig()
    {
        if (!$this->_payConfigModel) {
            $this->_payConfigModel = PayConfigModel::query()->where('site_id', $this->_siteId)->first();
        }
        if ($this->_payConfigModel) {
            return $this->_payConfigModel;
        } else {
            return new PayConfigModel();
        }
    }

    /**
     * 商品评价的配置
     * @return array
     */
    public function getProductCommentConfig()
    {
        if ($this->_model && $this->_model->site_id) {
            return [
                'product_comment_status' => intval($this->_model->product_comment_status),
                'product_comment_check_way' => intval($this->_model->product_comment_check_way),
                'product_comment_auto_day' => intval($this->_model->product_comment_auto_day),
            ];
        } else {
            return [
                'product_comment_status' => 1,
                'product_comment_check_way' => 0,
                'product_comment_auto_day' => 0,
            ];
        }
    }

    /**
     * 返回版权的设置
     */
    public function getCopyRight(){
        $copyRightDefault = [
            'status' => 1,
            'style' => 1,
            'text' => '智应提供技术支持',
            'logo' => 'images/zhiying_logo.png'
        ];
        $configData = $this->getModel()->toArray();
        $copyRight = @json_decode($configData['copyright'], true);
        if (!is_array($copyRight)) {
            $copyRight = [];
        }
        $copyRight = array_merge($copyRightDefault,$copyRight);
        if ($copyRight['logo'] == 'null') $data['copyright']['logo'] = '';
        if ($copyRight['logo'] && strpos($copyRight['logo'],'images/') === false) {
            $copyRight['logo'] = Site::getSiteComdataDir('', false) . $copyRight['logo'];
        }
        if (!$this->_sn->hasPermission(Constants::FunctionPermission_ENABLE_CUSTOM_COPYRIGHT)) {
            $copyRight['status'] = 1;
            $copyRight['text'] = '智应提供技术支持';
        }
        return $copyRight;
    }

    /**
     * 保存
     * @param array $param
     * @param bool $reload
     */
    public function save(array $param, $reload = false)
    {
        if ($param) {
            unset($param['site_id']);
            if ($this->checkExist()) {
                $this->_model->fill($param);
                $this->_model->save();
            } else {
                $this->_model->site_id = $this->_siteId;
                $this->_model->fill($param);
                $this->_model->save();
            }
        }
        if ($reload) {
            $this->_model = $this->findBySiteId($this->_siteId);
        }
    }

    /**
     * 获取配置
     * @param $siteId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function findBySiteId($siteId)
    {
        if ($siteId) return ConfigModel::query()->where('site_id', $siteId)->first();
        else return null;
    }
}
