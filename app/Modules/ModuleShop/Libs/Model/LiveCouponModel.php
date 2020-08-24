<?php
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 直播优惠券记录模型
 * Class LiveCouponModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class LiveCouponModel extends BaseModel
{
    protected $table = 'tbl_live_coupon';
    protected $primaryKey = 'id';
    public $incrementing = true;

    public function coupon()
    {
        return $this->hasOne(CouponModel::class, 'id', 'coupon_id');
    }
}

