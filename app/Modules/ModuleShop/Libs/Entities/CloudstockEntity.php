<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class CloudstockEntity extends BaseEntity
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
	 * 云仓类型：0=团代云仓，1=区域代理云仓 
	 * @var int $type
	 */
	public $type;

	/**
	 * 状态：0=禁用，1=启用 
	 * @var int $status
	 */
	public $status;

	/**
	 * 建立时间 
	 * @var \DataTime $created_at
	 */
	public $created_at;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const MEMBER_ID = 'member_id';
	const TYPE = 'type';
	const STATUS = 'status';
	const CREATED_AT = 'created_at';
}
