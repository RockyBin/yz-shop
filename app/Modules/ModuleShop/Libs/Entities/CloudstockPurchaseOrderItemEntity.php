<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class CloudstockPurchaseOrderItemEntity extends BaseEntity
{
	/**
	 * @var int $id
	 */
	public $id;

	/**
	 * 所属网站 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 所属订单 
	 * @var string $order_id
	 */
	public $order_id;

	/**
	 * 产品ID 
	 * @var int $product_id
	 */
	public $product_id;

	/**
	 * 规格ID 
	 * @var int $sku_id
	 */
	public $sku_id;

	/**
	 * 商品名称 
	 * @var string $name
	 */
	public $name;

	/**
	 * 小图的路径 
	 * @var string $image
	 */
	public $image;

	/**
	 * 规格名称 json数组 
	 * @var string $sku_names
	 */
	public $sku_names;

	/**
	 * 数量 
	 * @var int $num
	 */
	public $num;

	/**
	 * 订货单价（单位：分） 
	 * @var int $price
	 */
	public $price;

	/**
	 * 进货的云仓id 0为 总仓 
	 * @var int $cloudstock_id
	 */
	public $cloudstock_id;

	/**
	 * 订货总价 
	 * @var int $money
	 */
	public $money;

	/**
	 * 配仓状态0=未配仓 1=已配仓 
	 * @var int $stock_status
	 */
	public $stock_status;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const ORDER_ID = 'order_id';
	const PRODUCT_ID = 'product_id';
	const SKU_ID = 'sku_id';
	const NAME = 'name';
	const IMAGE = 'image';
	const SKU_NAMES = 'sku_names';
	const NUM = 'num';
	const PRICE = 'price';
	const CLOUDSTOCK_ID = 'cloudstock_id';
	const MONEY = 'money';
	const STOCK_STATUS = 'stock_status';
}
