<?php
/**
 * 一次性充值金额升级
 */

namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

use App\Modules\ModuleShop\Libs\Constants;

class ConditionOneReChargeMoney extends AbstractCondition
{
    protected $name = '一次性充值金额满';
	
	/**
     * 构造函数
     * @param int|float $value 充值满多少钱符合此条件,单位:元
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 获取升级条件文案
     * @return mixed|string
     */
    public function getNameText()
    {
        return $this->name . $this->value . $this->unit;
    }

    /**
     * 判断某会员是否满足此条件
     * @param int $memberId 会员id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
		if(is_array($params)) $money = $params['money'] ? $params['money'] : $params[0];
		elseif(is_numeric($params)) $money = $params;
		if($money <= 0) return false;
        return $money >= $this->value;
    }
}