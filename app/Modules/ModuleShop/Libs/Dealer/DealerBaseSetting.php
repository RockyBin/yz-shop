<?php

namespace App\Modules\ModuleShop\Libs\Dealer;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\DealerBaseSettingModel;
use YZ\Core\License\SNUtil;


/**
 * 经销商基础设置
 */
class DealerBaseSetting
{
    private $_settingModel = null;
    private $_siteId = 0;

    /**
     * 初始化经销商设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_settingModel = DealerBaseSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_settingModel) {
            $this->_settingModel = new DealerBaseSettingModel();
            $this->_settingModel->site_id = $this->_siteId;
            $this->_settingModel->save();
            $this->_settingModel = DealerBaseSettingModel::where(['site_id' => $this->_siteId])->first();
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
     * 保存经销商设置
     * @param array $info 设置内容
     */
    public function save(array $info)
    {
        // TODO 权限待补
        $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
//        if (!$sn->hasPermission(Constants::FunctionPermission_ENABLE_AGENT)) {
//            throw new Exception("当前商城没有经销商功能权限");
//        }

        if($info['purchases_money_target'] != 1){
            unset($info['pay_parent_type']);
        }
        $this->_settingModel->fill($info);
        $this->_settingModel->save();
    }

    /**
     * 返回当前站点的代理设置
     * @return AgentBaseSettingModel
     */
    public static function getCurrentSiteSetting()
    {
        $agentSetting = new static();
        return $agentSetting->getSettingModel();
    }

}
