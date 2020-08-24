<?php

namespace YZ\Core\Model;

class FinanceModel extends BaseModel
{
    protected $table = 'tbl_finance';
    protected $forceWriteConnection = true;
    protected $fillable = [
        'site_id',
        'member_id',
        'order_id',
        'type',
        'sub_type',
        'order_type',
        'status',
        'out_type',
        'in_type',
        'is_real',
        'pay_type',
        'tradeno',
        'terminal_type',
        'operator',
        'money',
        'money_fee',
        'money_real',
        'created_at',
        'about',
        'snapshot',
        'from_member1',
        'from_member2',
        'from_member3',
        'from_member4',
        'from_member5',
        'from_member6',
        'from_member7',
        'from_member8',
        'from_member9',
        'from_member10',
        'active_at'
    ];

    public static $rules = array(
        'site_id' => 'required',
        'member_id' => 'required',
        'type' => 'required',
        'pay_type' => 'required',
        'tradeno' => 'required',
    );

    public function __construct(array $attributes = array())
    {
        $this->created_at = date('Y-m-d H:i:s');
        $this->tradeno = self::genUuid(8);
        $this->in_type = 0;
        parent::__construct($attributes);
    }
}
