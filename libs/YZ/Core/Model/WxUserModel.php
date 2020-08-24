<?php

namespace YZ\Core\Model;

/**
 * 公众号的粉丝信息表
 * Class WxUserModel
 * @package YZ\Core\Model
 */
class WxUserModel extends BaseModel
{
    protected $table = 'tbl_wx_user';
    protected $primaryKey = 'openid';
    public $incrementing = false;

    public function __construct()
    {
        $this->openid = self::genUuid(10); //数据库这个字段时唯一的，要随机生成一个
        $this->member_id = self::genUuid(10); //数据库这个字段时唯一的，要随机生成一个
		$this->created_at = date('Y-m-d H:i:s');
        parent::__construct();
    }
}