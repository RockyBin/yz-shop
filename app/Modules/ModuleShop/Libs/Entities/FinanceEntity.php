<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class FinanceEntity extends BaseEntity
{
	/**
	 * 主键 
	 * @var int $id
	 */
	public $id;

	/**
	 * 所属网站 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 所属会员 
	 * @var int $member_id
	 */
	public $member_id;

	/**
	 * 订单类型，1=零售订单，2=云仓进货单，6=代理加盟费，7=经销商加盟费 
	 * @var int $order_type
	 */
	public $order_type;

	/**
	 * 相关的订单号 
	 * @var string $order_id
	 */
	public $order_id;

	/**
	 * 类型：0=普通财务，1=赠金，2=流转账目（用于直接支付和退款）5=云仓货款（记录向平台进货时收到的货款）6=代理商加盟费7=云仓货款（用于向云仓结算的货款 下级进货或者零售时使用）9=佣金8=团代分红 
	 * @var int $type
	 */
	public $type;

	/**
	 * 财务类型子类型 
	 * @var $sub_type
	 */
	public $sub_type;

	/**
	 * 是否为订单退款时的佣金扣减，方便后面用来做统计和过滤 
	 * @var int $is_commision_refund
	 */
	public $is_commision_refund;

	/**
	 * 支付方式：1=余额，2=微信，3=支付宝，4=Paypal，99=佣金 
	 * @var int $pay_type
	 */
	public $pay_type;

	/**
	 * 当方向为支出时，记录支出的类型：1=购物，2=提现手续费（作废），3=冲帐，4=退款到外部，5=提现到外部，98=分销佣金转余额， 
	 * @var int $out_type
	 */
	public $out_type;

	/**
	 * 当方向为入帐时，记录拼入帐的类型：1=充值，2=冲帐，3=商品退款，4=支付（第三方直接支付），98=分销佣金转余额，99=佣金收入 
	 * @var int $in_type
	 */
	public $in_type;

	/**
	 * 财务标志：1=表示此财务是真正的有资金往来，比如微信、支付宝，现金等方式的充值、提现等，0=表示没有真正的资金出入，如余额交费，佣金转余额，提现手续费等 
	 * @var int $is_real
	 */
	public $is_real;

	/**
	 * 状态：1=正常，0=冻结，2=作废 
	 * @var int $status
	 */
	public $status;

	/**
	 * 交易号(主要是记录在微信,支付宝等平台的交易号) 
	 * @var string $tradeno
	 */
	public $tradeno;

	/**
	 * 操作员(SYS=系统入帐,否则记录网站管理员的ID) 
	 * @var string $operator
	 */
	public $operator;

	/**
	 * 终端来源(参考核心代码的常量表Constants) 
	 * @var int $terminal_type
	 */
	public $terminal_type;

	/**
	 * 金额（单位：分） 
	 * @var int $money
	 */
	public $money;

	/**
	 * 手续费（单位：分） 
	 * @var int $money_fee
	 */
	public $money_fee;

	/**
	 * 实际金额（金额 - 手续费，单位：分） 
	 * @var int $money_real
	 */
	public $money_real;

	/**
	 * 时间 
	 * @var \DataTime $created_at
	 */
	public $created_at;

	/**
	 * 生效时间 
	 * @var \DataTime $active_at
	 */
	public $active_at;

	/**
	 * 拒绝时间 
	 * @var \DataTime $invalid_at
	 */
	public $invalid_at;

	/**
	 * 备注 
	 * @var string $about
	 */
	public $about;

	/**
	 * 拒绝原因 
	 * @var string $reason
	 */
	public $reason;

	/**
	 * 快照(格式json字符串) 
	 * @var string $snapshot
	 */
	public $snapshot;

	/**
	 * 佣金专用，来自哪个一级推荐人 
	 * @var int $from_member1
	 */
	public $from_member1;

	/**
	 * 佣金专用，来自哪个二级推荐人 
	 * @var int $from_member2
	 */
	public $from_member2;

	/**
	 * 佣金专用，来自哪个三级推荐人 
	 * @var int $from_member3
	 */
	public $from_member3;

	/**
	 * 佣金专用，来自哪个四级推荐人 
	 * @var int $from_member4
	 */
	public $from_member4;

	/**
	 * 佣金专用，来自哪个五级推荐人 
	 * @var int $from_member5
	 */
	public $from_member5;

	/**
	 * 佣金专用，来自哪个六级推荐人 
	 * @var int $from_member6
	 */
	public $from_member6;

	/**
	 * 佣金专用，来自哪个七级推荐人 
	 * @var int $from_member7
	 */
	public $from_member7;

	/**
	 * 佣金专用，来自哪个八级推荐人 
	 * @var int $from_member8
	 */
	public $from_member8;

	/**
	 * 佣金专用，来自哪个九级推荐人 
	 * @var int $from_member9
	 */
	public $from_member9;

	/**
	 * 佣金专用，来自哪个十级推荐人 
	 * @var int $from_member10
	 */
	public $from_member10;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const MEMBER_ID = 'member_id';
	const ORDER_TYPE = 'order_type';
	const ORDER_ID = 'order_id';
	const TYPE = 'type';
	const SUB_TYPE = 'sub_type';
	const IS_COMMISION_REFUND = 'is_commision_refund';
	const PAY_TYPE = 'pay_type';
	const OUT_TYPE = 'out_type';
	const IN_TYPE = 'in_type';
	const IS_REAL = 'is_real';
	const STATUS = 'status';
	const TRADENO = 'tradeno';
	const OPERATOR = 'operator';
	const TERMINAL_TYPE = 'terminal_type';
	const MONEY = 'money';
	const MONEY_FEE = 'money_fee';
	const MONEY_REAL = 'money_real';
	const CREATED_AT = 'created_at';
	const ACTIVE_AT = 'active_at';
	const INVALID_AT = 'invalid_at';
	const ABOUT = 'about';
	const REASON = 'reason';
	const SNAPSHOT = 'snapshot';
	const FROM_MEMBER1 = 'from_member1';
	const FROM_MEMBER2 = 'from_member2';
	const FROM_MEMBER3 = 'from_member3';
	const FROM_MEMBER4 = 'from_member4';
	const FROM_MEMBER5 = 'from_member5';
	const FROM_MEMBER6 = 'from_member6';
	const FROM_MEMBER7 = 'from_member7';
	const FROM_MEMBER8 = 'from_member8';
	const FROM_MEMBER9 = 'from_member9';
	const FROM_MEMBER10 = 'from_member10';
}
