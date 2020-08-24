<?php
/**
 * 一次性充值金额
 * User: liyaohui
 * Date: 2020/2/5
 * Time: 13:56
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


use YZ\Core\Constants;
use YZ\Core\Model\FinanceModel;

class ConditionOneRechargeMoney extends abstractCondition
{
    protected $name = '一次性充值金额满';
    protected $unit = '元';

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 判断某经销商是否满足此条件
     * @param int $memberId 经销商会员id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if(is_array($params)) $money = $params['money'] ? $params['money'] : $params[0];
        elseif(is_numeric($params)) $money = $params;
        if($money <= 0) return false;
        return $money >= moneyYuan2Cent($this->value);
    }
}