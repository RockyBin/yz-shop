<?php

namespace YZ\Core\Model;

class MemberAuthModel extends BaseModel
{
    protected $table = 'tbl_member_auth';
    protected $fillable = [
        'site_id',
        'member_id',
        'type',
        'openid',
        'lastlogin',
        'nickname',
        'headurl',
        'extend_info'
    ];
    public static $rules = array(
        'site_id' => 'required',
        'member_id' => 'required',
        'openid' => 'required',
    );

    public function __construct()
    {
        parent::__construct();
    }
}