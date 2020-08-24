<?php

namespace App\Modules\ModuleShop\Libs\Model\Supplier;
use YZ\Core\Model\BaseModel;
/**
 * 供应商网站角色表
 * Class SupplierRoleModel
 * @package  App\Modules\ModuleShop\Libs\Model\Supplier;
 */
class SupplierRoleModel extends BaseModel
{
    protected $table = 'tbl_supplier_role';

    protected $fillable = ['site_id', 'name', 'status'];

    /**
     * 权限
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function perms()
    {
        return $this->hasMany('App\Modules\ModuleShop\Libs\Model\Supplier\SupplierRolePermModel', 'role_id');
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
        SupplierRolePermModel::query()->where('role_id', $model->id)->delete();
    }
}