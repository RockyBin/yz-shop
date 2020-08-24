<?php
namespace App\Modules\ModuleShop\Libs\Model;

use App\Modules\ModuleShop\Libs\Product\Product;
use YZ\Core\Model\BaseModel;

/**
 * 直播商品记录模型
 * Class LiveProductModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class LiveProductModel extends BaseModel
{
    protected $table = 'tbl_live_product';
    protected $primaryKey = 'id';
    public $incrementing = true;

    public function product()
    {
        return $this->hasOne(ProductModel::class, 'id', 'product_id');
    }
}

