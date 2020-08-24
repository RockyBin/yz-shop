<?php
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

/**
 * 经销商业绩奖励模型
 * Class AgentPerformanceRewardModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class DealerSaleRewardModel extends BaseModel
{
    protected $table = 'tbl_dealer_sale_reward';
    protected $fillable = [
        'site_id',
        'member_id',
        'member_dealer_level',
        'member_dealer_hide_level',
        'reward_money',
        'order_money',
        'order_id',
        'sub_member_id',
        'sub_member_dealer_level',
        'sub_member_dealer_hide_level',
        'reward_type',
        'order_created_at',
        'reward_id',
    ];

}
