<?php
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 直播聊天记录模型
 * Class LiveChatModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class LiveChatModel extends BaseModel
{
    protected $table = 'tbl_live_chat';
    protected $primaryKey = 'id';
    public $incrementing = true;
}

