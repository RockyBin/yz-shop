<?php
/**
 * 直播导航
 * User: liyaohui
 * Date: 2020/3/17
 * Time: 14:22
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class LiveNavModel extends BaseModel
{
    protected $table = 'tbl_live_nav';
    protected $primaryKey = 'id';
    public $incrementing = true;
}