<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 移动端页面模块数据库模型
 */
class ModuleMobiModel extends BaseModel {
    protected $table = 'tbl_module_mobi';

    public function page()
    {
        return $this->belongsTo(PageMobiModel::class, 'page_id');
    }
}