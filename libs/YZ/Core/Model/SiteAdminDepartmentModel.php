<?php
/**
 * 员工部门
 * User: liyaohui
 * Date: 2020/2/18
 * Time: 18:02
 */

namespace YZ\Core\Model;


use YZ\Core\Model\BaseModel;
use YZ\Core\Model\SiteAdminModel;

class SiteAdminDepartmentModel extends BaseModel
{
    protected $table = 'tbl_site_admin_department';

    protected $fillable = [
        'site_id',
        'name',
        'parent_id',
        'sort'
    ];

    /**
     * 该部门下的员工
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function siteAdmins()
    {
        return $this->hasMany(SiteAdminModel::class, 'department_id', 'id');
    }
}