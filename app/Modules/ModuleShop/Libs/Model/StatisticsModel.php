<?php
/**
 * 统计表
 * User: wenke
 * Date: 2019/7/30
 * Time: 11:11
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class StatisticsModel extends BaseModel
{
    protected $table = 'tbl_statistics';
    public $timestamps=true;
    protected $fillable = [
        'site_id',
        'member_id',
        'type',
        'value',
        'time',
        'dealer_parent_id'
    ];

}