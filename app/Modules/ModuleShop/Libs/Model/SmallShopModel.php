<?php

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;


class SmallShopModel extends BaseModel
{
    protected $table = 'tbl_small_shop';
    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'status',
        'member_id',
        'name',
        'logo',
        'banner',
        'video',
        'video_cover',
        'description',
        'created_at',
        'updated_at'
    ];
}

