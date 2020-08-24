<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use App\Modules\ModuleShop\Libs\Entities\Traits\DealerSaleRewardEntityTrait;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class DealerSaleRewardEntity extends BaseEntity
{
	/**
	 * 自增主键 
	 * @var int $id
	 */
	public $id;

	/**
	 * 网站ID 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 会员id 
	 * @var int $member_id
	 */
	public $member_id;

	/**
	 * 会员经销商等级（当时） 
	 * @var int $member_dealer_level
	 */
	public $member_dealer_level;

	/**
	 * 经销商隐藏等级（当时） 
	 * @var int $member_dealer_hide_level
	 */
	public $member_dealer_hide_level;

	/**
	 * 销售奖金，单位：分 
	 * @var int $reward_money
	 */
	public $reward_money;

	/**
	 * 订单金额 单位 分 
	 * @var int $order_money
	 */
	public $order_money;

	/**
	 * 订单id 
	 * @var string $order_id
	 */
	public $order_id;

	/**
	 * 下级会员id 
	 * @var int $sub_member_id
	 */
	public $sub_member_id;

	/**
	 * 下级会员当时的经销商等级 
	 * @var int $sub_member_dealer_level
	 */
	public $sub_member_dealer_level;

	/**
	 * 下级会员当时的经销商隐藏等级 
	 * @var int $sub_member_dealer_hide_level
	 */
	public $sub_member_dealer_hide_level;

	/**
	 * 是平级奖还是越级奖0=平级 1=越级 
	 * @var int $reward_type
	 */
	public $reward_type;

	/**
	 * 下单时间 
	 * @var \DataTime $order_created_at
	 */
	public $order_created_at;

	/**
	 * 关联的奖金主表id 
	 * @var int $reward_id
	 */
	public $reward_id;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const MEMBER_ID = 'member_id';
	const MEMBER_DEALER_LEVEL = 'member_dealer_level';
	const MEMBER_DEALER_HIDE_LEVEL = 'member_dealer_hide_level';
	const REWARD_MONEY = 'reward_money';
	const ORDER_MONEY = 'order_money';
	const ORDER_ID = 'order_id';
	const SUB_MEMBER_ID = 'sub_member_id';
	const SUB_MEMBER_DEALER_LEVEL = 'sub_member_dealer_level';
	const SUB_MEMBER_DEALER_HIDE_LEVEL = 'sub_member_dealer_hide_level';
	const REWARD_TYPE = 'reward_type';
	const ORDER_CREATED_AT = 'order_created_at';
	const REWARD_ID = 'reward_id';

	use DealerSaleRewardEntityTrait;
}
