<?php
/**
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Libs\Model;

use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\QueryParameters\DealerOrderRewardQueryParameter;
use App\Modules\ModuleShop\Libs\Entities\QueryParameters\MemberQueryParameter;
use YZ\Core\Entities\Utils\EntityCollection;
use YZ\Core\Entities\Utils\PaginationEntity;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

class DealerOrderRewardModel  extends BaseModel
{
    protected $table = 'tbl_dealer_order_reward';
    protected $primaryKey = 'id';
    protected $fillable = [
        'site_id',
        'member_id',
        'member_dealer_level',
        'member_dealer_hide_level',
        'reward_money',
        'order_id',
        'order_money',
        'order_created_at',
        'reward_id'
    ];
}