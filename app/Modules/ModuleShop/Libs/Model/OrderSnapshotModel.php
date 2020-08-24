<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 *  订单快照，目前只用于在供应商需要拆分订单时，记录一下原订单的信息，暂时并没有参与系统的主要功能逻辑
 * Class OrderSnapshotModel
 * @package App\Modules\Model
 */
class OrderSnapshotModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_order_snapshot';
    protected $fillable = [
        'order_id',
        'site_id',
        'data',
        'created_at'
    ];

}