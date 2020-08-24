<?php
namespace YZ\Core\Payment;

/**
 * 在线退款结果封装，方便外层统一数据格式
 * Class RefundResult
 * @package YZ\Core\Payment
 */
class RefundResult
{
    /**
     * 是否支付成功
     * @var bool
     */
    public $success = false;

    /**
     * 对端的原来付款时的唯一交易号
     * @var string
     */
    public $tradeno = '';

    /**
     * 本系统的原来付款时的相关订单号
     * @var string
     */
    public $orderid = '';

    /**
     * 本系统的退款单号
     * @var string
     */
    public $refundNo = '';

    /**
     * 对端的退款交易号
     * @var string
     */
    public $refundTradeNo = '';

    /**
     * 退款金额：单位 分
     * @var int
     */
    public $amount = 0;

    /**
     * 支付方式，跟 Constants::PayType_XXX
     * @var int
     */
    public $paytype = 0;

    /**
     * 额外的信息
     * @var array|null
     */
    public $extInfo = [];

    /**
     * 支付接口返回的信息，主要是给日志用
     * @var array|null
     */
    public $apidata = '';
}