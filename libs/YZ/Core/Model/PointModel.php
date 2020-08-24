<?php

namespace YZ\Core\Model;

/**
 * 会员的积分记录表
 * Class PointModel
 * @package YZ\Core\Model
 */
class PointModel extends BaseModel
{
    protected $table = 'tbl_point';
	protected $forceWriteConnection = true;
    protected $fillable = [
        'site_id',
        'member_id',
        'point',
        'status',
        'created_at',
        'expiry_at',
        'active_at',
        'in_type',
        'in_id',
        'out_type',
        'out_id',
        'terminal_type',
        'about',
    ];

    public function __construct(array $attributes = array())
    {
        $this->expiry_at = '2030-12-31 23:59:59';
        parent::__construct($attributes);
    }
}