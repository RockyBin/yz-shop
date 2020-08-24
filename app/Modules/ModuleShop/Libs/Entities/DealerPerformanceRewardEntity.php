<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class DealerPerformanceRewardEntity extends BaseEntity
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
	 * 会员经销商主等级（当时） 
	 * @var int $member_dealer_level
	 */
	public $member_dealer_level;

	/**
	 * 会员经销商隐藏等级（当时） 
	 * @var int $member_dealer_hide_level
	 */
	public $member_dealer_hide_level;

	/**
	 * 业绩奖金，单位：分 
	 * @var int $reward_money
	 */
	public $reward_money;

	/**
	 * 贡献业绩统计，单位：分 
	 * @var int $performance_money
	 */
	public $performance_money;

	/**
	 * 总业绩， 单位：分 
	 * @var int $total_performance_money
	 */
	public $total_performance_money;

	/**
	 * 周期 
	 * @var string $period
	 */
	public $period;

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
	const PERFORMANCE_MONEY = 'performance_money';
	const TOTAL_PERFORMANCE_MONEY = 'total_performance_money';
	const PERIOD = 'period';
	const REWARD_ID = 'reward_id';
}
