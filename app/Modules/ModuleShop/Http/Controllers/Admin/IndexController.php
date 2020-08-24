<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin;

use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\SmsConfig;
use YZ\Core\Constants as CodeConstants;
use YZ\Core\Model\CountVisitLogModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Order\AfterSale;
use App\Modules\ModuleShop\Libs\Order\Order;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\PageMobiModel;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\License\SNUtil;

class IndexController extends BaseAdminController
{
    /**
     * 后台首页
     * @return array
     */
    public function index()
    {
        try {
            // 代发货订单数
            $siteId = Site::getCurrentSite()->getSiteId();
            $order = new Order();
            $orderData = $order->count([
                'status' => Constants::OrderStatus_OrderPay
            ]);
            $orderPayNum = $orderData ? intval($orderData->num) : 0;
            // 退款售后订单数
            $afterSale = new AfterSale();
            $afterSaleNum = $afterSale->count([
                'status' => [Constants::RefundStatus_Apply, Constants::RefundStatus_Agree, Constants::RefundStatus_Shipped, Constants::RefundStatus_Received]
            ]);
            // 待提现到外部金额
            $finance = new Finance();
            $financeData = $finance->count([
                'account_type' => -1,
                'out_types' => CodeConstants::FinanceOutType_Withdraw,
                'status' => CodeConstants::FinanceStatus_Freeze,
            ]);
            $withdrawApplyMoneyReal = $financeData ? intval($financeData->money_real) : 0;
            $withdrawApplyNum = $financeData ? intval($financeData->num) : 0;
            // 待审核分销商
            $distributorApplyNum = DistributorModel::query()
                ->where('status', Constants::DistributorStatus_WaitReview)
                ->where('is_del', Constants::DistributorIsDel_No)
                ->where('site_id', $siteId)
                ->count();
            // 统计商品数
            $productCountData = Product::getProductCount();
            // 预警产品数
            $productStockLowNum = $productCountData ? intval($productCountData['warning']) : 0;
            // 售罄产品数
            $productSolOutNum = $productCountData ? intval($productCountData['sold_out']) : 0;
            // 商品总数
            $totalProductNum = $productCountData ? intval($productCountData['total']) : 0;
            // 总用户数
            $totalMemberNum = MemberModel::query()->where('site_id', $siteId)->count();
            // 总订单数
            $totalOrderNum = OrderModel::query()->where('site_id', $siteId)->count();
            // 总消费金额
            $totalTradeMoney = intval(OrderModel::query()->where('site_id', $siteId)->whereIn('status', BaseShopOrder::getPaidStatusList())->sum('money'));
            // 时间
            $todayStart = date('Y-m-d') . ' 00:00:00';
            $todayEnd = date('Y-m-d') . ' 23:59:59';
            $yesterdayStart = date("Y-m-d", strtotime("-1 day")) . ' 00:00:00';
            $yesterdayEnd = date("Y-m-d", strtotime("-1 day")) . ' 23:59:59';
            // 访客数
            $uv_today = CountVisitLogModel::query()->where('site_id', $siteId)->where('created_at', '>=', $todayStart)->where('created_at', '<=', $todayEnd)->selectRaw('count(distinct(client_id)) as num')->first();
            $uv_today = $uv_today ? $uv_today->num : 0;
            $uv_yesterday = CountVisitLogModel::query()->where('site_id', $siteId)->where('created_at', '>=', $yesterdayStart)->where('created_at', '<=', $yesterdayEnd)->selectRaw('count(distinct(client_id)) as num')->first();
            $uv_yesterday = $uv_yesterday ? $uv_yesterday->num : 0;
            // 新用户数
            $member_new_num_today = MemberModel::query()->where('site_id', $siteId)->where('created_at', '>=', $todayStart)->where('created_at', '<=', $todayEnd)->count();
            $member_new_num_yesterday = MemberModel::query()->where('site_id', $siteId)->where('created_at', '>=', $yesterdayStart)->where('created_at', '<=', $yesterdayEnd)->count();
            // 充值金额
            $recharge_money_today = FinanceModel::query()->where('site_id', $siteId)->where('in_type', CodeConstants::FinanceInType_Recharge)->where('created_at', '>=', $todayStart)->where('created_at', '<=', $todayEnd)->sum('money_real');
            $recharge_money_yesterday = FinanceModel::query()->where('site_id', $siteId)->where('in_type', CodeConstants::FinanceInType_Recharge)->where('created_at', '>=', $yesterdayStart)->where('created_at', '<=', $yesterdayEnd)->sum('money_real');
            // 支付人数
            $member_pay_num_today = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $todayStart)->where('pay_at', '<=', $todayEnd)->selectRaw('count(distinct(member_id)) as num')->first();
            $member_pay_num_today = $member_pay_num_today ? $member_pay_num_today->num : 0;
            $member_pay_num_yesterday = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $yesterdayStart)->where('pay_at', '<=', $yesterdayEnd)->selectRaw('count(distinct(member_id)) as num')->first();
            $member_pay_num_yesterday = $member_pay_num_yesterday ? $member_pay_num_yesterday->num : 0;
            // 支付订单数
            $order_pay_num_today = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $todayStart)->where('pay_at', '<=', $todayEnd)->count();
            $order_pay_num_yesterday = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $yesterdayStart)->where('pay_at', '<=', $yesterdayEnd)->count();
            // 交易额
            $trade_money_today = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $todayStart)->where('pay_at', '<=', $todayEnd)->sum('money');
            $trade_money_yesterday = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $yesterdayStart)->where('pay_at', '<=', $yesterdayEnd)->sum('money');
            // 累计收益
            $total_profit_money_today = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $todayStart)->where('pay_at', '<=', $todayEnd)->whereRaw('money > product_cost + freight')->selectRaw('(sum(money) - sum(freight) - sum(product_cost)) as profit')->first();
            $total_profit_money_today = $total_profit_money_today ? intval($total_profit_money_today['profit']) : 0;
            $total_profit_money_yesterday = OrderModel::query()->where('site_id', $siteId)->where('pay_at', '>=', $yesterdayStart)->where('pay_at', '<=', $yesterdayEnd)->whereRaw('money > product_cost + freight')->selectRaw('(sum(money) - sum(freight) - sum(product_cost)) as profit')->first();
            $total_profit_money_yesterday = $total_profit_money_yesterday ? intval($total_profit_money_yesterday['profit']) : 0;
            // 新增分销商数量
            $distributor_new_num_today = DistributorModel::query()->where('site_id', $siteId)->where('status', Constants::DistributorStatus_Active)->where('passed_at', '>=', $todayStart)->where('passed_at', '<=', $todayEnd)->count();
            $distributor_new_num_yesterday = DistributorModel::query()->where('site_id', $siteId)->where('status', Constants::DistributorStatus_Active)->where('passed_at', '>=', $yesterdayStart)->where('passed_at', '<=', $yesterdayEnd)->count();
            // 分销订单数
            $distribution_order_num_today = OrderModel::query()->where('site_id', $siteId)->whereIn('status', array_merge(BaseShopOrder::getPaidStatusList(), [Constants::OrderStatus_OrderClosed]))->whereIn('has_commission', [1, 2])->where('pay_at', '>=', $todayStart)->where('pay_at', '<=', $todayEnd)->count();
            $distribution_order_num_yesterday = OrderModel::query()->where('site_id', $siteId)->whereIn('status', array_merge(BaseShopOrder::getPaidStatusList(), [Constants::OrderStatus_OrderClosed]))->whereIn('has_commission', [1, 2])->where('pay_at', '>=', $yesterdayStart)->where('pay_at', '<=', $yesterdayEnd)->count();
            // 分销订单交易额
            $distribution_trade_money_today = OrderModel::query()->where('site_id', $siteId)->whereIn('status', array_merge(BaseShopOrder::getPaidStatusList(), [Constants::OrderStatus_OrderClosed]))->whereIn('has_commission', [1, 2])->where('pay_at', '>=', $todayStart)->where('pay_at', '<=', $todayEnd)->sum('money');
            $distribution_trade_money_yesterday = OrderModel::query()->where('site_id', $siteId)->whereIn('status', array_merge(BaseShopOrder::getPaidStatusList(), [Constants::OrderStatus_OrderClosed]))->whereIn('has_commission', [1, 2])->where('pay_at', '>=', $yesterdayStart)->where('pay_at', '<=', $yesterdayEnd)->sum('money');
            // 分销订单产生的佣金（预计）
            $distribution_commission_today = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('in_type', CodeConstants::FinanceInType_Commission)->where('created_at', '>=', $todayStart)->where('created_at', '<=', $todayEnd)->sum('money');
            $distribution_commission_yesterday = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('in_type', CodeConstants::FinanceInType_Commission)->where('created_at', '>=', $yesterdayStart)->where('created_at', '<=', $yesterdayEnd)->sum('money');
            // 佣金提现已打款金额
            $commission_out_money_totay = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('status', CodeConstants::FinanceStatus_Active)->where('out_type', CodeConstants::FinanceOutType_Withdraw)->where('active_at', '>=', $todayStart)->where('active_at', '<=', $todayEnd)->sum('money_real');
            $commission_out_money_yesterday = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('status', CodeConstants::FinanceStatus_Active)->where('out_type', CodeConstants::FinanceOutType_Withdraw)->where('active_at', '>=', $yesterdayStart)->where('active_at', '<=', $yesterdayEnd)->sum('money_real');
            // 佣金提现金额（包含未打款）
            $commission_withdraw_money_today = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('out_type', CodeConstants::FinanceOutType_Withdraw)->where('created_at', '>=', $todayStart)->where('created_at', '<=', $todayEnd)->sum('money');
            $commission_withdraw_money_yesterday = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('out_type', CodeConstants::FinanceOutType_Withdraw)->where('created_at', '>=', $yesterdayStart)->where('created_at', '<=', $yesterdayEnd)->sum('money');
            // 佣金提现笔数（包含未打款）
            $commission_withdraw_num_today = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('out_type', CodeConstants::FinanceOutType_Withdraw)->where('created_at', '>=', $todayStart)->where('created_at', '<=', $todayEnd)->count();
            $commission_withdraw_num_yesterday = FinanceModel::query()->where('site_id', $siteId)->where('type', CodeConstants::FinanceType_Commission)->where('out_type', CodeConstants::FinanceOutType_Withdraw)->where('created_at', '>=', $yesterdayStart)->where('created_at', '<=', $yesterdayEnd)->count();
            // 检查步骤
            $stepFinish = false;
            // 商城设置步骤
            $shopConfig = new ShopConfig();
            $stepShopConfig = $shopConfig->getInfo()['info']->name ? true : false;
            // 产品添加步骤
            $stepProductAdd = ProductModel::query()->where('site_id', $siteId)->count() > 0 ? true : false;
            // 支付设置步骤
            $payConfig = new PayConfig();
            $payConfigType = $payConfig->getInfo(true)['type'];
            $stepPayConfig = false;
            foreach ($payConfigType as $key => $status) {
                if ($status) {
                    $stepPayConfig = true;
                    break;
                }
            }
            // 短信设置步骤
            $smsConfig = new SmsConfig();
            $stepSmsConfig = $smsConfig->getInfo()->appid ? true : false;
            // 佣金比例设置
            $stepCommissionSet = false;
            $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
            if ($sn->hasPermission(Constants::FunctionPermission_ENABLE_DISTRIBUTION)) {
                $distributionLevelNum = DistributionLevelModel::query()->where('site_id', $siteId)->get()->toArray();
                if(count($distributionLevelNum) == 1){
                    $commission = json_decode($distributionLevelNum[0]['commission']);
                    foreach ($commission as $v){
                        if($v!=0) $stepCommissionSet = true;
                    }
                }else{
                    $stepCommissionSet = true;
                }
            } else {
                $stepCommissionSet = true;
            }

            // 店铺装修
            $pagePublishNum = PageMobiModel::query()->where('site_id', $siteId)->whereNotNull('publish_at')->count();
            $stepStoreDecoration = $pagePublishNum > 0 ? true : false;
            // 如果全部都设置了
            if ($stepShopConfig && $stepProductAdd && $stepPayConfig && $stepSmsConfig && $stepCommissionSet && $stepStoreDecoration) {
                $stepFinish = true;
            }
            // 其他数据
            $shopConfig = new ShopConfig();
            $shopConfig = $shopConfig->getInfo();

            return makeApiResponseSuccess('ok', [
                'step' => [
                    'finish' => $stepFinish,
                    'shop_config' => $stepShopConfig,
                    'product_add' => $stepProductAdd,
                    'pay_config' => $stepPayConfig,
                    'sms_config' => $stepSmsConfig,
                    'commission_set' => $stepCommissionSet,
                    'store_decoration' => $stepStoreDecoration,
                ],
                'config' => [
                    'shop' => $shopConfig,
                ],
                'order_pay_num' => intval($orderPayNum),
                'after_sale_num' => intval($afterSaleNum),
                'withdraw_apply_money_real' => moneyCent2Yuan(abs($withdrawApplyMoneyReal)),
                'withdraw_apply_num' => intval($withdrawApplyNum),
                'distributor_apply_num' => intval($distributorApplyNum),
                'product_stock_low_num' => intval($productStockLowNum),
                'product_sold_out_num' => intval($productSolOutNum),
                'total_trade_money' => moneyCent2Yuan(abs($totalTradeMoney)),
                'total_product_num' => intval($totalProductNum),
                'total_member_num' => intval($totalMemberNum),
                'total_order_num' => intval($totalOrderNum),
                'uv_today' => intval($uv_today),
                'uv_yesterday' => intval($uv_yesterday),
                'member_new_num_today' => intval($member_new_num_today),
                'member_new_num_yesterday' => intval($member_new_num_yesterday),
                'recharge_money_today' => moneyCent2Yuan(abs($recharge_money_today)),
                'recharge_money_yesterday' => moneyCent2Yuan(abs($recharge_money_yesterday)),
                'member_pay_num_today' => intval($member_pay_num_today),
                'member_pay_num_yesterday' => intval($member_pay_num_yesterday),
                'order_pay_num_today' => intval($order_pay_num_today),
                'order_pay_num_yesterday' => intval($order_pay_num_yesterday),
                'trade_money_today' => moneyCent2Yuan(abs($trade_money_today)),
                'trade_money_yesterday' => moneyCent2Yuan(abs($trade_money_yesterday)),
                'total_profit_money_today' => moneyCent2Yuan(abs($total_profit_money_today)),
                'total_profit_money_yesterday' => moneyCent2Yuan(abs($total_profit_money_yesterday)),
                'distributor_new_num_today' => intval($distributor_new_num_today),
                'distributor_new_num_yesterday' => intval($distributor_new_num_yesterday),
                'distribution_order_num_today' => intval($distribution_order_num_today),
                'distribution_order_num_yesterday' => intval($distribution_order_num_yesterday),
                'distribution_trade_money_today' => moneyCent2Yuan(abs($distribution_trade_money_today)),
                'distribution_trade_money_yesterday' => moneyCent2Yuan(abs($distribution_trade_money_yesterday)),
                'distribution_commission_today' => moneyCent2Yuan(abs($distribution_commission_today)),
                'distribution_commission_yesterday' => moneyCent2Yuan(abs($distribution_commission_yesterday)),
                'commission_withdraw_num_today' => intval($commission_withdraw_num_today),
                'commission_withdraw_num_yesterday' => intval($commission_withdraw_num_yesterday),
                'commission_withdraw_money_today' => moneyCent2Yuan(abs($commission_withdraw_money_today)),
                'commission_withdraw_money_yesterday' => moneyCent2Yuan(abs($commission_withdraw_money_yesterday)),
                'commission_out_money_totay' => moneyCent2Yuan(abs($commission_out_money_totay)),
                'commission_out_money_yesterday' => moneyCent2Yuan(abs($commission_out_money_yesterday)),
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getSiteInfo()
    {
        try {
            // 查找是否有首页
            $hasPage = PageMobiModel::where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('type', Constants::PageMobiType_Home)
                ->count();
            $siteAdmin = SiteAdmin::getLoginedAdmin();
            $site = Site::getCurrentSite();
            $sn = SNUtil::getSNInstanceBySite($site->getSiteId());
            $LicensePerm = $sn->getPermission(1);
            $wsConfig = [
                'ws_url' => config('app.WS_URL'),
                'ws_user' => config('app.WS_USER'),
                'ws_pwd' => config('app.WS_PWD')
            ];
            $returnData = [
                'siteComdataPath' => Site::getSiteComdataDir(),
                'hasHomePage' => $hasPage > 0,
                'siteAdmin' => $siteAdmin,
                'LicensePerm' => $LicensePerm,
                'wsConfig' => $wsConfig,
            ];

            // 未登录的要跳转到登录页面
            if (!$siteAdmin) {
                return makeServiceResult(403, '请先登录', $returnData);
            } else {
                return makeApiResponseSuccess('ok', $returnData);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
