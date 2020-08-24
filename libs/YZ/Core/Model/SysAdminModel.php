<?php
namespace YZ\Core\Model;

/**
 * 系统管理员表
 * Class SysAdminModel
 * @package YZ\Core\Model
 */
class SysAdminModel extends BaseModel
{
    protected $table = 'tbl_sysadmin';

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}