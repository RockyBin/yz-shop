<?php


namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class StaffVisitLogModel extends BaseModel
{
    protected $table = 'tbl_staff_visit_log';

    public $timestamps = true;

    protected $fillable = [
        'member_id',
        'admin_id',
        'site_id',
        'content'
    ];
}

