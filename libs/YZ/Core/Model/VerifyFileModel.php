<?php
namespace YZ\Core\Model;

/**
 * 验证文件记录表
 * Class VerifyFileModel
 * @package YZ\Core\Model
 */
class VerifyFileModel extends BaseModel
{
    protected $table = 'tbl_verify_file';

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}