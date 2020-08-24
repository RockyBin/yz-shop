<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\AgentOtherRewardSettingModel;

/**
 * 代理其他奖励奖设置
 */
class AgentOtherRewardSetting
{
    private $_settingModel = null;
    private $_siteId = 0;

    /**
     * 初始化代理销售奖设置对象
     * @type 其他奖的type
     */
    public function __construct($type)
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_settingModel = AgentOtherRewardSettingModel::where(['site_id' => $this->_siteId,'type'=>$type])->first();
        if (!$this->_settingModel) {
            $this->_settingModel = new AgentOtherRewardSettingModel();
            $this->_settingModel->site_id = $this->_siteId;
            $this->_settingModel->type = $type;
            $this->_settingModel->save();
            $this->_settingModel = AgentOtherRewardSettingModel::where(['site_id' => $this->_siteId])->first();
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
        $this->_settingModel->fill($info);
        $this->_settingModel->save();
    }

    /**
     * 返回当前站点的代理销售奖设置
     * @return AgentSaleRewardSettingModel
     */
    public static function getCurrentSiteSetting($type)
    {
        $otherRewardSetting = new AgentOtherRewardSetting($type);
        return $otherRewardSetting->getSettingModel();
    }

}
