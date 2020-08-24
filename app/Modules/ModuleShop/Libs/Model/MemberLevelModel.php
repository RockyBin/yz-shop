<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 会员等级模块
 * Class MemberLevelModel
 * @package App\Modules\Model
 */
class MemberLevelModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_member_level';
    protected $fillable = [
        'site_id',
        'weight',
        'name',
        'discount',
        'condition',
        'upgrade_type',
        'upgrade_value',
        'for_newmember',
        'status'
    ];
    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}