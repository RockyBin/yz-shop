<?php
/**
 * 区域代理
 * User: liyaohui
 * Date: 2020/5/19
 * Time: 12:01
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


class AreaAgentConstants
{
    const AreaAgentLevel_Province = 10; // 省代
    const AreaAgentLevel_City = 9; // 市代
    const AreaAgentLevel_District = 8; // 区代

    const AreaAgentApplySelfLevel_Distribution = 'distribution'; // 申请
    const AreaAgentApplySelfLevel_Agent = 'agent';

    const AreaAgentStatus_WaitReview = 0; // 等待审核
    const AreaAgentStatus_Active = 1; // 生效中
    const AreaAgentStatus_RejectReview = -1; // 拒绝申请
    const AreaAgentStatus_Cancel = -2; // 取消资格
    const AreaAgentStatus_Applying = -3; //申请进行中，未完成申请（如未支付等情况）

    const AreaAgentPerformanceTimeType_Month = 0;   // 区域月业绩
    const AreaAgentPerformanceTimeType_Quarter = 1; // 区域季度业绩
    const AreaAgentPerformanceTimeType_Year = 2;    // 区域年业绩

    /**
     * 获取区域代理的所有等级
     * @return array
     */
    public static function getAreaAgentAllLevel()
    {
        return [
            static::AreaAgentLevel_Province,
            static::AreaAgentLevel_City,
            static::AreaAgentLevel_District,
        ];
    }

    /**
     * 根据区域类型返回区域的英文表示方式
     * @param $areaType
     * @return string
     */
    public static function getAreaTypeStr($areaType){
        if($areaType == static::AreaAgentLevel_Province) return "province";
        if($areaType == static::AreaAgentLevel_City) return "city";
        if($areaType == static::AreaAgentLevel_District) return "district";
        return "unknow";
    }

    /**
     * 根据区域类型返回区域的中文
     * @param $areaType
     * @return string
     */
    public static function getAreaTypeText($areaType){
        if($areaType == static::AreaAgentLevel_Province) return "省代";
        if($areaType == static::AreaAgentLevel_City) return "市代";
        if($areaType == static::AreaAgentLevel_District) return "区代";
        return "未知";
    }
}