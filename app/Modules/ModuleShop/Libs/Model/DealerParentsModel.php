<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class DealerParentsModel extends BaseModel
{
    protected $table = 'tbl_dealer_parents';
    protected $fillable = [
        'site_id',
        'member_id',
        'dealer_level',
        'parent_id',
        'level',
    ];
}