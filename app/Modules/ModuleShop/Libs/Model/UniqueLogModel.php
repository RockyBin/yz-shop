<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 此表用来记录一些冗余统计数据是否已经执行，避免重复执行导致数据不对
 */
class UniqueLogModel extends BaseModel {
    protected $table = 'tbl_unique_log';
	protected $primaryKey = 'key';
	protected $keyType = 'string';
    public $incrementing = false;

	public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }

    public static function newLog($key)
    {
        try {
            $log = new static();
            $log->key = $key;
            $log-> value = 1;
            $log->save();
            return true;
        }catch(\Exception $ex){
            return false;
        }
    }
}