<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class DealerRecommendRewardEntity extends BaseEntity
{
	/**
	 * 自增主键 
	 * @var int $id
	 */
	public $id;

	/**
	 * 网站id 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 推荐者会员id 
	 * @var int $member_id
	 */
	public $member_id;

	/**
	 * 推荐者经销商等级（当时） 
	 * @var int $member_dealer_level
	 */
	public $member_dealer_level;

	/**
	 * 推荐者经销商隐藏等级（当时） 
	 * @var int $member_dealer_hide_level
	 */
	public $member_dealer_hide_level;

	/**
	 * 被推荐者会员id 
	 * @var int $sub_member_id
	 */
	public $sub_member_id;

	/**
	 * 被推荐者经销商等级（当时） 
	 * @var int $sub_member_dealer_level
	 */
	public $sub_member_dealer_level;

	/**
	 * 被推荐者经销商隐藏等级（当时） 
	 * @var int $sub_member_dealer_hide_level
	 */
	public $sub_member_dealer_hide_level;

	/**
	 * 推荐奖金，单位：分 
	 * @var int $reward_money
	 */
	public $reward_money;

	/**
	 * 拒绝理由 
	 * @var string $reason
	 */
	public $reason;

	/**
	 * 0=下级奖 1=平级奖 2=越级奖 
	 * @var int $reward_type
	 */
	public $reward_type;

	/**
	 * 关联的奖金表id 
	 * @var int $reward_id
	 */
	public $reward_id;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const MEMBER_ID = 'member_id';
	const MEMBER_DEALER_LEVEL = 'member_dealer_level';
	const MEMBER_DEALER_HIDE_LEVEL = 'member_dealer_hide_level';
	const SUB_MEMBER_ID = 'sub_member_id';
	const SUB_MEMBER_DEALER_LEVEL = 'sub_member_dealer_level';
	const SUB_MEMBER_DEALER_HIDE_LEVEL = 'sub_member_dealer_hide_level';
	const REWARD_MONEY = 'reward_money';
	const REASON = 'reason';
	const REWARD_TYPE = 'reward_type';
	const REWARD_ID = 'reward_id';
}
