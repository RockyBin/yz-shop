<?php
/**
 * 操作记录表
 * User: liyaohui
 * Date: 2019/6/27
 * Time: 14:55
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;


class OpLogModel extends BaseModel
{
    protected $table = 'tbl_op_log';
    public $timestamps = true;

}