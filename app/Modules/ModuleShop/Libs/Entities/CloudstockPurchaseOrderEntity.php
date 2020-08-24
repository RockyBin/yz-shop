<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use App\Modules\ModuleShop\Libs\Entities\Traits\CloudstockPurchaseOrderEntityTrait;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class CloudstockPurchaseOrderEntity extends BaseEntity
{
	/**
	 * @var string $id
	 */
	public $id;

	/**
	 * 所属网站 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 所属店铺 
	 * @var int $store_id
	 */
	public $store_id;

	/**
	 * 所属会员 
	 * @var int $member_id
	 */
	public $member_id;

	/**
	 * 订单状态0=未支付， 1=已支付待审核， 2=已审核 待配仓， 3=完成， 4=取消 
	 * @var int $status
	 */
	public $status;

	/**
	 * 取消订单的原因 
	 * @var string $cancel_reason
	 */
	public $cancel_reason;

	/**
	 * 产品总金额（单位 分） 
	 * @var int $total_money
	 */
	public $total_money;

	/**
	 * 下单时间 
	 * @var \DataTime $created_at
	 */
	public $created_at;

	/**
	 * 更新时间 
	 * @var \DataTime $updated_at
	 */
	public $updated_at;

	/**
	 * 支付时间 
	 * @var \DataTime $pay_at
	 */
	public $pay_at;

	/**
	 * 订单完成时间 
	 * @var \DataTime $finished_at
	 */
	public $finished_at;

	/**
	 * 备注 
	 * @var string $remark
	 */
	public $remark;

	/**
	 * 内部备注 
	 * @var string $remark_inside
	 */
	public $remark_inside;

	/**
	 * 支付类型 
	 * @var int $pay_type
	 */
	public $pay_type;

	/**
	 * 收款人ID，0=平台收款，非0表示上级收款，此时记录上级会员ID 
	 * @var int $payee
	 */
	public $payee;

	/**
	 * 支付流水号 
	 * @var string $transaction_id
	 */
	public $transaction_id;

	/**
	 * 支付审核状态 0=未审核 1=审核通过 2=审核不通过 
	 * @var int $payment_status
	 */
	public $payment_status;

	/**
	 * 审核失败原因 
	 * @var string $refuse_remark
	 */
	public $refuse_remark;

	/**
	 * 支付凭证 
	 * @var string $payment_voucher
	 */
	public $payment_voucher;

	/**
	 * 收款账号信息 json  
	 * @var string $receipt_info
	 */
	public $receipt_info;

	/**
	 * 进货的云仓id 0为 总仓(目前认为进货单都一定从直接上级拿货，不存在一个订单N个出货仓这种情况) 
	 * @var int $cloudstock_id
	 */
	public $cloudstock_id;

	/**
	 * 配仓状态 
	 * @var int $stock_status
	 */
	public $stock_status;

	/**
	 * 订单流程 json数组 
	 * @var string $order_log
	 */
	public $order_log;

	/**
	 * 审核记录ID 
	 * @var int $verify_log_id
	 */
	public $verify_log_id;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const STORE_ID = 'store_id';
	const MEMBER_ID = 'member_id';
	const STATUS = 'status';
	const CANCEL_REASON = 'cancel_reason';
	const TOTAL_MONEY = 'total_money';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const PAY_AT = 'pay_at';
	const FINISHED_AT = 'finished_at';
	const REMARK = 'remark';
	const REMARK_INSIDE = 'remark_inside';
	const PAY_TYPE = 'pay_type';
	const PAYEE = 'payee';
	const TRANSACTION_ID = 'transaction_id';
	const PAYMENT_STATUS = 'payment_status';
	const REFUSE_REMARK = 'refuse_remark';
	const PAYMENT_VOUCHER = 'payment_voucher';
	const RECEIPT_INFO = 'receipt_info';
	const CLOUDSTOCK_ID = 'cloudstock_id';
	const STOCK_STATUS = 'stock_status';
	const ORDER_LOG = 'order_log';
	const VERIFY_LOG_ID = 'verify_log_id';

	use CloudstockPurchaseOrderEntityTrait;
}
