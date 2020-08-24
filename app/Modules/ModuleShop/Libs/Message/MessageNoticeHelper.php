<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Message;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use App\Modules\ModuleShop\Libs\Model\LogisticsModel;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use App\Modules\ModuleShop\Libs\Order\Logistics;
use App\Modules\ModuleShop\Libs\Point\Point;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;
use YZ\Core\Common\ShortUrl;
use YZ\Core\Constants as CodeConstants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberAddressModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\PointModel;
use YZ\Core\Model\WxTemplateModel;
use YZ\Core\Point\PointHelper;
use YZ\Core\Site\Site;
use YZ\Core\Sms\SmsTemplateMessage;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\Weixin\WxTemplateMessage;
use EasyWeChat\Factory;
use App\Modules\ModuleShop\Libs\Message\AbstractMessageNotice;

class MessageNoticeHelper extends AbstractMessageNotice
{
    /**
     * 发送付费成功通知
     * @param OrderModel $orderModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageOrderPaySuccess(OrderModel $orderModel)
    {
        try {
            if (!$orderModel) return false;
            $status = intval($orderModel->status);
            if ($status != Constants::OrderStatus_OrderPay) {
                return false;
            }
            // 基础数据
            $money = moneyCent2Yuan($orderModel->money); // 订单金额
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/memberOrderDetails?order_id=' . $orderModel->id,
                'openId' => self::getMemberWxOpenId($orderModel->member_id),
                'mobile' => self::getMemberMobile($orderModel->member_id),
                'shop_name' => self::getShopName(),
                'order_id' => $orderModel->id,
                'pay_time' => $orderModel->pay_at,
                'order_money' => $money,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Order_PaySuccess, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageOrderPaySuccess:' . $ex->getMessage());
        }
    }

    /**
     * 订单发货通知
     * @param LogisticsModel $logisticsModel 物流
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageOrderSend(LogisticsModel $logisticsModel)
    {
        try {
            if (!$logisticsModel) return false;
            $logistics = Logistics::find($logisticsModel->id, $logisticsModel->site_id);
            $logisticsPage = $logistics->getSearchPage();
            $logisticsPageUrl = $logisticsPage['url'];
            $param = [
                'url' => '/shop/front/#/member/memberSingleLogistics?id=' . $logisticsModel->order_id,
                'openId' => self::getMemberWxOpenId($logisticsModel->member_id),
                'mobile' => self::getMemberMobile($logisticsModel->member_id),
                'order_id' => $logisticsModel->order_id,
                'logistics_name' => $logisticsModel->logistics_name,
                'logistics_no' => $logisticsModel->logistics_no,
                'send_time' => $logisticsModel->created_at,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Order_Send, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageOrderSend:' . $ex->getMessage());
        }
    }

    /**
     * 订单催付通知
     * @param OrderModel $orderModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageOrderNoPay(OrderModel $orderModel)
    {
        try {
            if (!$orderModel) return false;
            // 基础数据
            $money = moneyCent2Yuan($orderModel->money); // 订单金额
            // 计算结束时间
            $endTime = '';
            $createTime = $orderModel->created_at;
            if ($createTime) {
                $orderConfig = new OrderConfig();
                $orderConfigModel = $orderConfig->getInfo();
                if ($orderConfigModel) {
                    $carbon = Carbon::parse($createTime);
                    $carbon->addMinute(intval($orderConfigModel->nopay_close_minute));
                    $carbon->addHour(intval($orderConfigModel->nopay_close_hour));
                    $carbon->addDay(intval($orderConfigModel->nopay_close_day));
                    $endTime = $carbon->toDateTimeString();
                }
            }
            // 获取第一个产品
            $productName = '';
            $productModel = ProductModel::query()->from('tbl_product')
                ->leftJoin('tbl_order_item', 'tbl_product.id', '=', 'tbl_order_item.product_id')
                ->where('tbl_order_item.order_id', $orderModel->id)
                ->where('tbl_product.site_id', Site::getCurrentSite()->getSiteId())
                ->orderBy('tbl_order_item.id', 'asc')
                ->select('tbl_product.name')
                ->first();
            if ($productModel) {
                $productName = $productModel->name;
            }
            // 获取地址信息
            $addressInfo = '';
            $addressModel = MemberAddressModel::query()->from('tbl_member_address')
                ->leftJoin('tbl_district as district_prov', 'tbl_member_address.prov', '=', 'district_prov.id')
                ->leftJoin('tbl_district as district_city', 'tbl_member_address.city', '=', 'district_city.id')
                ->leftJoin('tbl_district as district_area', 'tbl_member_address.area', '=', 'district_area.id')
                ->where('tbl_member_address.id', $orderModel->address_id)
                ->select('tbl_member_address.name as member_name', 'district_prov.name as prov_name', 'district_city.name as city_name', 'district_area.name as area_name')
                ->first();
            if ($addressModel) {
                $addressInfo = $addressModel->member_name . '，' . $addressModel->prov_name . $addressModel->city_name . $addressModel->area_name;
            }
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/memberOrderDetails?order_id=' . $orderModel->id,
                'openId' => self::getMemberWxOpenId($orderModel->member_id),
                'mobile' => self::getMemberMobile($orderModel->member_id),
                'order_id' => $orderModel->id,
                'end_time' => $endTime,
                'order_money' => $money,
                'product_name' => $productName,
                'address_info' => $addressInfo,
                'create_time' => $createTime
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Order_NoPay, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageOrderNoPay:' . $ex->getMessage());
        }
    }

    /**
     * 商家同意退货
     * @param AfterSaleModel $afterSaleModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageGoodsRefundAgree(AfterSaleModel $afterSaleModel)
    {
        try {
            if (!$afterSaleModel) return false;
            // 基础数据
            $refundMoney = moneyCent2Yuan($afterSaleModel->total_money); // 实际退款金额
            // 获取第一个产品
            $productName = '';
            $productModel = ProductModel::query()->from('tbl_product')
                ->leftJoin('tbl_after_sale_item', 'tbl_product.id', '=', 'tbl_after_sale_item.product_id')
                ->where('tbl_after_sale_item.after_sale_id', $afterSaleModel->id)
                ->where('tbl_product.site_id', Site::getCurrentSite()->getSiteId())
                ->orderBy('tbl_after_sale_item.id', 'asc')
                ->select('tbl_product.name')
                ->first();
            if ($productModel) {
                $productName = $productModel->name;
            }
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/memberAfterSeleDetails?id=' . $afterSaleModel->id,
                'openId' => self::getMemberWxOpenId($afterSaleModel->member_id),
                'mobile' => self::getMemberMobile($afterSaleModel->member_id),
                'after_sale_id' => $afterSaleModel->id,
                'order_id' => $afterSaleModel->order_id,
                'refund_money' => $refundMoney,
                'product_name' => $productName,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_GoodsRefund_Agree, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageGoodsRefundAgree:' . $ex->getMessage());
        }
    }

    /**
     * 商家拒绝退款
     * @param AfterSaleModel $afterSaleModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageMoneyRefundReject(AfterSaleModel $afterSaleModel)
    {
        try {
            if (!$afterSaleModel) return false;
            // 基础数据
            $refundMoney = moneyCent2Yuan($afterSaleModel->total_money); // 实际退款金额
            // 获取第一个产品
            $productName = '';
            $productModel = ProductModel::query()->from('tbl_product')
                ->leftJoin('tbl_after_sale_item', 'tbl_product.id', '=', 'tbl_after_sale_item.product_id')
                ->where('tbl_after_sale_item.after_sale_id', $afterSaleModel->id)
                ->where('tbl_product.site_id', Site::getCurrentSite()->getSiteId())
                ->orderBy('tbl_after_sale_item.id', 'asc')
                ->select('tbl_product.name')
                ->first();
            if ($productModel) {
                $productName = $productModel->name;
            }
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/memberAfterSeleDetails?id=' . $afterSaleModel->id,
                'openId' => self::getMemberWxOpenId($afterSaleModel->member_id),
                'mobile' => self::getMemberMobile($afterSaleModel->member_id),
                'after_sale_id' => $afterSaleModel->id,
                'order_id' => $afterSaleModel->order_id,
                'refund_money' => $refundMoney,
                'product_name' => $productName,
                'reject_reason' => $afterSaleModel->refuse_msg
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_MoneyRefund_Reject, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageMoneyRefundReject:' . $ex->getMessage());
        }
    }

    /**
     * 商家拒绝退货
     * @param AfterSaleModel $afterSaleModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageGoodsRefundReject(AfterSaleModel $afterSaleModel)
    {
        try {
            if (!$afterSaleModel) return false;
            // 基础数据
            $refundMoney = moneyCent2Yuan($afterSaleModel->total_money); // 实际退款金额
            // 获取第一个产品
            $productName = '';
            $productModel = ProductModel::query()->from('tbl_product')
                ->leftJoin('tbl_after_sale_item', 'tbl_product.id', '=', 'tbl_after_sale_item.product_id')
                ->where('tbl_after_sale_item.after_sale_id', $afterSaleModel->id)
                ->where('tbl_product.site_id', Site::getCurrentSite()->getSiteId())
                ->orderBy('tbl_after_sale_item.id', 'asc')
                ->select('tbl_product.name')
                ->first();
            if ($productModel) {
                $productName = $productModel->name;
            }
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/memberAfterSeleDetails?id=' . $afterSaleModel->id,
                'openId' => self::getMemberWxOpenId($afterSaleModel->member_id),
                'mobile' => self::getMemberMobile($afterSaleModel->member_id),
                'after_sale_id' => $afterSaleModel->id,
                'order_id' => $afterSaleModel->order_id,
                'refund_money' => $refundMoney,
                'product_name' => $productName,
                'reject_reason' => $afterSaleModel->refuse_msg
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_GoodsRefund_Reject, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageGoodsRefundReject:' . $ex->getMessage());
        }
    }

    /**
     * 退款成功通知
     * @param AfterSaleModel $afterSaleModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageMoneyRefundSuccess(AfterSaleModel $afterSaleModel)
    {
        try {
            if (!$afterSaleModel) return false;
            // 基础数据
            $refundMoney = $afterSaleModel->real_money;
            if (!$refundMoney) $refundMoney = $afterSaleModel->total_money;
            +
            $refundMoney = moneyCent2Yuan($refundMoney); // 实际退款金额
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/memberAfterSeleDetails?id=' . $afterSaleModel->id,
                'openId' => self::getMemberWxOpenId($afterSaleModel->member_id),
                'mobile' => self::getMemberMobile($afterSaleModel->member_id),
                'after_sale_id' => $afterSaleModel->id,
                'order_id' => $afterSaleModel->order_id,
                'refund_money' => $refundMoney,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_MoneyRefund_Success, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageMoneyRefundSuccess:' . $ex->getMessage());
        }
    }

    /**
     * 余额变动通知
     * @param FinanceModel $financeModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageBalanceChange(FinanceModel $financeModel)
    {
        try {
            if (!$financeModel) return false;
            // 数据处理
            $money = $financeModel->money;
            // 余额
            $balance = FinanceHelper::getMemberBalance($financeModel->member_id);
            // 原来的余额
            $balanceOriginal = $balance - $money;
            // 数据结构
            $typeText = FinanceHelper::getFinanceInOutTypeText($financeModel->in_type, $financeModel->out_type);
            $typeTextData = explode('-', $typeText);
            if (count($typeTextData) > 1) {
                $typeText = $typeTextData[1];
            }
            // 根据不同的信息，构造不用的短信内容
            $sms_content = '';
            $time = $financeModel->created_at;
            if (intval($financeModel->out_type) == CodeConstants::FinanceOutType_PayOrder || intval($financeModel->out_type) == CodeConstants::FinanceOutType_CloudStock_PayOrder) {
                $sms_content = '您的账户' . trans('shop-front.diy_word.balance') . '于{time}消费{money}元，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
            } else if (intval($financeModel->in_type) == CodeConstants::FinanceInType_Recharge) {
                $sms_content = '您于{time}充值{money}元，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
            } else if (intval($financeModel->in_type) == CodeConstants::FinanceInType_Refund) {
                $sms_content = '您的账户' . trans('shop-front.diy_word.balance') . '于{time}收到{money}元退款，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
                $time = $financeModel->active_at;
            } else if (intval($financeModel->out_type) == CodeConstants::FinanceOutType_Withdraw) {
                $sms_content = '您于{time}提现{money}元，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
                $time = $financeModel->active_at;
            } else if (intval($financeModel->in_type) == CodeConstants::FinanceInType_CommissionToBalance) {
                $sms_content = '您于{time}将{money}元' . trans('shop-front.diy_word.commission') . '提现到账户' . trans('shop-front.diy_word.balance') . '上，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
                $time = $financeModel->active_at;
            } else if (intval($financeModel->in_type) == CodeConstants::FinanceInType_Bonus) {
                $sms_content = '您于{time}获得赠金{money}元，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
                $time = $financeModel->active_at;
            } else if (intval($financeModel->in_type) == CodeConstants::FinanceInType_Give) {
                $sms_content = '您于{time}获得转现收入{money}元，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
                $time = $financeModel->active_at;
            } else if (intval($financeModel->out_type) == CodeConstants::FinanceOutType_Give) {
                $sms_content = '您于{time}由于转现支出{money}元，账户' . trans('shop-front.diy_word.balance') . '{balance}元。前往查看{url}';
                $time = $financeModel->active_at;
            }

            $param = [
                'url' => '/shop/front/#/member/balance-home',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'time' => $time,
                'money' => moneyCent2Yuan(abs($money)),
                'balance' => moneyCent2Yuan($balance),
                'balance_original' => moneyCent2Yuan($balanceOriginal),
                'type_text' => $typeText,
                'sms_content' => $sms_content,
                'wx_content_first' => "亲，您的" . trans('shop-front.diy_word.balance') . "账户发生变动",
                'keyword1' => "￥" . moneyCent2Yuan($balanceOriginal),
                'keyword2' => $typeText . "￥" . moneyCent2Yuan(abs($money)) . "，剩余￥" . moneyCent2Yuan($balance),
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Balance_Change, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageBalanceChange:' . $ex->getMessage());
        }
    }

    /**
     * 会员升级通知
     * @param MemberModel $memberModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageMemberLevelUpgrade(MemberModel $memberModel)
    {
        try {
            if (!$memberModel || intval($memberModel->level) <= 0) return false;
            $memberLevelModel = MemberLevelModel::query()
                ->where('id', $memberModel->level)
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->first();
            if (!$memberLevelModel) return false;
            $levelName = $memberLevelModel->name;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/member-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'time' => date('Y-m-d H:i:s'),
                'member_nickname' => $memberModel->nickname,
                'level_name' => $levelName,
                'member_type' => trans('shop-front.diy_word.member'),
                'change_type' => trans('shop-front.diy_word.member') . '升级',
                'wx_content_first' => '亲，恭喜您的' . trans('shop-front.diy_word.member') . '等级升至' . $levelName,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Member_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageMemberLevelUpgrade:' . $ex->getMessage());
        }
    }

    /**
     * 成为分销商通知
     * @param DistributorModel $distributorModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDistributorBecomeAgree(DistributorModel $distributorModel)
    {
        try {
            if (!$distributorModel) return false;
            $member = new Member($distributorModel->member_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/distributor/distributor-center',
                'openId' => self::getMemberWxOpenId($distributorModel->member_id),
                'mobile' => self::getMemberMobile($distributorModel->member_id),
                'shop_name' => self::getShopName(),
                'time' => $distributorModel->passed_at,
                'member_nickname' => $member->getModel()->nickname,
                'member_id' => $distributorModel->member_id,
                'change_type' => '成为' . trans("shop-front.diy_word.distributor"),
                'wx_content_first' => '亲，恭喜您已成功成为' . trans("shop-front.diy_word.distributor"),
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_DistributorBecome_Agree, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageDistributorBecomeAgree:' . $ex->getMessage());
        }
    }

    /**
     * 新增分销下级通知
     * @param MemberModel $subMemberModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageSubMemberNew(MemberModel $subMemberModel)
    {
        try {
            if (!$subMemberModel && intval($subMemberModel->invite1) <= 0) return false;
            $parentMember = new Member($subMemberModel->invite1);
            if (!$parentMember->isDistributor()) return false;
            // 数据结构
            $param = [
                'openId' => self::getMemberWxOpenId($parentMember->getMemberId()),
                'mobile' => self::getMemberMobile($parentMember->getMemberId()),
                'time' => date('Y-m-d H:i:s'),
                'member_nickname' => $subMemberModel->nickname,
                'wx_content_first' => '亲，恭喜您新增了一名下级分销商!'
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_SubMember_New, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageSubMemberNew:' . $ex->getMessage());
        }
    }

    /**
     * 分销商等级变动通知
     * @param DistributorModel $distributorModel
     * @param $originalLevelName
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDistributorLevelUpgrade(DistributorModel $distributorModel, $originalLevelName)
    {
        try {
            if (!$distributorModel || intval($distributorModel->level) <= 0) return false;
            $member = new Member($distributorModel->member_id);
            if (!$member->checkExist()) return false;
            $levelName = '';
            $levelModel = DistributionLevelModel::query()
                ->where('id', $distributorModel->level)
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->first();
            if ($levelModel) {
                $levelName = $levelModel->name;
            }

            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/member-center',
                'openId' => self::getMemberWxOpenId($distributorModel->member_id),
                'mobile' => self::getMemberMobile($distributorModel->member_id),
                'time' => date('Y-m-d H:i:s'),
                'level_name' => $levelName,
                'original_level_name' => $originalLevelName,
                'member_nickname' => $member->getModel()->nickname,
                'member_type' => trans('shop-front.diy_word.distributor'),
                'change_type' => trans('shop-front.diy_word.distributor') . '升级',
                'wx_content_first' => '亲，恭喜您的' . trans('shop-front.diy_word.distributor') . '等级升至' . $levelName,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Distributor_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageDistributorLevelUpgrade:' . $ex->getMessage());
        }
    }

    /**
     * 分销订单提成通知
     * @param FinanceModel $financeModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageCommissionActive(FinanceModel $financeModel)
    {
        try {
            if (!$financeModel) return false;
            if (intval($financeModel->type) != CodeConstants::FinanceType_Commission) return false;
            $financeStatus = intval($financeModel->status);
            if ($financeStatus == CodeConstants::FinanceStatus_Invalid) return false;
            // 数据处理
            $money = moneyCent2Yuan(abs($financeModel->money));
            // 订单数据
            $orderMoney = '';
            $time = '';
            $orderModel = OrderModel::query()
                ->where('id', $financeModel->order_id)
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->first();
            if ($orderModel) {
                $orderMoney = moneyCent2Yuan($orderModel->money);
                $time = $financeStatus == CodeConstants::FinanceStatus_Active ? $orderModel->end_at : $orderModel->pay_at;
                if (!$time) {
                    $time = $financeStatus == CodeConstants::FinanceStatus_Active ? $financeModel->active_at : $financeModel->created_at;
                }
            }
            // 数据结构
            $param = [
                'url' => '/shop/front/#/distributor/distributor-commision',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'source' => '订单' . trans('shop-front.diy_word.commission'),
                'money' => $money,
                'time' => $time,
                'wx_content_first' => $financeStatus == CodeConstants::FinanceStatus_Active ? '亲，您又成功' . trans('shop-front.diy_word.distribution') . '出一笔订单了！收入如下' : '亲，您又成功' . trans('shop-front.diy_word.distribution') . '出一笔订单了！预计收入如下',
                'sms_content' => $financeStatus == CodeConstants::FinanceStatus_Active ? '恭喜您，您的团队又新增了一笔订单,新增收入为：{money}元' : '恭喜您，您的团队又新增了一笔订单,预计收入为{money}元',
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Commission_Active, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCommissionActive:' . $ex->getMessage());
        }
    }

    /**
     * 佣金提现通知
     * @param FinanceModel $financeModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageCommissionWithdraw(FinanceModel $financeModel)
    {
        try {
            if (!$financeModel) return false;
            // 数据处理
            $money = moneyCent2Yuan(abs($financeModel->money));
            // 会员数据
            $nickName = '';
            $member = new Member($financeModel->member_id);
            if ($member->checkExist()) {
                $nickName = $member->getModel()->nickname;
            }

            // 数据结构
            $param = [
                'url' => '/shop/front/#/distributor/distributor-commision',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'withdraw_money' => $money,
                'member_id' => $financeModel->member_id,
                'finance_id' => $financeModel->id,
                'member_nickname' => $nickName,
                'active_time' => $financeModel->active_at,
                'withdraw_status' => '提现成功',
                'money_type' => trans("shop-front.diy_word.commission"),
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Commission_Withdraw, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCommissionWithdraw:' . $ex->getMessage());
        }
    }

    /**
     * 申请分销商被拒通知
     * @param DistributorModel $distributorModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDistributorBecomeReject(DistributorModel $distributorModel)
    {
        try {
            if (!$distributorModel) return false;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/distributor/distributor-center',
                'openId' => self::getMemberWxOpenId($distributorModel->member_id),
                'mobile' => self::getMemberMobile($distributorModel->member_id),
                'apply_type' => trans('shop-front.diy_word.distributor') . '申请',
                'reject_reason' => $distributorModel->reject_reason,
                'wx_content_first' => '亲，非常抱歉您的' . trans("shop-front.diy_word.distributor") . '申请未通过审核！',
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_DistributorBecome_Reject, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageDistributorBecomeReject:' . $ex->getMessage());
        }
    }

    /**
     * 库存预警通知（卖家）
     * @param ProductModel $productModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageProductStockWarn(ProductModel $productModel)
    {
        try {
            if (!$productModel) return false;
            // 数据结构
            $param = [
                'mobile' => self::getBusinessMobile(),
                'product_name' => $productModel->name,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_ProductStock_Warn, $param, false);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageProductStockWarn:' . $ex->getMessage());
        }
    }

    /**
     * 维权订单通知（卖家）
     * @param AfterSaleModel $afterSaleModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAfterSaleApply(AfterSaleModel $afterSaleModel)
    {
        try {
            if (!$afterSaleModel) return false;
            // 数据结构
            $param = [
                'mobile' => self::getBusinessMobile(),
                'order_id' => $afterSaleModel->order_id,
                'after_sale_id' => $afterSaleModel->id,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_AfterSale_Apply, $param, false);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAfterSaleApply:' . $ex->getMessage());
        }
    }

    /**
     * 买家已退货提醒（卖家）
     * @param AfterSaleModel $afterSaleModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAfterSaleGoodsRefund(AfterSaleModel $afterSaleModel)
    {
        try {
            if (!$afterSaleModel) return false;
            // 数据结构
            $param = [
                'mobile' => self::getBusinessMobile(),
                'order_id' => $afterSaleModel->order_id,
                'after_sale_id' => $afterSaleModel->id,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_AfterSale_GoodsRefund, $param, false);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAfterSaleGoodsRefund:' . $ex->getMessage());
        }
    }

    /**
     * 提现申请通知（卖家）
     * @param FinanceModel $financeModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageWithdrawApply($financeModel)
    {
        try {
            if (!$financeModel) return false;
            // 数据处理
            $money = moneyCent2Yuan(abs($financeModel->money));
            // 数据结构
            $param = [
                'mobile' => self::getBusinessMobile(),
                'withdraw_money' => $money
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Withdraw_Apply, $param, false);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageWithdrawApply:' . $ex->getMessage());
        }
    }

    /**
     * 新订单通知（卖家）
     * @param OrderModel $orderModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageOrderNewPay(OrderModel $orderModel)
    {
        try {
            if (!$orderModel) return false;
            // 数据结构
            $param = [
                'mobile' => self::getBusinessMobile(),
                'order_id' => $orderModel->id
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Order_NewPay, $param, false);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageOrderNewPay:' . $ex->getMessage());
        }
    }

    /**
     * 积分变动通知
     * @param PointModel $pointModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessagePointChange(PointModel $pointModel)
    {
        try {
            // 积分生效才发送通知
            if (!$pointModel || intval($pointModel->status) != CodeConstants::PointStatus_Active) return false;
            $inOutType = Point::mergeInoutType($pointModel->toArray())['type'];
            $point = $pointModel->point;
            $pointBalance = PointHelper::getPointBalance($pointModel->member_id);
            $pointOriginal = $pointBalance - $point;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/member-integral',
                'openId' => self::getMemberWxOpenId($pointModel->member_id),
                'mobile' => self::getMemberMobile($pointModel->member_id),
                'point' => abs($point),
                'point_remain' => $pointBalance,
                'point_original' => $pointOriginal,
                'time' => $pointModel->active_at,
                'reason' => CodeConstants::getPointInoutTypeTextForFront($inOutType),
            ];
            if (intval($pointModel->point) >= 0) {
                // 积分赠送
                $param['reason'] .= '赠送' . trans('shop-front.diy_word.point');
            }
            $param['wx_content_first'] = "亲，您的" . trans('shop-front.diy_word.point') . "账户发生变动";
            $param['keyword1'] = $pointOriginal;
            $param['keyword2'] = $param['reason'] . $param['point'] . '，剩余' . $param['point_remain'];
            self::sendMessage(CodeConstants::MessageType_Point_Change, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessagePointChange:' . $ex->getMessage());
        }
    }

    /**
     * 成为代理通知
     * @param AgentModel $agentModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentAgree(AgentModel $agentModel)
    {
        try {
            if (!$agentModel) return false;
            $member = new Member($agentModel->member_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/member-center',
                'openId' => self::getMemberWxOpenId($agentModel->member_id),
                'mobile' => self::getMemberMobile($agentModel->member_id),
                'shop_name' => self::getShopName(),
                'member_nickname' => $member->getModel()->nickname,
                'change_type' => '成为' . trans("shop-front.diy_word.team_agent"),
                'wx_content_first' => '亲，恭喜您已成功成为' . trans("shop-front.diy_word.team_agent"),
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_Agree, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentAgree:' . $ex->getMessage());
        }
    }

    /**
     * 申请代理被拒通知
     * @param AgentModel $agentModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentReject(AgentModel $agentModel)
    {
        try {
            if (!$agentModel) return false;
            $member = new Member($agentModel->member_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/member-center',
                'openId' => self::getMemberWxOpenId($agentModel->member_id),
                'mobile' => self::getMemberMobile($agentModel->member_id),
                'apply_type' => trans("shop-front.diy_word.team_agent") . '审核',
                'reject_reason' => $agentModel->reject_reason,
                'wx_content_first' => '亲，非常抱歉您的' . trans("shop-front.diy_word.team_agent") . '申请未通过审核！',
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_Reject, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentReject:' . $ex->getMessage());
        }
    }

    /**
     * 代理等级变动通知
     * @param MemberModel $memberModel
     * @param int $oldAgentLevel
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentLevelUpgrade(MemberModel $memberModel, $oldAgentLevel = 0)
    {
        try {
            if (!$memberModel) return;
            $agentLevel = intval($memberModel->agent_level);
            if ($oldAgentLevel == 0 || $agentLevel == 0 || $agentLevel >= $oldAgentLevel) return; // 比原来低级，则不通知
            // 数据结构
            $agentLevelName = Constants::getAgentLevelTextForFront($agentLevel);
            $param = [
                'url' => '/shop/front/#/agent/agent-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'member_nickname' => $memberModel->nickname,
                'member_agent_level' => $agentLevelName,
                'change_type' => trans("shop-front.diy_word.team_agent") . '升级',
                'wx_content_first' => '亲，恭喜您的' . trans("shop-front.diy_word.team_agent") . '等级升至' . $agentLevelName,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentLevelUpgrade:' . $ex->getMessage());
        }
    }

    /**
     * 成员的代理等级变动通知
     * @param MemberModel $memberModel 通知的团队上级
     * @param MemberModel $subMemberModel 团队下级
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentSubMemberLevelUpgrade(MemberModel $memberModel, MemberModel $subMemberModel)
    {
        try {
            if (!$memberModel || !$subMemberModel) return;
            if (intval($memberModel->agent_level) == 0 || intval($subMemberModel->agent_level) == 0) return;
            // 数据结构
            $subMemberAgentLevelName = Constants::getAgentLevelTextForFront(intval($subMemberModel->agent_level));
            $wxContentFirst = '亲，恭喜您，您的' . trans("shop-front.diy_word.team_agent_member") . '已升级为' . $subMemberAgentLevelName . '！';
            $smsContent = '恭喜您，您的' . trans("shop-front.diy_word.team_agent_member") . $subMemberModel->nickname . '，升级为' . $subMemberAgentLevelName . '！';
            if (intval($memberModel->agent_level) == intval($subMemberModel->agent_level)) {
                // 平级
                $wxContentFirst = '亲，您的成员已升级为' . $subMemberAgentLevelName . '，与你平级啦！';
                $smsContent = '您的' . trans("shop-front.diy_word.team_agent_member") . $subMemberModel->nickname . '，升级为' . $subMemberAgentLevelName . '，与你平级啦！';
            } else if (intval($memberModel->agent_level) > intval($subMemberModel->agent_level)) {
                // 越级
                $wxContentFirst = '亲，您的成员已升级为' . $subMemberAgentLevelName . '，越级升级啦！';
                $smsContent = '您的' . trans("shop-front.diy_word.team_agent_member") . $subMemberModel->nickname . '，升级为' . $subMemberAgentLevelName . '，越级升级啦！';
            }
            $param = [
                'url' => '/shop/front/#/agent/agent-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'member_nickname' => $subMemberModel->nickname,
                'change_type' => trans("shop-front.diy_word.team_agent_member") . '升级',
                'wx_content_first' => $wxContentFirst,
                'sms_content' => $smsContent,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_AgentSubMember_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentSubMemberLevelUpgrade:' . $ex->getMessage());
        }
    }

    /**
     * 团队分红通知
     * @param FinanceModel $financeModel
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentCommission(FinanceModel $financeModel)
    {
        try {
            if (!$financeModel || intval($financeModel->money) <= 0 || intval($financeModel->type) != CodeConstants::FinanceType_AgentCommission) return;
            if (intval($financeModel->status) == CodeConstants::FinanceStatus_Invalid) return;
            $money = moneyCent2Yuan($financeModel->money);
            $wxContentFirst = '';
            $smsContent = '';
            $source = '';
            $subType = intval($financeModel->sub_type);
            if ($subType == CodeConstants::FinanceSubType_AgentCommission_Order) {
                $source = '订单' . trans("shop-front.diy_word.agent_reward");
                if (intval($financeModel->status) == CodeConstants::FinanceStatus_Active) {
                    $wxContentFirst = '亲，恭喜您，您的团队完成了一笔订单了，新增收入如下：';
                    $smsContent = '恭喜您，您的团队完成了一笔订单，新增收入为' . $money . '元';
                } else {
                    $wxContentFirst = '亲，恭喜您，您的团队新增了一笔订单，预计收入如下：';
                    $smsContent = '恭喜您，您的团队新增了一笔订单，预计收入为' . $money . '元';
                }
            } else if ($subType == CodeConstants::FinanceSubType_AgentCommission_SaleReward) {
                $source = '订单' . trans("shop-front.diy_word.team_agent_sale_reward");
                if (intval($financeModel->status) == CodeConstants::FinanceStatus_Active) {
                    $wxContentFirst = '亲，恭喜您，成功获得到了一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，新增收入如下：';
                    $smsContent = '恭喜您，新增一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，新增收入为' . $money . '元';
                } else {
                    $wxContentFirst = '亲，恭喜您，新增一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，预计收入如下：';
                    $smsContent = '恭喜您，获得到了一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，预计收入为' . $money . '元';
                }
            } else if ($subType == CodeConstants::FinanceSubType_AgentCommission_Recommend) {
                $source = trans("shop-front.diy_word.team_agent_recommend_reward");
                $wxContentFirst = '亲，恭喜您，您已成功推荐了一个' . trans("shop-front.diy_word.team_agent") . '，获得一笔' . trans("shop-front.diy_word.team_agent_recommend_reward") . '奖金！';
                $smsContent = '恭喜您，您已成功推荐了一个' . trans("shop-front.diy_word.team_agent") . '，获得一笔' . trans("shop-front.diy_word.team_agent_recommend_reward") . '奖金，收入为' . $money . '元';
            } else if ($subType == CodeConstants::FinanceSubType_AgentCommission_Performance) {
                $source = trans("shop-front.diy_word.team_agent_performance_reward");
                $period = '';
                // PERFORMANCE_REWARD_1_2019_2
                $financeOrderId = $financeModel->order_id;
                if ($financeOrderId) {
                    $periodParam = explode('_', substr($financeOrderId, 19), 3);
                    if ($periodParam[0] == 2) {
                        $period = $periodParam[1] . '年';
                    } else if ($periodParam[0] == 1) {
                        $period = $periodParam[1] . '年第' . $periodParam[2] . '季度';
                    } else {
                        $period = $periodParam[1] . '年' . $periodParam[2] . '月';
                    }
                }
                $wxContentFirst = '亲，恭喜您，完成了' . $period . '的目标，获得到了一笔' . trans("shop-front.diy_word.team_agent_performance_reward") . '奖金！';
                $smsContent = '恭喜您，完成了' . $period . '的目标，获得到了一笔' . trans("shop-front.diy_word.team_agent_performance_reward") . '奖金，收入为¥' . $money;
            } else {
                return;
            }
            $param = [
                'url' => '/shop/front/#/agent/agent-reward',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'money' => $money,
                'source' => $source,
                'time' => intval($financeModel->status) == CodeConstants::FinanceStatus_Active ? $financeModel->active_at : $financeModel->created_at,
                'wx_content_first' => $wxContentFirst,
                'sms_content' => $smsContent,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_Commission, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentCommission:' . $ex->getMessage());
        }
    }

    /**
     * 佣金提现通知
     * @param FinanceModel $financeModel
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentCommissionWithdraw(FinanceModel $financeModel)
    {
        try {
            if (!$financeModel) return;
            // 数据处理
            $money = moneyCent2Yuan(abs($financeModel->money));
            // 会员数据
            $nickName = '';
            $member = new Member($financeModel->member_id);
            if ($member->checkExist()) {
                $nickName = $member->getModel()->nickname;
            }
            // 数据结构
            $param = [
                'url' => '/shop/front/#/agent/agent-reward',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'withdraw_money' => $money,
                'member_id' => $financeModel->member_id,
                'finance_id' => $financeModel->id,
                'member_nickname' => $nickName,
                'active_time' => $financeModel->active_at,
                'withdraw_status' => '提现成功',
                'money_type' => trans("shop-front.diy_word.agent_reward"),
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_Commission_Withdraw, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentCommissionWithdraw:' . $ex->getMessage());
        }
    }

    /**
     * 区域代理分佣通知
     * @param FinanceModel $financeModel
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAreaAgentCommission(FinanceModel $financeModel)
    {
        try {
            if (!$financeModel || intval($financeModel->money) <= 0 || intval($financeModel->type) != CodeConstants::FinanceType_AreaAgentCommission) return;
            if (intval($financeModel->status) == CodeConstants::FinanceStatus_Invalid) return;
            $money = moneyCent2Yuan($financeModel->money);
            $wxContentFirst = '';
            $smsContent = '';
            $source = '';
            $subType = intval($financeModel->sub_type);
            if ($subType == CodeConstants::FinanceSubType_AreaAgentCommission_Order) {
                $source = '订单区域代理返佣';
                if (intval($financeModel->status) == CodeConstants::FinanceStatus_Active) {
                    $wxContentFirst = '亲，您的区域返佣又成功新增一笔订单了，收入如下：';
                    $smsContent = '恭喜您，您的区域返佣又成功新增一笔订单了，新增收入为￥' . $money;
                } else {
                    $wxContentFirst = '亲，您的区域返佣又成功新增一笔订单了，预计收入如下：';
                    $smsContent = '恭喜您，您的区域返佣又成功新增一笔订单了，预计收入为￥' . $money;
                }
            } else {
                return;
            }
            $param = [
                'url' => '/shop/front/#/areaagent/areaagent-reward',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'money' => $money,
                'source' => $source,
                'time' => intval($financeModel->status) == CodeConstants::FinanceStatus_Active ? $financeModel->active_at : $financeModel->created_at,
                'wx_content_first' => $wxContentFirst,
                'sms_content' => $smsContent,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Area_Agent_Commission, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAreaAgentCommission:' . $ex->getMessage());
        }
    }

    /**
     * 区域代理佣金提现
     * @param AreaAgentApplyModel $financeModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAreaAgentWithdrawCommission($financeModel)
    {
        try {
            if (!$financeModel) return false;
            // 数据处理
            $money = moneyCent2Yuan(abs($financeModel->money));
            // 会员数据
            $nickName = '';
            $member = new Member($financeModel->member_id);
            if ($member->checkExist()) {
                $nickName = $member->getModel()->nickname;
            }

            // 数据结构
            $param = [
                'url' => '/shop/front/#/areaagent/areaagent-reward',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'withdraw_money' => $money,
                'member_id' => $financeModel->member_id,
                'finance_id' => $financeModel->id,
                'member_nickname' => $nickName,
                'active_time' => $financeModel->active_at,
                'withdraw_status' => '提现成功',
                'money_type' => '区域代理佣金',
                'wx_content_first' => '亲，您申请提现的区域代理返佣已打款到您的账户，请注意查收！',
                'sms_content' => '您的区域代理返佣于{active_time}成功提现{withdraw_money}元，请注意查收'
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_AreaAgent_Withdraw_Commission, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAreaAgentWithdrawCommission:' . $ex->getMessage());
        }
    }


    /**
     * 修改供货价通知（卖家）
     * @param Porductmodel $productModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageSupplierPriceChange($productModel)
    {
        try {
            if (!$productModel) return false;
            $supplier = SupplierModel::query()->where('member_id', $productModel->supplier_member_id)->first();
            $sms_content = '您的供应商<' . $supplier->name . '>修改了商品<' . $productModel->name . '>的供货价，请尽快登录后台查看核对销售价格成本价等信息.';
            $mobile = self::getBusinessMobile();
            Log::writeLog('mobile',$mobile);
            // 数据结构
            $param = [
                'mobile' => $mobile,
                'sms_content' => $sms_content,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Supplier_Price_Change, $param, false);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageOrderNewPay:' . $ex->getMessage());
        }
    }
}