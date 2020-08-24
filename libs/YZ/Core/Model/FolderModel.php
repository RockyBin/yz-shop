<?php
namespace YZ\Core\Model;

/**
 * 资源管理器：文件夹管理的数据库记录
 * Class FolderModel
 * @package YZ\Core\Model
 */
class FolderModel extends BaseModel
{
    protected $table = 'tbl_folder';

    public function __construct($attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}