<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Message;

use App\Modules\ModuleShop\Libs\CloudStock\AdminPurchaseOrder;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Product\ProductSku;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use Carbon\Carbon;
use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Order\Logistics;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberAddressModel;
use YZ\Core\Model\MemberModel;


class CloudStockMessageNotice extends AbstractMessageNotice
{
    public static function sendMessageCloudStockPurchaseFrontOrderPaySuccess($orderModel)
    {
        try {
            if (!$orderModel) return false;
            $status = intval($orderModel->status);
            if ($status != Constants::CloudStockPurchaseOrderStatus_Pay) {
                return false;
            }
            // 基础数据
            $money = moneyCent2Yuan($orderModel->total_money); // 订单金额
            // 数据结构
            $param = [
                'wx_content_first' => '亲，我们已经收到您提交的支付信息啦，会尽快为您完成审核，请耐心等候',
                'url' => '/shop/front/#/cloudstock/my-purchase-order-detail?order_id=' . $orderModel->id,
                'openId' => self::getMemberWxOpenId($orderModel->member_id),
                'mobile' => self::getMemberMobile($orderModel->member_id),
                'shop_name' => self::getShopName(),
                'order_id' => $orderModel->id,
                'pay_time' => $orderModel->pay_at,
                'order_money' => $money,
                'sms_content' => '您在{shop_name}购买的云仓商品已提交线下支付信息，等待商家审核货款。查看详情{url}'
            ];
            // 发送消息
            parent::sendMessage(CodeConstants::MessageType_Order_PaySuccess, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockPurchaseFrontOrderPaySuccess:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockPurchaseAdminVerifyOrderPaySuccess($orderModel)
    {
        try {
            if (!$orderModel) return false;
            $status = intval($orderModel->status);
            if ($status == Constants::CloudStockPurchaseOrderStatus_Reviewed || $status == Constants::CloudStockPurchaseOrderStatus_Finished) {
                // 基础数据
                $money = moneyCent2Yuan($orderModel->total_money); // 订单金额
                // 数据结构
                $param = [
                    'wx_content_first' => '亲，我们已经收到您提交的支付信息啦，会尽快为您完成云仓配仓，请耐心等候',
                    'url' => '/shop/front/#/cloudstock/my-purchase-order-detail?order_id=' . $orderModel->id,
                    'openId' => self::getMemberWxOpenId($orderModel->member_id),
                    'mobile' => self::getMemberMobile($orderModel->member_id),
                    'shop_name' => self::getShopName(),
                    'order_id' => $orderModel->id,
                    'pay_time' => $orderModel->pay_at,
                    'order_money' => $money,
                    'sms_content' => '您在{shop_name}购买的云仓商品已支付成功。查看详情{url}'
                ];
                // 发送消息
                self::sendMessage(CodeConstants::MessageType_Order_PaySuccess, $param);
            }
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockPurchaseAdminVerifyOrderPaySuccess:' . $ex->getMessage());
        }
    }


    public static function sendMessageCloudStockPurchaseOrderMatch($orderModel)
    {
        try {
            if (!$orderModel) return false;
            $param = [
                'url' => '/shop/front/#/cloudstock/cloud-stock',
                'wx_content_first' => '亲，您的云仓商品已完成配仓!',
                'openId' => self::getMemberWxOpenId($orderModel->member_id),
                'mobile' => self::getMemberMobile($orderModel->member_id),
                'order_id' => $orderModel->id,
                'logistics_name' => '虚拟库存',
                'logistics_no' => '虚拟库存',
                'send_time' => date('Y-m-d H:i:s'),
                'sms_content' => '您购买的云仓商品已于{send_time}配仓。点击查看{url}'
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Order_Send, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockPurchaseOrderMatch:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockTakeDeliverySend($logisticsModel)
    {
        try {
            if (!$logisticsModel) return false;
            $logistics = Logistics::find($logisticsModel->id, $logisticsModel->site_id);
            $logisticsPage = $logistics->getSearchPage();
            $logisticsPageUrl = $logisticsPage['url'];
            $param = [
                'url' => '/shop/front/#/member/memberSingleLogistics?type=1&id=' . $logisticsModel->order_id,
                'openId' => self::getMemberWxOpenId($logisticsModel->member_id),
                'mobile' => self::getMemberMobile($logisticsModel->member_id),
                'order_id' => $logisticsModel->order_id,
                'logistics_name' => $logisticsModel->logistics_name,
                'logistics_no' => $logisticsModel->logistics_no,
                'send_time' => $logisticsModel->created_at,
                'sms_content' => '您于云仓提货的宝贝已于{send_time}发货。跟踪物流详情。点击查看{url}'
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Order_Send, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockTakeDeliverySend:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockPurchaseOrderNoPay($orderModel)
    {
        try {
            if (!$orderModel) return false;
            // 基础数据
            $money = moneyCent2Yuan($orderModel->total_money); // 订单金额
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
                ->leftJoin('tbl_cloudstock_purchase_order_item', 'tbl_product.id', '=', 'tbl_cloudstock_purchase_order_item.product_id')
                ->where('tbl_cloudstock_purchase_order_item.order_id', $orderModel->id)
                ->where('tbl_product.site_id', $orderModel->site_id)
                ->orderBy('tbl_cloudstock_purchase_order_item.id', 'asc')
                ->select('tbl_product.name')
                ->first();
            if ($productModel) {
                $productName = $productModel->name;
            }
            $member = new Member($orderModel->member_id);
            $nickName = $member->getModel()->nickname;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/cloudstock/my-purchase-order-detail?order_id=' . $orderModel->id,
                'openId' => self::getMemberWxOpenId($orderModel->member_id),
                'mobile' => self::getMemberMobile($orderModel->member_id),
                'order_id' => $orderModel->id,
                'end_time' => $endTime,
                'order_money' => $money,
                'product_name' => $productName,
                'address_info' => $nickName,
                'create_time' => $createTime,
                'sms_content' => '您购买的云仓商品还没有付款，我们会为您预留到{end_time}，请尽快支付。查看详情{url}'
            ];
            // 发送消息
            parent::sendMessage(CodeConstants::MessageType_Order_NoPay, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockPurchaseOrderNoPay:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockInventoryChange($cloudStockLogModel, $extendData)
    {
        try {
            if (!$cloudStockLogModel) return false;
            if ($extendData['member_id']) {
                $memberId = $extendData['member_id'];
            } else {
                $memberId = $cloudStockLogModel->member_id;
            }
            // 数据结构
            $param = [
                'url' => '/shop/front/#/cloudstock/cloud-depot-records',
                'openId' => self::getMemberWxOpenId($memberId),
                'mobile' => self::getMemberMobile($memberId),
                'time' => $extendData['created_at'] ? $extendData['created_at'] : $cloudStockLogModel->created_at,
            ];
            $product = (new Product($cloudStockLogModel->product_id))->getModel();
            $productSku = ProductSkusModel::find($cloudStockLogModel->sku_id);
            if ($productSku) {
                if ($productSku->sku_name) $sku_name = implode(',', json_decode($productSku->sku_name, true));
            }
            if ($extendData) {
                $num_before = $extendData['num_before'];
                $num_after = $extendData['num_after'];
                $num = $extendData['num'];
            } else {
                $num_before = $cloudStockLogModel->num_before;
                $num_after = $cloudStockLogModel->num_after;
                $num = $cloudStockLogModel->num;
            }
            $param['wx_content_first'] = "您的云仓库存发生变动";
            $param['keyword1'] = $product->name . ' ' . $sku_name . ' ' . abs($num_before);
            $type = '';
            switch (true) {
                case $cloudStockLogModel->in_type == Constants::CloudStockInType_Purchase:
                    $type = '进货增加库存';
                    break;
                case $cloudStockLogModel->in_type == Constants::CloudStockInType_FirstGift:
                    $type = '首次开通云仓赠送';
                    break;
                case $cloudStockLogModel->in_type == Constants::CloudStockInType_Return:
                    $type = '取消零售订单返还库存';
                    break;
                case $cloudStockLogModel->in_type == Constants::CloudStockInType_Manual:
                    $type = '后台手工操作';
                    break;
                case $cloudStockLogModel->in_type == Constants::CloudStockInType_TakeDelivery_Return:
                    $type = '取消提货单返还库存';
                    break;
                case $cloudStockLogModel->out_type == Constants::CloudStockOutType_Sale:
                    $type = '零售订单扣减库存';
                    break;
                case $cloudStockLogModel->out_type == Constants::CloudStockOutType_SubSale:
                    $type = '下级代理进货扣减库存';
                    break;
                case $cloudStockLogModel->out_type == Constants::CloudStockOutType_Take || $extendData['take_delivery_order']:
                    $type = '提货扣减库存';
                    break;
                case $cloudStockLogModel->out_type == Constants::CloudStockOutType_Manual:
                    $type = '后台手工操作';
                    break;
            }
            $param['keyword2'] = $type . ' ' . abs($num);
            $param['sms_content'] = '您的云仓库存由于' . $type . ',发生库存变动，查看详情{url}';
            self::sendMessage(CodeConstants::MessageType_CloudStock_Inventory_Change, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockInventoryChange:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockILevelUpgrade($memberModel)
    {
        try {
            if (!$memberModel) return false;
            $level_text = ['一', '二', '三'];
            $param = [
                'url' => '/shop/front/#/cloudstock/cloud-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'time' => date('Y-m-d H:i:s'),
                'member_nickname' => $memberModel->nickname,
                'change_type' => '云仓升级',
                'wx_content_first' => '亲，恭喜您的云仓等级升至' . $level_text[$memberModel->agent_level - 1] . '级云仓',
            ];
            self::sendMessage(CodeConstants::MessageType_CloudStock_ILevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockILevelUpgrade:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockPurchaseCommissionUnder($model, $extendData)
    {
        try {
            if (!$model) return false;
            if ($model instanceof FinanceModel) {
                $financeModel = $model;
                //因为这里不想多次放送，但又想拿到总的收益金额，只能去拿订单的金额了。因为收益就是等于订单的总金额
                $cloudStockPurchaseOrderModel = CloudStockPurchaseOrderModel::query()->where('site_id', $model->site_id)->where('id', $model->order_id)->first();
                $money = moneyCent2Yuan(abs($cloudStockPurchaseOrderModel->total_money));
                // 会员数据
                $nickName = '';
                $member_id = $financeModel->member_id;
                $member = new Member($financeModel->member_id);
                if ($member->checkExist()) {
                    $nickName = $member->getModel()->nickname;
                }
                $first = "亲，恭喜您！您的下级经销商又为您完成了一笔云仓订单，新增收入为";
                // 数据结构
            } else if ($model instanceof CloudStockPurchaseOrderModel) {
                $cloudStockPurchaseOrderModel = $model;
                $money = moneyCent2Yuan(abs($cloudStockPurchaseOrderModel->total_money));
                $cloudstock = CloudStockModel::query()->where('id', $cloudStockPurchaseOrderModel->cloudstock_id)->first();
                if (!$cloudstock) return false;
                $cloudstockModel = $cloudstock->getModel();
                // 会员数据
                $nickName = '';
                $member_id = $cloudstockModel->member_id;
                $member = new Member($cloudstockModel->member_id);
                if ($member->checkExist()) {
                    $nickName = $member->getModel()->nickname;
                }
                $first = "亲，恭喜您！您的下级经销商又为您新增了一笔云仓订单，预计收入为";
                if ($extendData) $first = "亲，恭喜您！您的下级经销商又为您完成了一笔云仓订单，新增收入为";
                // 数据结构
            }
            $param = [
                'url' => '/shop/front/#/dealer/order-settle-list',
                'openId' => self::getMemberWxOpenId($member_id),
                'mobile' => self::getMemberMobile($member_id),
                'member_nickname' => $nickName,
                'money' => $money,
                'source' => '云仓订单收入',
                'time' => $model->created_at,
                'wx_content_first' => $first,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_CloudStock_Purchase_Commission_Under, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockPurchaseCommissionUnder:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockRetailCommission()
    {
        try {

        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockRetailCommission:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockWithdrawCommission($financeModel)
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
                'url' => '/shop/front/#/dealer/my-property',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'withdraw_money' => $money,
                'member_id' => $financeModel->member_id,
                'finance_id' => $financeModel->id,
                'member_nickname' => $nickName,
                'active_time' => $financeModel->active_at,
                'withdraw_status' => '提现成功',
                'money_type' => '云仓收入',
                'wx_content_first' => '亲，您申请提现的经销商资金已打款到您的账户，请注意查收！',
                'sms_content' => '您的云仓收入账户于{active_time}成功提现¥{withdraw_money}，请注意查收'
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_CloudStock_Withdraw_Commission, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockWithdrawCommission:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockOpen($memberModel)
    {
        try {
            if (!$memberModel) return false;
            $param = [
                'url' => '/shop/front/#/cloudstock/cloud-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'time' => date('Y-m-d H:i:s'),
                'member_nickname' => $memberModel->nickname,
                'shop_name' => self::getShopName(),
                'type' => '开启' . $memberModel->agent_level . '级云仓',
                'wx_content_first' => '亲，恭喜您已成功开通云仓！',
            ];
            self::sendMessage(CodeConstants::MessageType_CloudStock_Open, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockOpen:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockMemberAdd($memberModel)
    {
        try {
            if (!$memberModel) return false;
            $inviteMember = new Member($memberModel->dealer_parent_id);
            $inviteMemberModel = $inviteMember->getModel(false);
            $param = [
                'url' => '/shop/front/#/cloudstock/cloud-center',
                'openId' => self::getMemberWxOpenId($inviteMemberModel->id),
                'mobile' => self::getMemberMobile($inviteMemberModel->id),
                'time' => date('Y-m-d H:i:s'),
                'member_nickname' => $memberModel->nickname,
                'wx_content_first' => '亲，恭喜您新增了一名经销商。',
            ];
            self::sendMessage(CodeConstants::MessageType_CloudStock_Member_Add, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockMemberAdd:' . $ex->getMessage());
        }
    }

    public static function sendMessageCloudStockInventoryNotEnough($orderModel)
    {
        try {
            if (!$orderModel) return false;
            $parentCloudStock = CloudStockModel::query()
                ->where('id', $orderModel->cloudstock_id)
                ->first();
            if (!$parentCloudStock) return false;
            $parentMember = new Member($parentCloudStock->member_id);
            $parentMemberModel = $parentMember->getModel(false);
            $cloudstockPurchaseOrder = new AdminPurchaseOrder();
            $orderInfo = $cloudstockPurchaseOrder->getOrderInfo($orderModel->id);
            $product_list = $orderInfo['product_list'];
            if (!$product_list) return false;
            foreach ($product_list as $item) {
                if ($item['shortage_stock']) {
                    $product_name = $item['name'] . ' ' . implode(' ', $item['sku_name']);
                    $inventory = $item['inventory'] ? $item['inventory'] : 0;
                    $shortageStockNum = abs($item['num'] - $item['inventory']);
                    break;
                }
            }
            $param = [
                'url' => '/shop/front/#/cloudstock/subordinate-purchase-order-detail?order_id=' . $orderModel->id,
                'openId' => self::getMemberWxOpenId($parentMemberModel->id),
                'mobile' => self::getMemberMobile($parentMemberModel->id),
                'time' => date('Y-m-d H:i:s'),
                'member_nickname' => $parentMemberModel->nickname,
                'product_name' => $product_name,
                'stock_num' => '现有库存' . $inventory . ',欠' . $shortageStockNum,
                'wx_content_first' => '您的云仓库存不足无法自动配仓，请及时补货，早日结算回款哦!',
            ];
            self::sendMessage(CodeConstants::MessageType_CloudStock_Inventory_Not_Enough, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageCloudStockInventoryNotEnough:' . $ex->getMessage());
        }
    }

    /**
     * 新订单通知（卖家）
     * @param OrderModel $orderModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageCloudStockOrderNewPay($orderModel)
    {
        try {
            if (!$orderModel) return false;
            if ($orderModel instanceof CloudStockTakeDeliveryOrderModel) {
                $sms_content = '您有一笔新的提货订单，请尽快登陆后台处理。';
            } else {
                $sms_content = '您有一笔新的代理进货订单，请尽快登陆后台处理。';
            }
            $mobile = self::getBusinessMobile();
            // 数据结构
            $param = [
                'mobile' => $mobile,
                'sms_content' => $sms_content,
                'order_id' => $orderModel->id
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Order_NewPay, $param, false);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageOrderNewPay:' . $ex->getMessage());
        }
    }


}