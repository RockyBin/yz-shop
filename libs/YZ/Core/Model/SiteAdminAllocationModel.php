<?php

namespace YZ\Core\Model;

/**
 * 员工流量分配规则
 * Class SiteAdminAllocationModel
 * @package App\Modules\Model
 */
class SiteAdminAllocationModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_site_admin_allocation';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'status',
        'type',
        'people_type',
        'admins'
    ];
}