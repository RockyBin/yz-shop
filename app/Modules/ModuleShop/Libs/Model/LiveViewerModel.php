<?php
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 直播观众列表模型
 * Class LiveViewerModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class LiveViewerModel extends BaseModel
{
    protected $table = 'tbl_live_viewer';
    protected $primaryKey = 'client_id';
    public $incrementing = false;
}

