<?php

namespace App\Modules\ModuleShop\Libs\Point;

use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForConsume;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForMemberRecommend;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForLogin;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForRegister;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForShare;
use YZ\Core\Constants;
use YZ\Core\Model\PointModel;
use YZ\Core\Site\Site;

/**
 * 积分赠送
 * Class PointGive
 * @package App\Modules\ModuleShop\Libs\Point
 */
class PointGiveHelper
{
    /**
     * 每天首次登录送积分
     * @param $memberModal
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function GiveForLogin($memberModal)
    {
        $handle = new PointGiveForLogin($memberModal);
        $handle->addPoint();
    }

    /**
     * 注册送积分
     * @param $memberModal
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function GiveForRegister($memberModal)
    {
        $handle = new PointGiveForRegister($memberModal);
        $handle->addPoint();
    }

    /**
     * 推荐会员送积分
     * @param $memberModal
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function GiveForMemberRecommend($memberModal)
    {
        $handle = new PointGiveForMemberRecommend($memberModal);
        $handle->addPoint();
    }

    /**
     * 分享送积分
     * @param $memberModal
     */
    public static function GiveForShare($memberModal)
    {
        $handle = new PointGiveForShare($memberModal);
        $handle->addPoint();
    }

    /**
     * 退款扣除积分
     * @param $afterSale
     * @return bool
     * @throws \Exception
     */
    public static function DeductForConsumeRefund($afterSale)
    {
        if ($afterSale && $afterSale->order_id) {
            $orderId = $afterSale->order_id;
            $order = OrderModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $orderId)
                ->first();
            if (!$order) return false;
            // 订单总赠送积分
            $pointConsume = PointModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('in_type', Constants::PointInOutType_Consume)
                ->where('in_id', $orderId)
                ->first();
            if (!$pointConsume) return false;
            $pointTotal = intval($pointConsume->point);
            if ($pointTotal <= 0) return false;
            // 订单现在可获得多少积分
            if (!$order->snapshot) return false;
            $snapshot = json_decode($order->snapshot, true);
            if (!$snapshot['point_config_pay']) return false;
            $pointHandle = new PointGiveForConsume($order->member_id, $orderId);
            // 读取的是付款时的积分配置
            $pointHandle->setConfig($snapshot['point_config_pay']);
            $pointNow = $pointHandle->getGivePoint();
            if ($pointNow >= $pointTotal) return false;
            // 减少赠送的积分
            $pointConsume->point = $pointNow;
            $pointConsume->save();
        }
    }

    /**
     * 订单过了售后期，处理冻结的积分
     * @param $orderId
     * @param int $type
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function DueForOrderFinish($orderId, $type = 0)
    {
        if ($type == 0) {
            // 过维权期，购物赠送积分生效
            $point = PointModel::query()->where("site_id", Site::getCurrentSite()->getSiteId())
                ->where('in_type', Constants::PointInOutType_Consume)
                ->where('in_id', $orderId)
                ->where('status', Constants::PointStatus_UnActive)
                ->first();
            if ($point) {
                $pointHandel = new Point();
                $pointHandel->active($point->id, [
                    'about' => '购买商品赠送积分，订单号：' . $orderId,
                ]);
            }
        } else {
            // 完全售后成功，清理购物赠送积分
            PointModel::query()->where("site_id", Site::getCurrentSite()->getSiteId())
                ->where('in_type', Constants::PointInOutType_Consume)
                ->where('in_id', $orderId)
                ->where('status', Constants::PointStatus_UnActive)
                ->delete();
        }
    }
}