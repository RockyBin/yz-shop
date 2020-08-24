<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;

/**
 * 无需条件成为分销商
 * Class BecomeDistributorNoCondition
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
class BecomeDistributorNoCondition extends AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_None;

    /**
     * 实例化
     * BecomeDistributorNoCondition constructor.
     * @param $memberModal
     * @param DistributionSetting|null $distributionSetting
     */
    public function __construct($memberModal, DistributionSetting $distributionSetting = null)
    {
        parent::__construct($memberModal, $distributionSetting);
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 无条件
        return true;
    }
}