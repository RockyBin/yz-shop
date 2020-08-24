<?php


namespace App\Modules\ModuleShop\Libs\Model\Supplier;

use YZ\Core\Model\BaseModel;

/**
 * 供应商网站管理员表
 * Class SupplierAdminModel
 * @package  App\Modules\ModuleShop\Libs\Model\Supplier;
 */
class SupplierAdminModel extends BaseModel
{
    protected $primaryKey = 'id';

    protected $table = 'tbl_supplier_admin';
    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'member_id',
        'name',
        'mobile',
        'headurl',
        'status',
        'role_id',
        'role_type',
        'password',
        'lastlogin'
    ];


    public static function boot()
    {
        parent::boot();
        static::deleted(function ($model) {
            static::onDeleted($model);
        });
    }

//    public static function onDeleted($model)
//    {
//        SupplierRolePermModel::where('admin_id', $model->id)->delete();
//    }

    public function member()
    {
        return $this->hasMany(MemberModel::class, 'admin_id', 'id');
    }

}