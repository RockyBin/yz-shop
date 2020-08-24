<?php
namespace YZ\Core\Payment;

/**
 * 在线支付结果封装，方便外层统一数据格式
 * Class PaymentResult
 * @package YZ\Core\Payment
 */
class PaymentResult
{
    /**
     * 是否支付成功
     * @var bool
     */
    public $success = false;

    /**
     * 对端的唯一交易号
     * @var string
     */
    public $tradeno = '';

    /**
     * 本系统的相关订单号
     * @var string
     */
    public $orderid = '';

    /**
     * 金额：单位 分
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

    public function __construct($success,$tradeno,$orderid,$amount,$paytype,$extInfo = null,$apidata = null)
    {
        $this->success = $success;
        $this->tradeno = $tradeno;
        $this->orderid = $orderid;
        $this->amount = $amount;
        $this->paytype = $paytype;
        if($extInfo) $this->extInfo = $extInfo;
        if($apidata) $this->apidata = $apidata;
    }
}