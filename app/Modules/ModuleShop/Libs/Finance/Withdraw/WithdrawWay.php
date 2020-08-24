<?php

namespace App\Modules\ModuleShop\Libs\Finance\Withdraw;

/**
 * 可用的提现方式
 * Class WithdrawWay
 * @package App\Modules\ModuleShop\Libs\Finance\Withdraw
 */
class WithdrawWay
{
    /**
     * 是否可以提现到余额
     * @var int
     */
    public $balance = 0;

    /**
     * 是否可以提现到微信(在线)
     * @var int
     */
    public $wxpay = 0;

    /**
     * 是否可以提现到支付宝(在线)
     * @var int
     */
    public $alipay = 0;

    /**
     * 是否可以用微信收款码提现（线下）
     * @var int
     */
    public $wxQrcode = 0;

    /**
     * 是否可以用支付宝收款码提现（线下）
     * @var int
     */
    public $alipayQrcode = 0;

    /**
     * 是否可以用支付宝帐号提现（线下）
     * @var int
     */
    public $alipayAccount = 0;

    /**
     * 是否可以用银行帐号提现（线下）
     * @var int
     */
    public $bankAccount = 0;
}
