<?php
/**
 * 推荐奖设置模型
 * User: liyaohui
 * Date: 2019/12/27
 * Time: 14:58
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class DealerRecommendRewardSettingModel extends BaseModel
{
    protected $table = 'tbl_dealer_recommend_reward_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'enable',
        'auto_check',
        'same_reward_payer',
        'under_reward_payer',
        'reward_rule'
    ];
}