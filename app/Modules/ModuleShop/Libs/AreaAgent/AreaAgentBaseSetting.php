<?php
/**
 * 区域代理基础设置逻辑类
 * User: liyaohui
 * Date: 2020/5/18
 * Time: 16:13
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentBaseSettingModel;
use YZ\Core\License\SNUtil;
use YZ\Core\Site\Site;

class AreaAgentBaseSetting
{
    private $_settingModel = null;
    private $_siteId = 0;

    /**
     * 初始化代理设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_settingModel = AreaAgentBaseSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_settingModel) {
            $this->_settingModel = new AreaAgentBaseSettingModel();
            $this->_settingModel->site_id = $this->_siteId;
            $this->_settingModel->save();
            $this->_settingModel = AreaAgentBaseSettingModel::where(['site_id' => $this->_siteId])->first();
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
     * 保存区域代理设置
     * @param array $info
     * @throws \Exception
     */
    public function save(array $info)
    {
        $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
        if (!$sn->hasPermission(Constants::FunctionPermission_ENABLE_AREA_AGENT)) {
            throw new \Exception("当前商城没有区域代理功能权限");
        }
        if ($info['site_id']) unset($info['site_id']);
        $this->_settingModel->fill($info);
        $this->_settingModel->save();
    }

    /**
     * 返回当前站点的区域代理设置
     * @return AgentBaseSettingModel
     */
    public static function getCurrentSiteSetting()
    {
        $agentSetting = new static();
        return $agentSetting->getSettingModel();
    }
}