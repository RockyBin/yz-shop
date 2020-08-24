<?php
/**
 * 累计交易金额
 */

namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberConfig;

class ConditionTotalOrderMoney extends AbstractCondition
{
    protected $name = '累计交易金额满';
    protected $unit = '元';

    /**
     * 构造函数
     * @param int|float $value 累计交易金额满多少钱符合此条件,单位:元
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 判断某会员是否满足此条件
     * @param int $memberId 会员id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $memberConfig = new MemberConfig();
        $countPeriod = intval($memberConfig->getConfig()->level_upgrade_period);
        $member = new Member($memberId,getCurrentSiteId(),false);
        // 获取订单金额
        $orderMoney = 0;
        if ($countPeriod == Constants::Period_OrderPay) {
            $orderMoney = intval($member->getModel()->buy_money);
        } else if ($countPeriod == Constants::Period_OrderFinish) {
            $orderMoney = intval($member->getModel()->deal_money);
        }
        if ($orderMoney <= 0) {
            return false;
        }
        return $orderMoney >= moneyYuan2Cent($this->value);
    }
}