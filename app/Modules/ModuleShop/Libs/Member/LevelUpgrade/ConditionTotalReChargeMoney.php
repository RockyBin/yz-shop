<?php
/**
 * 累计充值金额升级
 */

namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Model\FinanceModel;

class ConditionTotalReChargeMoney extends AbstractCondition
{
    protected $name = '累计充值金额满';
    protected $unit = '元';

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
		$in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', \YZ\Core\Constants::FinanceStatus_Active)
            ->whereIn('type', [\YZ\Core\Constants::FinanceType_Normal])
            ->whereIn('in_type', [\YZ\Core\Constants::FinanceInType_Recharge,\YZ\Core\Constants::FinanceInType_Manual,\YZ\Core\Constants::FinanceInType_Give])
            ->where('money', '>', 0)
            ->sum('money');
		return $in >= moneyYuan2Cent($this->value);
    }
}