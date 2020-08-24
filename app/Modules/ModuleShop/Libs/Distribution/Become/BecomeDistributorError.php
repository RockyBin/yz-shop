<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;

/**
 * 无需条件成为分销商
 * Class BecomeDistributorError
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
class BecomeDistributorError extends AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_Error;

    /**
     * 实例化
     * BecomeDistributorError constructor.
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
        $this->errorMsg = 'Error Condition Type';
        // 无条件
        return false;
    }

    /**
     * 不允许申请
     * @return bool
     */
    public function apply()
    {
        $this->errorMsg = 'Error Condition Type';
        return false;
    }

    /**
     * 不允许生效
     * @return bool
     */
    public function Active()
    {
        $this->errorMsg = 'Error Condition Type';
        return false;
    }
}