<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 页面弹窗数据库模型
 */
class PopupModel extends BaseModel {
    protected $table = 'tbl_popup';
    protected $primaryKey = 'id';
    protected $fillable = [
        'site_id',
        'device_type',
        'page_type',
        'layout',
        'size_type',
        'margin',
        'interval',
        'show_type',
        'show_interval',
        'autoclose',
        'autoclose_second',
        'items',
    ];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
    }
}