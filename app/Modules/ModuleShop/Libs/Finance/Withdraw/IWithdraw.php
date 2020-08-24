<?php

namespace App\Modules\ModuleShop\Libs\Finance\Withdraw;

/**
 * 前台会员提现工具类接口
 * Interface IWithdraw
 * @package App\Modules\ModuleShop\Libs\Finance\Withdraw
 */
interface IWithdraw
{
    /**
     * 获取可提现的余额
     * @param $financeType 财务类型
     * @param $memberId 会员ID
     * @return mixed
     */
    public function getAvailableBalance($financeType,$memberId);

    /**
     * 获取提现的配置信息
     * @return array
     */
    public function getConfig();

    /**
     * 修改提现配置
     * @param array $info，设置信息，对应 Model 的字段信息
     * @return null
     */
    public function editConfig(array $info);

    /**
     * 获取提现方式的配置，直接读取相应字段，不进行检测
     * @return array
     */
    public function getWithdrawWayConfig();

    /**
     * 获取可用的提现方式
     * @return WithdrawWay
     */
    public function getWithdrawWay() : WithdrawWay;

    /**
     * 提现
     * @return string
     */
    public function withdraw($financeType, $payType, $money, $memberId);
}
