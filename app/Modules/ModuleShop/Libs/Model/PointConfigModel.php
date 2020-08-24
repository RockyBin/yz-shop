<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 积分配置模块
 * Class PointConfigModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class PointConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_point_config';
    protected $primaryKey = 'site_id';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'status',
        'terminal_wx',
        'terminal_wxapp',
        'terminal_mobile',
        'terminal_pc',
        'in_member_reg_point',
        'in_member_reg_status',
        'in_member_login_point',
        'in_member_login_status',
        'in_member_info_point',
        'in_member_info_status',
        'in_consume_point',
        'in_consume_status',
        'in_consume_per_price',
        'in_product_comment_point',
        'in_product_comment_status',
        'in_recharge_point',
        'in_recharge_status',
        'in_share_point',
        'in_share_status',
        'in_member_recommend_point',
        'in_member_recommend_status',
        'in_distribution_recommend_point',
        'in_distribution_recommend_status',
        'in_distribution_become_point',
        'in_distribution_become_status',
        'out_order_pay_status',
        'out_order_pay_point',
        'out_order_pay_max_percent',
        'point_give_status',
        'point_give_target'
    ];
}