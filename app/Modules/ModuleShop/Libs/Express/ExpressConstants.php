<?php
/**
 * 快递发货相关常量
 * User: liyaohui
 * Date: 2020/7/11
 * Time: 15:44
 */

namespace App\Modules\ModuleShop\Libs\Express;


class ExpressConstants
{
    // 订单同步状态
    const OrderSynStatus_NoSync = 0; // 未同步
    const OrderSynStatus_SyncSuccessed = 1; // 同步成功
    const OrderSynStatus_InSync = 2; // 同步中
    const OrderSynStatus_SyncFail = 3; // 同步失败
    const OrderSynStatus_UpdateFail = 4; // 更新失败
    const OrderSynStatus_UpdateSuccessed = 5; // 更新成功
    const OrderSynStatus_CancelSuccessed = 6; // 取消成功
    const OrderSynStatus_CancelFail = 7; // 取消失败

    // 同步类型
    const OrderSynType_Send = 1; // 导入订单
    const OrderSynType_Update = 2; // 更新订单
    const OrderSynType_Cancel = 3; // 关闭订单

    // 快递100固定参数
    const ExpressParam_ChannelSource = 'YZSC';

    public static function getSyncSuccessedStatus()
    {
        return [
            ExpressConstants::OrderSynStatus_SyncSuccessed,
            ExpressConstants::OrderSynStatus_UpdateFail,
            ExpressConstants::OrderSynStatus_UpdateSuccessed,
            ExpressConstants::OrderSynStatus_CancelFail
        ];
    }
}