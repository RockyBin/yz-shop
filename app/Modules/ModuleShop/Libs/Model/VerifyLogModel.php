<?php
/**
 * 操作记录表
 * User: liyaohui
 * Date: 2019/6/27
 * Time: 14:55
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;


class VerifyLogModel extends BaseModel
{
    protected $table = 'tbl_verify_log';
    public $timestamps = true;
    public $fillable = [
        'site_id',
        'type',
        'member_id',
        'status',
        'info',
        'foreign_id',
        'from_member_id',
        'reject_reason'
    ];
}