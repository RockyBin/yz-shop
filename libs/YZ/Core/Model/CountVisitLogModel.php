<?php

namespace YZ\Core\Model;

/**
 * 访问记录表
 * Class CountVisitorModel
 * @package YZ\Core\Model
 */
class CountVisitLogModel extends BaseModel
{
    protected $table = 'tbl_count_visit_log';
    protected $fillable = [
        'site_id',
        'client_id',
        'ip',
        'created_at',
        'country',
        'prov',
        'city',
        'referer',
        'channel',
        'page'
    ];

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}