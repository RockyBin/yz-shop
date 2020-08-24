<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 经销商邀请海报数据库模型
 */
class DealerInvitePosterModel extends BaseModel {
    protected $table = 'tbl_dealer_invite_poster';
    protected $primaryKey = 'id';

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}