<?php
/**
 * 总充值金额
 * User: liyaohui
 * Date: 2020/2/5
 * Time: 13:56
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


use YZ\Core\Constants;
use YZ\Core\Model\FinanceModel;

class ConditionTotalRechargeMoney extends abstractCondition
{
    protected $name = '累计充值金额满';
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
        $in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->whereIn('type', [Constants::FinanceType_Normal])
            ->whereIn('in_type', [Constants::FinanceInType_Recharge, Constants::FinanceInType_Manual, Constants::FinanceInType_Give])
            ->where('money', '>', 0)
            ->sum('money');
        return $in >= moneyYuan2Cent($this->value);
    }
}