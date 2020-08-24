<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\AgentBaseSettingModel;
use YZ\Core\License\SNUtil;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use PHPUnit\Runner\Exception;

/**
 * 代理基础设置
 */
class AgentBaseSetting
{
    private $_settingModel = null;
    private $_siteId = 0;

    /**
     * 初始化代理设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_settingModel = AgentBaseSettingModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_settingModel) {
            $this->_settingModel = new AgentBaseSettingModel();
            $this->_settingModel->site_id = $this->_siteId;
            $this->_settingModel->level = 0;
            $this->_settingModel->save();
            $this->_settingModel = AgentBaseSettingModel::where(['site_id' => $this->_siteId])->first();
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
        $info['initial_fee'] = json_decode($info['initial_fee']);
        return $info;
    }

    /**
     * 保存代理设置
     * @param array $info 设置内容
     */
    public function save(array $info)
    {
        if (is_array($info['commision'])) $info['commision'] = json_encode($info['commision']);
        if (is_array($info['initial_fee'])) $info['initial_fee'] = json_encode($info['initial_fee']);
        $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
        if ($info['level'] > 0 && !$sn->hasPermission(Constants::FunctionPermission_ENABLE_AGENT)) {
            throw new Exception("当前商城没有代理功能权限");
        }
        if (key_exists('bonus_mode', $info)) {
            //当分红模式改变时，要对开启了单品设置的商品进行处理，佣金模式转为按系统
            if(intval($info['bonus_mode']) != intval($this->_settingModel->bonus_mode)) {
                //将商品的单品分红设置改为跟随系统
                ProductSkusModel::where('site_id', $this->_siteId)->where('agent_order_commission_rule','>',0)->update(['agent_order_commission_rule' => 0]);
                //删除所有商品关于代理订单分红的单品设置记录
                ProductPriceRuleModel::where(['site_id' => $this->_siteId,'type' => Constants::ProductPriceRuleType_AgentOrderCommision])->delete();
            }
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

    /**
     * 返回当前站点的代理设置，格式化输出
     * @return AgentBaseSettingModel
     */
    public static function getCurrentSiteSettingFormat()
    {
        $setting = self::getCurrentSiteSetting();
        $setting->commision = json_decode($setting->commision, true);
        $setting->initial_fee = json_decode($setting->initial_fee, true);
        return $setting;
    }

    /**
     * 返回当前站点的代理设置加盟信息
     * @return AgentBaseSettingModel
     */
    public function getInitialInfo():array
    {
        $data['need_initial_fee']=$this->_settingModel->need_initial_fee;
        if($this->_settingModel->need_initial_fee){
            $data['initial_fee']=json_decode($this->_settingModel->initial_fee);
            foreach ($data['initial_fee'] as &$item){
                $item=bcdiv($item, 1, 2);
            }
        }
        return $data;
    }
}
