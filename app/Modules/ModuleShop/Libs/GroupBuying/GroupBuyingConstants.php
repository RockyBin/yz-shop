<?php
/**
 * 拼团活动常量
 * User: liyaohui
 * Date: 2020/4/6
 * Time: 14:21
 */

namespace App\Modules\ModuleShop\Libs\GroupBuying;


class GroupBuyingConstants
{
    // 拼团活动状态
    const GroupBuyingStatus_Ready = 0; // 未开始
    const GroupBuyingStatus_Processing = 1; // 进行中
    const GroupBuyingStatus_NoEnd = 2; // 未结束的（包含未开始的）
    const GroupBuyingStatus_End = -1; // 已结束

    // 拼团类型
    const GroupBuyingType_Normal = 0; // 普通拼团
    const GroupBuyingType_OldWithNew = 1; // 老带新

    // 成团状态
    const GroupBuyingTearmStatus_No = 0; // 未成团
    const GroupBuyingTearmStatus_Yes = 1; // 已成团
    const GroupBuyingTearmStatus_Faile = -1; // 拼团失败
}