<?php

namespace YZ\Core\Model;

use App\Modules\ModuleShop\Libs\Model\ShoppingCartModel;

class MemberParentsModel extends BaseModel
{
    protected $table = 'tbl_member_parents';
    protected $fillable = [
        'site_id',
        'member_id',
        'parent_id',
        'level'];

    public static $rules = array(
        'site_id' => 'required',
        'member_id' => 'required',
        'parent_id' => 'required',
        'level' => 'required'
    );
}
