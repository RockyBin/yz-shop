<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use App\Modules\ModuleShop\Libs\Entities\Traits\DealerOrderRewardSettingEntityTrait;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class DealerOrderRewardSettingEntity extends BaseEntity
{
	/**
	 * 网站Id 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 是否开启：0=关闭，1=开启 
	 * @var int $enable
	 */
	public $enable = 0;

	/**
	 * 奖金支付者：0=公司支付 
	 * @var int $payer
	 */
	public $payer = 0;

	/**
	 * 奖金接受者：0=直接对接公司，1=从公司直接拿货的经销商 
	 * @var int $payee
	 */
	public $payee = 0;

	/**
	 * 是否自动审核：0=否，1=是 
	 * @var int $auto_check
	 */
	public $auto_check = 0;

	/**
	 * 规则Json 
	 * @var string $reward_rule
	 */
	public $reward_rule;

	const SITE_ID = 'site_id';
	const ENABLE = 'enable';
	const PAYER = 'payer';
	const PAYEE = 'payee';
	const AUTO_CHECK = 'auto_check';
	const REWARD_RULE = 'reward_rule';

	use DealerOrderRewardSettingEntityTrait;
}
