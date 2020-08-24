<?php

namespace App\Modules\ModuleShop\Libs\AreaAgent\Condition;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use  App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use YZ\Core\Logger\Log;

/**
 * 自身分销等级条件
 */
class BecomeConditionSelfDistributionLevel extends abstractCondition
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
        return '自身分销等级为 ' . $levelText;
    }

    /**
     * 判断是否满足此条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $distributor = (new Distributor($memberId))->getModel();
        // 增加判断分销商状态是否生效
        if ($distributor && $distributor->status == Constants::DistributorStatus_Active) {
            $distributorLevel = $distributor->level;
            // 需要判断等级是否生效
            $level = DistributionLevelModel::query()
                ->where('id', $distributorLevel)
                ->where('status', 1)
                ->count();
            if($level <=0) return false ;
            if ($this->value) {
                //因为等级有可能有多个;
                $value = myToArray($this->value);
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
