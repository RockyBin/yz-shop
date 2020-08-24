<?php
namespace App\Modules\ModuleShop\Libs\Model\Activities;

use YZ\Core\Model\BaseModel;

class FreeFreightModel extends BaseModel
{
    protected $table = 'tbl_free_freight';
    protected $fillable = [
        'site_id',
        'status',
        'money',
    ];
    public $timestamps = true;
}