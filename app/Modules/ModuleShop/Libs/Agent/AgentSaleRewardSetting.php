<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\AgentSaleRewardSettingModel;

/**
 * 代理销售奖设置
 */
class AgentSaleRewardSetting
{
    private $_settingModel = null;
    private $_siteId = 0;

    /**
     * 初始化代理销售奖设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_settingModel = AgentSaleRewardSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_settingModel) {
            $this->_settingModel = new AgentSaleRewardSettingModel();
            $this->_settingModel->site_id = $this->_siteId;
            $this->_settingModel->enable = 0;
            $this->_settingModel->save();
            $this->_settingModel = AgentSaleRewardSettingModel::where(['site_id' => $this->_siteId])->first();
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
        $info['commision'] = json_decode($info['commision']);
        $baseSetting = AgentBaseSetting::getCurrentSiteSetting();
        //分佣层级等于0的时候代表关闭了功能
        $info['baseSetting'] = $baseSetting->level == 0 ? false : true;
        return $info;
    }

    /**
     * 保存代理销售奖设置
     * @param array $info 设置内容
     */
    public function save(array $info)
    {
        if (is_array($info['commision'])) $info['commision'] = json_encode($info['commision']);
        $this->_settingModel->fill($info);
        $this->_settingModel->save();
    }

    /**
     * 返回当前站点的代理销售奖设置
     * @return AgentSaleRewardSettingModel
     */
    public static function getCurrentSiteSetting()
    {
        $saleRewardSetting = new AgentSaleRewardSetting();
        return $saleRewardSetting->getSettingModel();
    }

    /**
     * 格式化输出数据
     * @return AgentSaleRewardSettingModel
     */
    public static function getCurrentSiteSettingFormat()
    {
        $setting = self::getCurrentSiteSetting();
        $setting->commision = json_decode($setting->commision, true);
        return $setting;
    }
}
