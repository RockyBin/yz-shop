<?php
/**
 * 会员的云仓SKU子仓的结算记录
 */
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class CloudStockSkuSettleModel extends BaseModel
{
    protected $table = 'tbl_cloudstock_sku_settle';

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}
