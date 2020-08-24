<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Model\ProductModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\DistributionFormSettingModel;
use App\Modules\ModuleShop\Libs\Model\DistributionSettingModel;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;

/**
 * 分销商设置
 */
class DistributionSetting
{
    private $_settingModel = null;
    private $_formSettingModel = null;
    private $_siteId = 0;

    /**
     * 初始化分销商设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_settingModel = DistributionSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_settingModel) {
            $this->_settingModel = new DistributionSettingModel();
            $this->_settingModel->site_id = $this->_siteId;
            $this->_settingModel->level = 3;
            $this->_settingModel->save();
            $this->_settingModel = DistributionSettingModel::where(['site_id' => $this->_siteId])->first();
        }
        $this->_formSettingModel = DistributionFormSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_formSettingModel) {
            $this->_formSettingModel = new DistributionFormSettingModel();
            $this->_formSettingModel->site_id = $this->_siteId;
            $this->_formSettingModel->save();
            $this->_formSettingModel = DistributionFormSettingModel::where(['site_id' => $this->_siteId])->first();
        }
    }

    public function getSettingModel()
    {
        return $this->_settingModel;
    }

    public function getFormSettingModel()
    {
        return $this->_formSettingModel;
    }

    /**
     * 保存分销设置
     * @param array $info 设置内容，$info['baseinfo'] = array(),基本设置项；$info['forminfo'] = array(),申请表单设置项
     */
    public function save(array $info)
    {
        if ($info['baseinfo']['buy_money']) $info['baseinfo']['buy_money'] = intval($info['baseinfo']['buy_money'] * 100);
        $this->_settingModel->fill($info['baseinfo']);
        $this->_settingModel->save();
        if (is_array($info['forminfo']['extend_fields']) || $info['forminfo']['extend_fields']) {
            $info['forminfo']['extend_fields'] = json_encode($info['forminfo']['extend_fields'], JSON_UNESCAPED_UNICODE);
        }
        $this->_formSettingModel->fill($info['forminfo']);
        $this->_formSettingModel->save();
    }

    /**
     * 保存分销基础设置
     * @param array $info 设置内容
     * @return bool
     */
    public function saveBase(array $info)
    {
        $data = [
            'level' => $info['level'] ?: 0,
            'internal_purchase' => $info['internal_purchase'] ? 1 : 0,
            'calc_valid_condition' => $info['calc_valid_condition'] ? 1 : 0 ,
            'calc_performance_valid_condition' => $info['calc_performance_valid_condition'] ? 1 : 0 ,
            'calc_commission_valid_condition' => $info['calc_commission_valid_condition'] ? 1 : 0 ,
            'calc_upgrade_valid_condition' => $info['calc_upgrade_valid_condition'] ? 1 : 0 ,
            'calc_apply_valid_condition' => $info['calc_apply_valid_condition'] ? 1 : 0
        ];
        $this->_settingModel->fill($data);
        return $this->_settingModel->save();
    }

    public function getInfo()
    {
        $baseinfo = $this->_settingModel->toArray();
        if ($baseinfo['buy_money']) $baseinfo['buy_money'] = round($baseinfo['buy_money'] / 100, 2);
        if ($baseinfo['buy_product']) {
            $pro = ProductModel::find($baseinfo['buy_product']);
            if ($pro) $baseinfo['buy_product_type'] = $pro->type;
        }
        $baseinfo['aftersale_isopen']=(new OrderConfig())->getInfo()['aftersale_isopen'];
        $forminfo = $this->_formSettingModel->toArray();
        $forminfo['extend_fields'] = json_decode($forminfo['extend_fields'], true);
        return ['baseinfo' => $baseinfo, 'forminfo' => $forminfo];
    }

    public function getBaseInfo()
    {
        $baseInfo = $this->_settingModel->toArray();
        $baseInfo['aftersale_isopen'] = (new OrderConfig())->getInfo()['aftersale_isopen'];
        return $baseInfo;
    }

    /**
     * 返回当前站点的基本分销设置
     * @return DistributionSettingModel
     */
    public static function getCurrentSiteSetting(){
        $distributionSetting = new DistributionSetting();
        return $distributionSetting->getSettingModel();
    }
}
