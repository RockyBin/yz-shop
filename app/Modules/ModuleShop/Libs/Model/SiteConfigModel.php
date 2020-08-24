<?php

namespace App\Modules\ModuleShop\Libs\Model;

use \YZ\Core\Model\BaseModel;

/**
 * 通用设置模块
 * Class SiteConfigModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class SiteConfigModel extends BaseModel
{
    protected $table = 'tbl_site_config';
    protected $primaryKey = 'site_id';
    public $incrementing = false;
    protected $fillable = [
        'product_comment_status',
        'product_comment_check_way',
        'product_comment_auto_day',
    ];
}