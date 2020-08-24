<?php

namespace App\Modules\ModuleShop\Libs\Finance\Withdraw;

use YZ\Core\Constants;

/**
 * 提现静态工具类
 *
 * @author wenke
 */
class WithdrawFactory
{
    /**
     * 根据提现类型生成相应对象
     * @param int $conditionType 条件类型
     */
    public static function createInstance($FinanceType) : IWithdraw
    {
        $iType = intval($FinanceType);
        switch ($iType) {
            case Constants::FinanceType_CloudStock:
                $instance = new DealerWithdraw();
                break;
            case Constants::FinanceType_Normal:
                $instance = new BalanceWithdraw();
                break;
            case Constants::FinanceType_Supplier:
                $instance = new SupplierWithdraw();
                break;
            default: //分销 代理 区代佣金
                $instance = new CommissionWithdraw();
        }
        return $instance;
    }
}
