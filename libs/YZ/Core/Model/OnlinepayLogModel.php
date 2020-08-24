<?php
namespace YZ\Core\Model;

/**
 * 在线支付日志记录表
 * Class OnlinepayLogModel
 * @package YZ\Core\Model
 */
class OnlinepayLogModel extends BaseModel
{
    protected $table = 'tbl_onlinepay_log';

    public function __construct()
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct();
    }
}