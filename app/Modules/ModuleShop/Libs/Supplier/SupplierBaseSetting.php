<?php

namespace App\Modules\ModuleShop\Libs\Supplier;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierBaseSettingModel;
use YZ\Core\License\SNUtil;
use App\Modules\ModuleShop\Libs\Constants;
use PHPUnit\Runner\Exception;

/**
 * 供应商基础设置
 */
class SupplierBaseSetting
{
    private $_settingModel = null;
    private $_siteId = 0;

    /**
     * 初始化设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_settingModel = SupplierBaseSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_settingModel) {
            $this->_settingModel = new SupplierBaseSettingModel();
            $this->_settingModel->site_id = $this->_siteId;
            $this->_settingModel->save();
            $this->_settingModel = SupplierBaseSettingModel::where(['site_id' => $this->_siteId])->first();
        }
    }

    public function getSettingModel()
    {
        return $this->_settingModel;
    }

    /**
     * 获取设置信息
     *
     * @return array
     */
    public function getInfo()
    {
        $info = $this->_settingModel->toArray();
        return $info;
    }

    /**
     * 保存代理设置
     * @param array $info 设置内容
     */
    public function save(array $info)
    {
        $this->_settingModel->fill($info);
        $this->_settingModel->save();
    }

    /**
     * 返回当前站点的代理设置
     * @return SupplierBaseSettingModel
     */
    public static function getCurrentSiteSetting()
    {
        $agentSetting = new static();
        return $agentSetting->getSettingModel();
    }
}
