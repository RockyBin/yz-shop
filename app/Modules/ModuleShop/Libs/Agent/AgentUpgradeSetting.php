<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Product\Product;
use YZ\Core\Plugin\PluginHelper;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\AgentUpgradeSettingModel;
use App\Modules\ModuleShop\Libs\Agent\AgentLevel;
use Illuminate\Support\Collection;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use App\Modules\ModuleShop\Libs\Constants;

/**
 * 代理升级设置
 */
class AgentUpgradeSetting
{
    private $_upgradeModel = null;
    private $_siteId = 0;

    /**
     * 初始化代理设置对象
     */
    public function __construct()
    {
        //因为代理升级与代理等级有关系，需要先生成等级。否则无法进行往后的操作
        new AgentLevel();
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_upgradeModel = AgentUpgradeSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_upgradeModel) {
            $this->_upgradeModel = new AgentUpgradeSettingModel();
            $this->_upgradeModel->site_id = $this->_siteId;
            $this->_upgradeModel->status = 0;
            $this->_upgradeModel->order_valid_condition = 1;
            $this->_upgradeModel->save();
            $this->_upgradeModel = AgentUpgradeSettingModel::where(['site_id' => $this->_siteId])->first();
        }
    }

    public function getUpgradeModel()
    {
        return $this->_upgradeModel;
    }

    /**
     * 保存代理设置
     * @param array $info 设置内容
     */
    public function save(array $info)
    {
        $this->_upgradeModel->fill($info);
        $this->_upgradeModel->save();
    }

    /**
     * 返回当前站点的代理设置
     * @return AgentBaseSettingModel
     */
    public static function getCurrentSiteSetting()
    {
        $agentSetting = new static();
        return $agentSetting->getUpgradeModel();
    }

    /**
     * 返回后台升级设置需要使用的数据
     * @return mixed
     * @throws \Exception
     */
    public function info()
    {
        $agentUpgradeModeldata = $this->getUpgradeModel();
        $agentLevel = new AgentLevel();
        $agentLevel = $agentLevel->getLevelModel();
        $data['agentUpgrade'] = $agentUpgradeModeldata;
        $data['agentLevel'] = $agentLevel;
        foreach ($data['agentLevel'] as &$item) {
            if ($item->upgrade_condition) {
                $arr = json_decode($item->upgrade_condition, true);
                if ($arr['product_id']) {
                    $product_list = Product::getList(['product_ids' => myToArray($arr['product_id'])]);
                    $arr['product_list'] = $product_list['list'];
                }
                foreach ($arr['upgrade'] as &$v){
                    if(in_array($v['type'],[Constants::AgentLevelUpgradeCondition_SelfOrderMoney,Constants::AgentLevelUpgradeCondition_DirectlyOrderMoney,Constants::AgentLevelUpgradeCondition_IndirectOrderMoney,Constants::AgentLevelUpgradeCondition_TeamOrderMoney,Constants::AgentLevelUpgradeCondition_TotalChargeMoney,Constants::AgentLevelUpgradeCondition_OnceChargeMoney])  && $v['value']){
                        $v['value']=$v['value']/100;
                    }
                }
                $item->upgrade_condition = $arr;
            }
            // 获取定制的条件
            $pluginConditions = PluginHelper::loadUpgradeConditionPluginsConfig('AgentUpgradeCondition', $item->level);
            if ($pluginConditions) $item->plugin_upgrade_conditon = $pluginConditions;
        }
        // 订单设置里面的是否打开了售后
        $orderConfig = new OrderConfig();
        $data['aftersale_isopen'] = $orderConfig->getInfo()->aftersale_isopen;

        //分佣层级等于0的时候代表关闭了功能
        $baseSetting = AgentBaseSetting::getCurrentSiteSetting();
        $data['baseSetting'] = $baseSetting->level == 0 ? false : true;
        $data['enabledAgentLevel'] = $baseSetting->level;
        return $data;
    }

    /**
     * 是否是需要商品的条件
     * @param $condition
     * @return bool
     */
    public static function isNeedProductCondition($condition)
    {
        if (in_array($condition['type'], [
            Constants::AgentLevelUpgradeCondition_SelfBuyDesignatedProduct,
            Constants::AgentLevelUpgradeCondition_DirectlyBuyDesignatedProduct,
            Constants::AgentLevelUpgradeCondition_IndirectBuyDesignatedProduct,
            Constants::AgentLevelUpgradeCondition_TeamBuyDesignatedProduct,
//            Constants::AgentLevelUpgradeCondition_RecommendOneLevelAgentNum,
//            Constants::AgentLevelUpgradeCondition_RecommendTwoLevelAgentNum,
            Constants::AgentLevelUpgradeCondition_SelfBuyAllDesignatedProduct
        ]) && $condition['value']) {
            return true;
        } else {
            return false;
        }
    }
}
