<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;
use YZ\Core\Model\MemberModel;

class DealerLevelEntity extends BaseEntity
{
	/**
	 * 主键 
	 * @var int $id
	 */
	public $id;

	/**
	 * 网站ID 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 等级名称 
	 * @var string $name
	 */
	public $name;

	/**
	 * 等级权重 
	 * @var int $weight
	 */
	public $weight;

	/**
	 * 是否启用 
	 * @var int $status
	 */
	public $status;

	/**
	 * 是否开启隐藏等级 
	 * @var int $has_hide
	 */
	public $has_hide;

	/**
	 * 当是隐藏等级时，记录基本等级的ID，否则表示此等级为基本等级 
	 * @var int $parent_id
	 */
	public $parent_id;

	/**
	 * 最小提货量 
	 * @var int $min_take_delivery_num
	 */
	public $min_take_delivery_num;

	/**
	 * 加盟费 单位分 
	 * @var int $initial_fee
	 */
	public $initial_fee;

	/**
	 * 复购最小进货量  
	 * @var int $min_purchase_num
	 */
	public $min_purchase_num;

	/**
	 * 首购最小进货量  
	 * @var int $min_purchase_num_first
	 */
	public $min_purchase_num_first;

	/**
	 * 复购最小金额 单位分 
	 * @var int $min_purchase_money
	 */
	public $min_purchase_money;

	/**
	 * 首购最小金额 单位分 
	 * @var int $min_purchase_money_first
	 */
	public $min_purchase_money_first;

	/**
	 * 云仓订单折扣百分比 
	 * @var double $discount
	 */
	public $discount;

	/**
	 * 升级条件，格式为JSON数组 
	 * @var string $upgrade_condition
	 */
	public $upgrade_condition;

	/**
	 * 是否允许会员自动升级到此等级0=否 1=是 
	 * @var int $auto_upgrade
	 */
	public $auto_upgrade;

	const ID = "id";
	const SITE_ID = "site_id";
	const NAME = "name";
	const WEIGHT = "weight";
	const STATUS = "status";
	const HAS_HIDE = "has_hide";
	const PARENT_ID = "parent_id";
	const MIN_TAKE_DELIVERY_NUM = "min_take_delivery_num";
	const INITIAL_FEE = "initial_fee";
	const MIN_PURCHASE_NUM = "min_purchase_num";
	const MIN_PURCHASE_NUM_FIRST = "min_purchase_num_first";
	const MIN_PURCHASE_MONEY = "min_purchase_money";
	const MIN_PURCHASE_MONEY_FIRST = "min_purchase_money_first";
	const DISCOUNT = "discount";
	const UPGRADE_CONDITION = "upgrade_condition";
	const AUTO_UPGRADE = "auto_upgrade";
}
