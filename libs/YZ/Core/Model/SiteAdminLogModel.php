<?php

namespace YZ\Core\Model;

/**
 * 网站管理员操作日志表
 * Class SiteAdminPermModel
 * @package YZ\Core\Model
 */
class SiteAdminLogModel extends BaseModel
{
    protected $table = 'tbl_site_admin_log';

    public function __construct(array $attributes = array())
    {
        $this->ip = getClientIP();
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}