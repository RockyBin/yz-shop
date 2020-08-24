<?php
/**
 * 商品参数模板
 */

namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Model;

class ProductParamTemplateModel extends Model
{
    protected $table = 'tbl_product_param_template';
    protected $fillable = [
        'site_id',
        'name',
        'params',
        'created_at',
        'updated_at'
    ];
}
