<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 浏览记录模块
 * Class CouponModel
 * @package App\Modules\Model
 */
class BrowseModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_browse';

    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'member_id',
        'product_id',
        'created_at',
        'updated_at',
    ];
}