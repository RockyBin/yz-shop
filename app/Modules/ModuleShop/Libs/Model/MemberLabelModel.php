<?php
/**
 * 会员标签
 * User: pangwenke
 * Date: 2020/2/19
 * Time: 14:55
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;


class MemberLabelModel extends BaseModel
{
    protected $table = 'tbl_member_label';
    public $timestamps = true;

    public function parent()
    {
        return $this->hasOne(get_class($this), 'id', 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(get_class($this), 'parent_id', 'id');
    }

    public function memberRelationLabel()
    {
        return $this->belongsToMany(MemberModel::class, 'tbl_member_relation_label', 'label_id', 'member_id');
//        return $this->hasMany(MemberRelationLabelModel::class, 'label_id', 'id');
    }
}

