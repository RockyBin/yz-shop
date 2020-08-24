<?php
/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\OpLog;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OpLogModel;

class AgentLevelChangeOpLog extends AbstractOpLog
{
    public function __construct()
    {
        parent::__construct();
    }

    static function save(int $type, $target, $beforeData, $afterData)
    {
        self::saveAct($type, $target, $beforeData, $afterData);
    }
}