<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityPropertyEventHandler;
use App\Modules\ModuleShop\Libs\Entities\Traits\DealerOrderRewardEntityTrait;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class DealerOrderRewardEntity extends BaseEntity
{
	/**
	 * 自增主键 
	 * @var int $id
	 */
	public $id;

	/**
	 * 网站Id 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 会员Id 
	 * @var int $member_id
	 */
	public $member_id;

	/**
	 * 会员经销商主等级 
	 * @var int $member_dealer_level
	 */
	public $member_dealer_level;

	/**
	 * 会员经销商隐藏等级 
	 * @var int $member_dealer_hide_level
	 */
	public $member_dealer_hide_level;

	/**
	 * 奖金，单位：分 
	 * @var int $reward_money
	 */
	public $reward_money;

	/**
	 * CloudStock订单Id 
	 * @var int $order_id
	 */
	public $order_id;

	/**
	 * 订单金额，单位：分[%Money%] 
	 * @var int $order_money
	 */
	public $order_money;

	/**
	 * 下单时间 
	 * @var \DataTime $order_created_at
	 */
	public $order_created_at;

	/**
	 * 关联的奖金主表Id 
	 * @var int $reward_id
	 */
	public $reward_id;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const MEMBER_ID = 'member_id';
	const MEMBER_DEALER_LEVEL = 'member_dealer_level';
	const MEMBER_DEALER_HIDE_LEVEL = 'member_dealer_hide_level';
	const REWARD_MONEY = 'reward_money';
	const ORDER_ID = 'order_id';
	const ORDER_MONEY = 'order_money';
	const ORDER_CREATED_AT = 'order_created_at';
	const REWARD_ID = 'reward_id';

    use DealerOrderRewardEntityTrait;
}
