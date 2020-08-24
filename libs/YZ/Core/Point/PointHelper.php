<?php

namespace YZ\Core\Point;

use YZ\Core\Model\PointModel;
use YZ\Core\Site\Site;
use YZ\Core\Constants;
use Illuminate\Support\Facades\DB;

/**
 * 积分静态工具类
 *
 * @author Administrator
 */
class PointHelper
{
    /**
     * 获取指定会员的积分余额
     * @param $memberId 会员ID
     * @return int 积分余额
     */
    public static function getPointBalance($memberId)
    {
        // 统计所有生效并未过期的入帐积分
        $in = self::queryForBalanceIn()->where('member_id', $memberId)->sum('point');
        // 统计所有出帐积分，包括冻结的
        $out = self::queryForBalanceOut()->where('member_id', $memberId)->sum('point');
        return $in + $out;
    }

    /**
     * 获取指定会员的冻结积分
     * @param $memberId
     * @return array
     */
    public static function getPointBlocked($memberId)
    {
        // 冻结状态下的入账积分
        $in = PointModel::where('member_id', $memberId)->where('status', Constants::PointStatus_UnActive)->where('point', '>', '0')->sum('point');
        // 冻结状态下的出账积分
        $out = PointModel::where('member_id', $memberId)->where('status', Constants::PointStatus_UnActive)->where('point', '<', '0')->sum('point');

        return [
            'in' => abs($in),
            'out' => abs($out),
            'total' => abs($in) + abs($out)
        ];
    }

    /**
     * 获取指定会员的累计消费积分
     * @param $memberId
     * @return float|int
     */
    public static function getPointConsume($memberId)
    {
        $total = PointModel::where('member_id', $memberId)
            ->where('status', Constants::PointStatus_Active)
            ->where('point', '<', '0')
            ->whereIn('out_type', [Constants::PointInOutType_OrderPay,Constants::PointInOutType_Give_Pay])
            ->sum('point');

        return abs($total);
    }

    /**
     * 获取指定会员的积分情况
     * @param $memberId 会员id
     * @return array
     */
    public static function getPointInfo($memberId)
    {
        // 可用积分
        $balance = self::getPointBalance($memberId);
        // 冻结积分数据
        $blockedData = self::getPointBlocked($memberId);
        $blocked = $blockedData['total'];
        // 累计消费积分
        $consume = self::getPointConsume($memberId);
        // 历史积分
        $history = intval($balance) + intval($blocked) + intval($consume);

        return [
            'balance' => $balance,
            'blocked' => $blocked,
            'consume' => $consume,
            'history' => $history
        ];
    }

    /**
     * 可用积分入账 查询条件，生效的还没过期的入账
     * @return mixed
     */
    private static function queryForBalanceIn()
    {
        return PointModel::where('status', Constants::PointStatus_Active)
            ->where('point', '>', '0')
            ->where('expiry_at', '>=', date('Y-m-d H:i:s'));
    }

    /**
     * 可用积分出账 查询条件，出账的
     * @return mixed
     */
    private static function queryForBalanceOut()
    {
        return PointModel::where('point', '<', '0');
    }
}
