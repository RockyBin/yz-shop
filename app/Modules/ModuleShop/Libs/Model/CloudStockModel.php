<?php
/**
 * 会员的云仓主记录，记录会员总仓的状态等
 */
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class CloudStockModel extends BaseModel
{
    protected $table = 'tbl_cloudstock';

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}
