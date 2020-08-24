<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class OrderMembersHistoryModel extends BaseModel
{
    protected $table = 'tbl_order_members_history';
    protected $fillable = [
        'site_id',
        'member_id',
        'order_id',
        'member_id',
        'level',
        'type',
        'calc_distribution_performance'
    ];
}