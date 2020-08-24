<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 手机端分享海报的数据库模型
 */
class SharePaperModel extends BaseModel {
    protected $table = 'tbl_share_paper';
    protected $primaryKey = 'id';

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}