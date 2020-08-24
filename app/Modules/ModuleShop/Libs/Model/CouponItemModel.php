<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 优惠券模块
 * Class CouponLogModel
 * @package App\Modules\Model
 */
class CouponItemModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_coupon_item';
    protected $fillable = [
        'code',
        'coupon_id',
        'member_id',
        'receive_terminal_type',
        'receive_time',
        'expiry_time',
        'use_time',
        'status',
        'remark',
        'start_time'
    ];

    /**
     * 初始化
     * CouponItemModel constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->start_time = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }

    /**
     * 获取领取的优惠券信息
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coupon()
    {
        return $this->belongsTo(CouponModel::class, 'coupon_id');
    }

}