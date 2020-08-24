<?php

namespace YZ\Core\Model;

use YZ\Core\Model\SiteAdminDepartmentModel;

/**
 * 网站管理员表
 * Class SiteAdminModel
 * @package YZ\Core\Model
 */
class SiteAdminModel extends BaseModel
{
    protected $primaryKey = 'id';

    protected $table = 'tbl_site_admin';
    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'name',
        'mobile',
        'position',
        'department_id',
        'headurl',
        'status',
        'role_id',
        'role_type',
        'username',
        'password',
        'lastlogin'
    ];

    public function perms()
    {
        return $this->hasMany('YZ\Core\Model\SiteAdminPermModel', 'admin_id');
    }

    public static function boot()
    {
        parent::boot();
        static::deleted(function ($model) {
            static::onDeleted($model);
        });
    }

    public static function onDeleted($model)
    {
        SiteAdminPermModel::where('admin_id', $model->id)->delete();
    }

    public function member(){
        return $this->hasMany(MemberModel::class,'admin_id','id');
    }
    public function department()
    {
        return $this->belongsTo(SiteAdminDepartmentModel::class, 'department_id');
    }
}