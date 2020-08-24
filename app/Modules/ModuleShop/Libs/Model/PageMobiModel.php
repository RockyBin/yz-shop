<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 手机端页面数据库模型
 */
class PageMobiModel extends BaseModel {
    protected $table = 'tbl_page_mobi';
    protected $primaryKey = 'id';
    protected $fillable = [
        'site_id',
        'device_type',
        'type',
        'title',
        'description',
        'background',
        'created_at',
        'saved_at',
        'publish_at',
        'template_id',
    ];

    public function __construct(array $attributes = array())
    {
        if(!array_key_exists('created_at',$attributes)){
            $attributes['created_at'] = date('Y-m-d H:i:s');
        }
        if(!array_key_exists('saved_at',$attributes)){
            $attributes['saved_at'] = date('Y-m-d H:i:s');
        }
        if(!array_key_exists('device_type',$attributes)){
            $attributes['device_type'] = 1;
        }
        parent::__construct($attributes);
    }

    public function modules()
    {
        return $this->hasMany(ModuleMobiModel::class, 'page_id');
    }
}