<?php
/**
 * 会员的云仓SKU子仓记录
 */
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class CloudStockSkuModel extends BaseModel
{
    protected $table = 'tbl_cloudstock_sku';

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}
