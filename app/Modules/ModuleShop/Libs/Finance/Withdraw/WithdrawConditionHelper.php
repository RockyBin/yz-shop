<?php

namespace App\Modules\ModuleShop\Libs\Finance\Withdraw;


use YZ\Core\Constants;

/**
 * 提现静态工具类
 *
 * @author wenke
 */
class WithdrawConditionHelper
{

    /**
     * 根据提现类型生成相应对象
     * @param int $conditionType 条件类型
     */
    public static function createInstance($FinanceType)
    {
        $iType = intval($FinanceType);
        switch ($iType) {
            // 云仓经销商提现
            case Constants::FinanceType_CloudStock:
                $instance = new DealerWithdraw();
                break;
            // 供应商提现
            case Constants::FinanceType_Supplier:
                $instance = new SupplierWithdraw();
                break;
            // 余额提现 分销商佣金提现 代理分红提现
            default:
                $instance = new CommonWithdraw();
        }
        return $instance;
    }
}
