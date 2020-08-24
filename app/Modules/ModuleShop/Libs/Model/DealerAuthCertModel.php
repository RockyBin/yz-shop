<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 代理授权证书设计表
 */
class DealerAuthCertModel extends BaseModel {
    protected $table = 'tbl_dealer_authcert';
    protected $primaryKey = 'id';

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}