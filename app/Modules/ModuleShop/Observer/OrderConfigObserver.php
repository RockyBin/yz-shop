<?php

namespace App\Modules\ModuleShop\Observer;

use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentBaseSettingModel;
use App\Modules\ModuleShop\Libs\Model\OrderConfigModel;
use YZ\Core\Logger\Log;
use App\Modules\ModuleShop\Libs\Model\DistributionSettingModel;
use App\Modules\ModuleShop\Libs\Model\MemberConfigModel;
use App\Modules\ModuleShop\Libs\Model\AgentUpgradeSettingModel;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceRewardSettingModel;
use App\Modules\ModuleShop\Libs\Model\AgentBaseSettingModel;
use YZ\Core\Site\Site;

/**
 * 订单设置观察者模型
 */
class OrderConfigObserver
{

    /**
     * 监听数据更新后的事件。
     *
     * @param  OrderConfigModel $OrderConfigModel
     * @return void
     */
    public function updated(OrderConfigModel $OrderConfigModel)
    {
        if ($OrderConfigModel->aftersale_isopen == 0) {
            $this->closeCalcValidCondition();
        }
        Log::writeLog('OrderConfigObserver', ' ' . $OrderConfigModel->aftersale_isopen);
    }

    /**
     * 当订单设置关闭售后的时候，要同步关闭分销商设置,会员等级,代理等级，代理业绩奖励,代理基础设置的计算条件
     * 基本涵盖全站的计算条件,若想关闭全站的计算条件，调取此方法即可
     * 包含了（分销商设置,会员等级,代理等级，代理业绩奖励,代理基础设置）
     */
    public function closeCalcValidCondition()
    {
        try {
            //分销商设置
            (new DistributionSettingModel())->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update(['calc_valid_condition' => 0, 'calc_performance_valid_condition' => 0, 'calc_commission_valid_condition' => 0, 'calc_upgrade_valid_condition' => 0, 'calc_apply_valid_condition' => 0]);
            //会员等级
            (new MemberConfigModel())->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update(['level_upgrade_period' => 0]);
            //代理等级
            (new AgentUpgradeSettingModel())->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update(['order_valid_condition' => 0]);
            //代理业绩奖励
            (new AgentPerformanceRewardSettingModel())->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update(['count_period' => 0]);
            //代理基础设置
            (new AgentBaseSettingModel())->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update(['commision_grant_time' => 0]);
            //区域代理基础设置
            (new AreaAgentBaseSettingModel())->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update(['commision_grant_time' => 0]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
