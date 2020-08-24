<?php
/**
 * 推荐奖设置模型
 * User: liyaohui
 * Date: 2019/12/27
 * Time: 14:58
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class DealerSaleRewardSettingModel extends BaseModel
{
    protected $table = 'tbl_dealer_sale_reward_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'enable',
        'auto_check',
        'payer',
        'reward_rule'
    ];
}