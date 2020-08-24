<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 分销商据库模型
 */
class DistributorModel extends BaseModel
{
    protected $table = 'tbl_distributor';
    protected $primaryKey = 'member_id';
    public $incrementing = false;
    protected $fillable = [
        'member_id',
        'site_id',
        'level',
        'terminal_type',
        'status',
        'reject_reason',
        'apply_condition',
        'apply_condition_buy_times_flag',
        'apply_conditionbuy_money_flag',
        'company',
        'business_license',
        'business_license_file',
        'idcard',
        'idcard_file',
        'applicant',
        'mobile',
        'sex',
        'address',
        'remark',
        'extend_fields',
        'created_at',
        'passed_at',
        'total_commission',
        'directly_under_deal_times',
        'directly_under_deal_money',
        'subordinate_deal_times',
        'subordinate_deal_money',
        'total_team',
        'directly_under_distributor',
        'directly_under_member',
        'subordinate_distributor',
        'subordinate_member',
        'show_in_review',
        'is_del',
        'prov',
        'city',
        'area',
        'business_type',
    ];

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }

    /**
     * 分销的等级情况，关联 DistributionLevelModel
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    function levelInfo()
    {
        return $this->hasOne('App\Modules\ModuleShop\Libs\Model\DistributionLevelModel', 'id', 'level');
    }

    /**
     * 分销商的会员，关联 MemberModel
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    function memberInfo()
    {
        return $this->hasOne('YZ\Core\Model\MemberModel', 'id', 'member_id');
    }

}
