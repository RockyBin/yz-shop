<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use YZ\Core\Constants as CoreConstants;

/**
 * 达到消费金额成为分销商
 * Class BecomeDistributorBuyMoney
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
class BecomeDistributorBuyMoney extends AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_BuyMoney;

    /**
     * 实例化
     * BecomeDistributorBuyMoney constructor.
     * @param $memberModal
     * @param DistributionSetting|null $distributionSetting
     */
    public function __construct($memberModal, DistributionSetting $distributionSetting = null)
    {
        parent::__construct($memberModal, $distributionSetting);
        $this->setExtendData([
            'apply_conditionbuy_money_flag' => $this->setting->calc_apply_valid_condition
        ]);
        $this->periodFlag = intval($distributionSetting->getSettingModel()->calc_apply_valid_condition);
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 读取数据
        $memberModel = $this->member->getInfo(true);
        $total_consume_money = 0;
        if ($this->setting->apply_product_type == 1) {
            if ($this->setting->apply_product) {
                $class_id = explode(',', $this->setting->apply_product);
                $calc_apply_valid_condition = $this->setting->calc_apply_valid_condition;
                // 把所有分类下的产品拿出来去重
                $product = ProductModel::query()
                    ->from('tbl_product')
                    ->leftJoin('tbl_product_relation_class as r', 'r.product_id', 'tbl_product.id')
                    ->whereIn('r.class_id', $class_id)
                    ->where('tbl_product.status' , CoreConstants::Product_Status_Sell)
                    ->distinct('tbl_product.id')
                    ->pluck('tbl_product.id')
                    ->toArray();
                // 需要购买足够的商品分类的金额
                $status = $calc_apply_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
                $order = OrderItemModel::query()
                    ->leftJoin('tbl_order', 'tbl_order.id', 'tbl_order_item.order_id')
                    ->where('tbl_order.site_id', getCurrentSiteId())
                    ->where('tbl_order.member_id', $memberModel->id)
					->whereIn('tbl_order.status', $status)
                    ->whereIn('tbl_order_item.product_id', $product)
                    ->distinct('tbl_order.id')
                    ->select('tbl_order.money', 'tbl_order.coupon_money', 'tbl_order.point_money', 'tbl_order.after_sale_money')
                    ->get();
                if ($order) {
                    foreach ($order as $item) {
                        // 维权期后的要把退款的减去
                        if ($calc_apply_valid_condition == 1) {
                            $total_consume_money += ($item->money - $item->coupon_money - $item->point_money + $item->after_sale_money);
                        } else {
                            $total_consume_money += ($item->money - $item->coupon_money - $item->point_money);
                        }

                    }
                }
            }
        } elseif ($this->setting->apply_product_type == 2) {
            // 需要购买足够的商品的金额
            if ($this->setting->apply_product) {
                $product = explode(',', $this->setting->apply_product);
                $calc_apply_valid_condition = $this->setting->calc_apply_valid_condition;
                $status = $calc_apply_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
                $order = OrderItemModel::query()
                    ->leftJoin('tbl_order', 'tbl_order.id', 'tbl_order_item.order_id')
                    ->leftJoin('tbl_product as p', 'p.id', 'tbl_order_item.product_id')
                    ->where('tbl_order.site_id', getCurrentSiteId())
					->whereIn('tbl_order.status', $status)
                    ->where('tbl_order.member_id', $memberModel->id)
                    ->where('p.status', CoreConstants::Product_Status_Sell)
                    ->whereIn('tbl_order_item.product_id', $product)
                    ->distinct('tbl_order.id')
                    ->select('tbl_order.money', 'tbl_order.coupon_money', 'tbl_order.point_money', 'tbl_order.after_sale_money')
                    ->get();
                if ($order) {
                    foreach ($order as $item) {
                        // 维权期后的要把退款的减去
                        if ($calc_apply_valid_condition == 1) {
                            $total_consume_money += ($item->money - $item->coupon_money - $item->point_money + $item->after_sale_money);
                        } else {
                            $total_consume_money += ($item->money - $item->coupon_money - $item->point_money);
                        }

                    }
                }
            }
        } else {
            $total_consume_money = $this->setting->calc_apply_valid_condition ? intval($memberModel->deal_money) : intval($memberModel->buy_money);
        }

        $config_consume_money = intval($this->setting->buy_money);
        // 计算结果
        $result = $total_consume_money >= $config_consume_money;
        // 还需要多少金额
        $remain = $total_consume_money >= $config_consume_money ? 0 : $config_consume_money - $total_consume_money;
        $this->setExtendData([
            'money_remain' => moneyCent2Yuan($remain),
            'money_need' => moneyCent2Yuan($config_consume_money),
        ]);
        if (!$result) {
            $this->errorMsg = str_replace('#money#', moneyCent2Yuan($config_consume_money), trans('shop-front.distributor.buy_money_not_enough'));
        }
        return $result;
    }
}