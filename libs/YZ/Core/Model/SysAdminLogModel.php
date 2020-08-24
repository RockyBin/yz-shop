<?php
namespace YZ\Core\Model;

/**
 * 系统管理员操作日志表
 * Class SysAdminLogModel
 * @package YZ\Core\Model
 */
class SysAdminLogModel extends BaseModel
{
    protected $table = 'tbl_sysadmin_log';

    public function __construct(array $attributes = array())
    {
        $this->ip = getClientIP();
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}