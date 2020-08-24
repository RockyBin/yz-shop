<?php
namespace YZ\Core\Model;

/**
 * 资源管理器：文件的数据库记录
 * Class FileModel
 * @package YZ\Core\Model
 */
class FileModel extends BaseModel
{
    protected $table = 'tbl_file';

    public function __construct($attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}