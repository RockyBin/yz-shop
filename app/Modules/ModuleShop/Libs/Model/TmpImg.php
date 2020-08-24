<?php

namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Model;

class TmpImg extends Model
{
    //
    protected $guarded = [];

    protected $table='tbl_tmp_img';

    protected $casts = [
        'img_path' => 'array'
    ];
}
