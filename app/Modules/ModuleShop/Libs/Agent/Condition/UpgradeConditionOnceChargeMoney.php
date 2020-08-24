<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;


use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;

/**
 * 自购订单金额等级升级条件
 */
class UpgradeConditionOnceChargeMoney extends abstractCondition
{
    protected $value = '';
    protected $name = "一次性充值金额满";
    public $unit = '元';

    public function __construct($value)
    {
        $this->value = $value;
    }



    /**
     * 判断是否满足此代理升级条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (is_array($params)) $money = $params['money'] ? $params['money'] : $params[0];
        elseif (is_numeric($params)) $money = $params;
        if ($money <= 0) return false;
        return $money >= $this->value ;
    }
}
