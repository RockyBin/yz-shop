<?php

/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\OpLog;


use App\Modules\ModuleShop\Libs\Constants;

class OpLog
{
    /**
     * 记录操作日志
     *
     * @param integer $type 日志类型
     * @param string $target 操作对象，如操作订单，那应该是订单ID，如操作会员，那应该是会员ID
     * @param $beforeData 变化前数据
     * @param $afterData 变化后数据
     * @return void
     */
    public static function Log(int $type, $target, $beforeData, $afterData)
    {
        switch ($type) {
            case $type == Constants::OpLogType_DistributorUpperChange:
                DistributorUpperChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_DistributorLevelChange:
                DistributorLevelChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_AgentUpperChange:
                AgentUpperChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_AgentLevelChange:
                AgentLevelChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_MemberLevelChange:
                MemberLevelChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_MemberMerge:
                MemberMergeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_OrderMoneyChange:
                OrderMoneyChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_OrderFreightChange:
                OrderFreightChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
            case $type == Constants::OpLogType_OrderAddressChange:
                OrderAddressChangeOpLog::save($type, $target, $beforeData, $afterData);
                break;
        }
    }
}
