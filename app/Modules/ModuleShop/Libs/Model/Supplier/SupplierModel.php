<?php
/**
 * 供应商模型
 * User: liyaohui
 * Date: 2020/6/18
 * Time: 16:51
 */

namespace App\Modules\ModuleShop\Libs\Model\Supplier;


use App\Modules\ModuleShop\Libs\Model\ProductModel;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

class SupplierModel extends BaseModel
{
    protected $table = 'tbl_supplier';
    protected $primaryKey = 'member_id';
    public $timestamps = true;

    /**
     * 会员数据
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }

    /**
     * 所有的商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(ProductModel::class, 'supplier_member_id', 'member_id');
    }
}