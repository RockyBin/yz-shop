<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 代理商授权证书
 */
class DealerAuthCertItemModel extends BaseModel {
    protected $table = 'tbl_dealer_authcert_item';
    protected $primaryKey = 'id';
    protected $keyType = 'string';

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}