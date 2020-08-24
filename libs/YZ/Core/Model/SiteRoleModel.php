<?php

namespace YZ\Core\Model;

/**
 * 网站角色表
 * Class SiteRoleModel
 * @package YZ\Core\Model
 */
class SiteRoleModel extends BaseModel
{
    protected $table = 'tbl_site_role';

    protected $fillable = ['site_id', 'name', 'status'];

    /**
     * 权限
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function perms()
    {
        return $this->hasMany('YZ\Core\Model\SiteRolePermModel', 'role_id');
    }

    /**
     * boot
     */
    public static function boot()
    {
        parent::boot();
        static::deleted(function ($model) {
            static::onDeleted($model);
        });
    }

    /**
     * 同时删除角色的权限
     * @param $model
     */
    public static function onDeleted($model)
    {
        SiteRolePermModel::query()->where('role_id', $model->id)->delete();
    }
}