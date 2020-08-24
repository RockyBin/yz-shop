<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;

/**
 * 达到消费笔数成为分销商
 * Class BecomeDistributorBuyTime
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
class BecomeDistributorBuyTimes extends AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_BuyTimes;

    /**
     * 实例化
     * BecomeDistributorBuyTime constructor.
     * @param $memberModal
     * @param DistributionSetting|null $distributionSetting
     */
    public function __construct($memberModal, DistributionSetting $distributionSetting = null)
    {
        parent::__construct($memberModal, $distributionSetting);
        $this->setExtendData([
            //现在分销商的所有计算方式统一使用calc_valid_condition
                'apply_condition_buy_times_flag' => $this->setting->calc_apply_valid_condition
        ]);
        $this->periodFlag = intval($distributionSetting->getSettingModel()->calc_apply_valid_condition);
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 读取数据
        $memberModel = $this->member->getInfo(true);
        $total_consume_times = $this->setting->calc_apply_valid_condition ? intval($memberModel->deal_times) : intval($memberModel->buy_times);
        $config_consume_times = intval($this->setting->buy_times);
        // 计算结果
        $result = $total_consume_times >= $config_consume_times;
        // 还需要多少次数
        $remain = $total_consume_times >= $config_consume_times ? 0 : $config_consume_times - $total_consume_times;
        $this->setExtendData([
            'times_remain' => $remain,
            'times_need' => $config_consume_times,
        ]);
        if (!$result) {
            $this->errorMsg = str_replace('#times#', $config_consume_times, trans('shop-front.distributor.buy_times_not_enough'));
        }
        return $result;
    }
}