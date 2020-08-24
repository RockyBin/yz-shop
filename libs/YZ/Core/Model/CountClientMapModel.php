<?php

namespace YZ\Core\Model;

/**
 * 访客ID和会员ID的对照表
 * Class CountClientMapModel
 * @package YZ\Core\Model
 */
class CountClientMapModel extends BaseModel
{
    protected $table = 'tbl_count_client_map';
    protected $fillable = [
        'site_id',
        'client_id',
        'member_id',
        'created_at',
        'updated_at'
    ];

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->member_id = 0;
        parent::__construct($attributes);
    }
}