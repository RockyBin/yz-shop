<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 直播记录模型
 * Class LiveModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class LiveModel extends BaseModel
{
    protected $table = 'tbl_live';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;

    /**
     * 导航
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function navs()
    {
        return $this->hasMany(LiveNavModel::class, 'live_id', 'id');
    }

    /**
     * 聊天记录
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function chat()
    {
        return $this->hasMany(LiveChatModel::class, 'live_id', 'id');
    }

    /**
     * 优惠券
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function coupon()
    {
        return $this->hasMany(LiveCouponModel::class, 'live_id', 'id');
    }

    /**
     * 产品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function product()
    {
        return $this->hasMany(LiveProductModel::class, 'live_id', 'id');
    }

    /**
     * 观看人数
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function viewer()
    {
        return $this->hasMany(LiveViewerModel::class, 'live_id', 'id');
    }

}

