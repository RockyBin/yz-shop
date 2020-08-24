<?php
/**
 * 会员标签
 * User: pangwenke
 * Date: 2020/2/19
 * Time: 14:55
 */

namespace App\Modules\ModuleShop\Libs\Model;

use App\Modules\ModuleShop\Libs\Member\MemberLabel;
use YZ\Core\Model\BaseModel;


class MemberRelationLabelModel extends BaseModel
{
    protected $table = 'tbl_member_relation_label';


    public function memberLabel()
    {
        return $this->hasOne(MemberLabel::class, 'id', 'label_id');
    }
}

