<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 商城通过网址打包的APP
 * Class AppModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class MobileAppModel extends BaseModel
{
    protected $table = 'tbl_mobile_app';
    protected $fillable = [
        'site_id',
        'device_type',
        'name',
        'logo',
        'lunch_image',
        'url',
        'created_at',
        'updated_at'
    ];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
    }
}