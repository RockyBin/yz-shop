<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use App\Modules\ModuleShop\Libs\Entities\Traits\DealerRewardEntityTrait;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class DealerRewardEntity extends BaseEntity
{
	/**
	 * @var int $id
	 */
	public $id;

	/**
	 * 站点id 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 会员id 
	 * @var int $member_id
	 */
	public $member_id;

	/**
	 * 奖金类型 1=业绩奖 2=推荐奖 3=销售奖 
	 * @var int $type
	 */
	public $type;

	/**
	 * 状态 0=待兑换 1=待审核 2=已发放 3=已拒绝 
	 * @var int $status
	 */
	public $status;

	/**
	 * 奖金金额 单位 分 
	 * @var int $reward_money
	 */
	public $reward_money;

	/**
	 * 支付奖金人id 为0则是公司支付 
	 * @var int $pay_member_id
	 */
	public $pay_member_id;

	/**
	 * 拒绝原因 
	 * @var string $reason
	 */
	public $reason;

	/**
	 * 缓存的奖金信息json 
	 * @var string $about
	 */
	public $about;

	/**
	 * 创建时间 
	 * @var \DataTime $created_at
	 */
	public $created_at;

	/**
	 * 更新时间 
	 * @var \DataTime $updated_at
	 */
	public $updated_at;

	/**
	 * 审核时间 
	 * @var \DataTime $verify_at
	 */
	public $verify_at;

	/**
	 * 兑换申请时间 
	 * @var \DataTime $exchange_at
	 */
	public $exchange_at;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const MEMBER_ID = 'member_id';
	const TYPE = 'type';
	const STATUS = 'status';
	const REWARD_MONEY = 'reward_money';
	const PAY_MEMBER_ID = 'pay_member_id';
	const REASON = 'reason';
	const ABOUT = 'about';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const VERIFY_AT = 'verify_at';
	const EXCHANGE_AT = 'exchange_at';

    use DealerRewardEntityTrait;
}
