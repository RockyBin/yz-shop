<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use  App\Modules\ModuleShop\Libs\Distribution\Distributor;
use YZ\Core\Logger\Log;

/**
 * 自身代理等级的分销商等级升级条件
 */
class UpgradeConditionSelfDistributionLevel extends abstractCondition
{
    protected $value = '';
    protected $name = "自身分销等级";

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getTitle()
    {
        $levelText = DistributionLevel::getLevelName($this->value);
        return '自身分销等级为 ' .$levelText  ;
    }

    /**
     * 判断是否满足此代理升级条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->checkIsAgent($params)) {
            return false;
        }
        $distributor = (new Distributor($memberId))->getModel();
        // 增加判断分销商状态是否生效
        if ($distributor && $distributor->status == Constants::DistributorStatus_Active) {
            $distributorLevel = $distributor->level;
            if ($this->value) {
                //因为等级有可能有多个;
                $value = explode(',', $this->value);
                foreach ($value as $item) {
                    if ($item && $distributorLevel == $item) {
                        return true;
                        break;
                    }
                }
            }
        }
        return false;
    }
}
