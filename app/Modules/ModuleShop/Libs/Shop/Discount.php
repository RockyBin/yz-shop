<?

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\Model\CouponItemModel;
use App\Modules\ModuleShop\Libs\Model\CouponModel;
use App\Modules\ModuleShop\Libs\Model\ProductClassModel;
use App\Modules\ModuleShop\Libs\Supplier\SupplierBaseSetting;
use YZ\Core\Site\Site;
use YZ\Core\Constants as CodeConstants;

/**
 * 此类用来计算优惠，如积分等
 */
class Discount
{
    /**
     * 根据拥有可用积分和金额，计算出最终可以用来进行抵扣的积分数量和金额
     * @param $myPoint 会员当前拥有多少积分 或 经过单品计算后除去重复积分后的可用积分
     * @param $orderTotalMoney 订单里的所有产品的总价格(要排除不能使用的产品)，单位：分
     * @param $productMoney 订单里某个商品的总价格，单位：分
     * @param PointDeductionConfig $pointConfig
     * @return array
     */
    public function calPointMoney($myPoint, $orderTotalMoney, $productMoney, PointDeductionConfig $pointConfig)
    {
        if (!$pointConfig->enable || $pointConfig->ratio <= 0) {
            return ['usedPoint' => 0, 'money' => 0];
        }
        /* 先去把 $orderTotalMoney 和 $productMoney 取整 会放大误差 */
        // 因为积分抵扣是XX积分换1元，所以相应的金额要求是1元的整数倍
        //$orderTotalMoney = intval($orderTotalMoney / 100) * 100;
        if ($orderTotalMoney == 0) {
            return ['usedPoint' => 0, 'money' => 0];
        }
        //$productMoney = intval($productMoney / 100) * 100;
        // 因为订单里可能会有N种商品，这时涉及到优惠分配问题，这里先按单项商品和订单总价的比例先预先分配一下每种商品的最大可用积分
        //$maxPoint = intval($myPoint * ($productMoney / $orderTotalMoney));
        $maxPoint = $myPoint;
        $money = $pointConfig->type == 0 ? $productMoney * ($pointConfig->max / 100) : $pointConfig->max;
        // 为什么要 intval($maxMoney / $pointConfig->moneyUnit): 因为是后台可能设置了 $ratio 换多少 $moneyUnit，当不满足 $moneyUnit 的整数倍时，是不能抵扣的
        $needPoint = intval($money / $pointConfig->moneyUnit) * $pointConfig->ratio;

        if ($needPoint > $maxPoint) {
            $usedPoint = intval($maxPoint / $pointConfig->ratio) * $pointConfig->ratio;
        } else {
            $usedPoint = $needPoint;
        }
        // 抵扣的积分要和钱对应 所以要用实际的积分去算出抵扣的钱
        $money = $usedPoint / $pointConfig->ratio * $pointConfig->moneyUnit;
        return ['usedPoint' => $usedPoint, 'money' => $money];
    }

    /** 根据优惠券的相关信息，计算出最终可以用来进行抵扣的金额
     * @param $couponItemId 会员当前拥有的优惠券
     * @param $orderTotalMoney 订单里的所有产品的总价格(要排除不能使用的产品)，单位：分
     * @param $productMoney 订单里某个商品的总价格，单位：分
     * @return int 此产品的优惠金额，单位：分
     */
    public function calCouponMoney($couponItemId, $orderTotalMoney, $productMoney)
    {
        $money = 0;
        $item = CouponItemModel::find($couponItemId);
        $coupon = CouponModel::find($item->coupon_id);
        if ($this->checkCoupon($coupon, $item)) {
//            $coupon = CouponModel::find($item->coupon_id);
            if (strtotime($coupon->effective_starttime) > time()) return 0;
            if ($coupon->coupon_type == 1) { // 折扣券
                // 由于获取的是优惠的金额 所以要用10去减折扣  折扣区间为 0.1 - 10 所以除以10 即可
                $money = intval(bcmul($productMoney, ((10 - $coupon->coupon_money) / 10)));
            } else if ($coupon->coupon_type == 0) { // 现金券
                // 优惠券金额如果比订单总额大  需要重新设置优惠券的金额为订单金额
                if ($coupon->coupon_money > $orderTotalMoney) {
                    $coupon->coupon_money = $orderTotalMoney;
                }
                // 因为订单里可能会有N种商品，这时涉及到优惠分配问题，这里先按单项商品和订单总价的比例先预先分配一下每种商品的最大可用金额
                if ($orderTotalMoney != $productMoney) $money = intval($coupon->coupon_money * ($productMoney / $orderTotalMoney));
                else $money = $coupon->coupon_money;
            }
        }
        return $money;
    }

    /**
     * 检测优惠券基本的状态，时间等是否正常
     * @param $couponInfo 优惠券设置信息
     * @param $itemInfo 用户领取的优惠券
     * @return bool
     */
    public function checkCoupon($couponInfo, $itemInfo)
    {
        $flag = false;
        if ($couponInfo && $itemInfo) {
            $flag = $itemInfo->status == 2 && strtotime($itemInfo->expiry_time) > time();
            $flag &= strtotime($couponInfo->effective_starttime) < time() && $couponInfo->status == 1;
        }
        return $flag;
    }

    public function getValidCoupons(IShopOrder $order)
    {
        //获取商品id列表
        $orderMemberId = $order->getThisMemberId();
        // 获取所有可用的优惠券
        $couponList = CouponItemModel::query()
            ->where('member_id', $order->getThisMemberId())
            ->where('status', 2)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->with(['coupon' => function ($query) {
                $query->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('status', 1);
            }])
            ->get()->toArray();
        if (empty($couponList)) {
            return [];
        }
        // 当前终端
        $terminal = getCurrentTerminal();
        // 订单总金额 未优惠
        $orderMoney = $order->calProductMoney();
        // 订单中所有产品
        $orderProducts = $order->getProductList();
        // 订单中所有产品所属的分类
        $orderProductClassIds = null;
        // 可以使用的优惠券
        $acceptCoupons = [];
        // 所有分类 用来拼接出可以使用优惠的分类
        $classList = ProductClassModel::query()
            ->where('status', 1)
            ->select(['class_name', 'id'])
            ->get();
        // 供应商设置
        $supplierBaseConfig = SupplierBaseSetting::getCurrentSiteSetting();
        foreach ($couponList as $coupon) {
            // 当前终端是否可用
            $couponTerminal = trim($coupon['coupon']['terminal_type'], ',');
            if (!in_array($terminal, explode(',', $couponTerminal))) {
                continue;
            }
            // 先检测优惠券时间和状态是否可用
            if (!(
                $coupon['coupon']
                & strtotime($coupon['expiry_time']) > time()
                & strtotime($coupon['coupon']['effective_starttime']) < time()
            )) {
                continue;
            }
            // 符合条件的产品
            $acceptProductList = [];
            // 订单中产品是否满足优惠券
            if ($coupon['coupon']['product_type'] == 1) {
                // 可以使用优惠的分类
                $couponProClass = trim($coupon['coupon']['product_info'], ',');
                $couponProClass = explode(',', $couponProClass);
                // 检测订单中的产品是否有符合条件的
                foreach ($orderProducts as $pro) {
                    if ($pro->getThisProductModel()->supplier_member_id && $supplierBaseConfig->open_coupon != 1) {
                        continue;
                    }
                    $class = $pro->getThisProductClass();
                    $class = $class->pluck('id');
                    $classCount = $class->count();
                    if ($classCount > 0) {
                        // 比对产品分类是否在优惠券要求的分类里 没有则不符合规则
                        $hasClass = $class->diff($couponProClass)->count();
                        if ($hasClass == $classCount) {
                            continue;
                        } else {
                            $acceptProductList[] = $pro;
                        }
                    }
                }
                if (count($acceptProductList) == 0) {
                    continue;
                } else if ($couponProClass) {
                    //如果拥有父类，则删除所有子类，只显示父类
                    $parent_id = \DB::table('tbl_product_class')
                        ->where('site_id', '=', Site::getCurrentSite()->getSiteId())
                        ->where('parent_id', '=', 0)
                        ->whereIn('id', $couponProClass)
                        ->select((\DB::raw('GROUP_CONCAT(id) as id')))
                        ->first();
                    //子类ID
                    $son_id = \DB::table('tbl_product_class')
                        ->where('site_id', '=', Site::getCurrentSite()->getSiteId())
                        ->where('parent_id', '<>', 0)
                        ->whereIn('id', $couponProClass)
                        ->get();
                    //剔除掉已选父类的子类
                    $product_class_new = [];
                    foreach ($son_id as $k => $v) {
                        if (strpos($parent_id->id, (string)$v->parent_id) === false) {
                            array_push($product_class_new, $v->id);
                        }
                    }
                    if ($parent_id->id) {
                        $product_class_new = array_merge($product_class_new, explode(',', $parent_id->id));
                    }
                    //输出产品信息数据
                    $data = \DB::table('tbl_product_class')
                        ->where('site_id', '=', Site::getCurrentSite()->getSiteId())
                        ->whereIn('id', $product_class_new)
                        ->select((\DB::raw('GROUP_CONCAT(class_name) as class_name')))
                        ->first();

                    // 获取当前可以使用优惠券的产品分类名称
                    //    $class = $classList->whereIn('id', $couponProClass)->pluck('class_name');
                    //   $coupon['coupon']['product_class_text'] = $class;
                    //先优惠券在前台显示规则，统一改成选中父类，只显示父类即可
                    $coupon['coupon']['product_class_text'] = explode(',', $data->class_name);
                }
            } elseif ($coupon['coupon']['product_type'] == 2) {
                $existsProductId = false;
                $productInfoArr = explode(',', trim($coupon['coupon']['product_info']));
                $productList = $order->getProductList();
                foreach ($productList as $item) {
                    if ($item->getThisProductModel()->supplier_member_id && $supplierBaseConfig->open_coupon != 1) {
                        continue;
                    }
                    $existsProductId = in_array($item->productId, $productInfoArr);
                    if ($existsProductId) {
                        $productTotalPrice = $item->num * $item->getThisProductModel()->price;
                        break;
                    }
                }

                if (!$existsProductId) continue;

                $coupon['coupon']['product_class_text'] = '指定商品';
            } else {
                $coupon['coupon']['product_class_text'] = '全场通用';
            }
            // 有门槛的 是否满足门槛
            if ($coupon['coupon']['doorsill_type'] == 1) {
                // 针对所有产品的优惠券 直接比对订单金额即可
                if ($coupon['coupon']['product_type'] == 0 & $orderMoney < $coupon['coupon']['doorsill_full_money']) {
                    continue;
                } // 限制某些产品的 要把符合条件的产品金额相加
                else if ($coupon['coupon']['product_type'] == 1) {
                    $productMoney = 0;
                    foreach ($acceptProductList as $pro) {
                        $productMoney += $pro->calPrice($orderMemberId);
                    }
                    if ($productMoney < $coupon['coupon']['doorsill_full_money']) {
                        continue;
                    }
                } elseif ($coupon['coupon']['product_type'] == 2 && $productTotalPrice < $coupon['coupon']['doorsill_full_money']) {
                    continue;
                }
            }
            if ($coupon['coupon']['terminal_type'] != '') {
                $coupon['coupon']['terminal_type'] = ltrim(rtrim($coupon['coupon']['terminal_type'], ','), ',');
                $terminal_type = explode(',', $coupon['coupon']['terminal_type']);
                $terminal_string = '';
                foreach ($terminal_type as $k => $item) {
                    switch ($item) {
                        case CodeConstants::TerminalType_PC:
                            $terminal_string .= 'PC、';
                            continue;
                        case CodeConstants::TerminalType_Mobile:
                            $terminal_string .= 'H5、';
                            continue;
                        case CodeConstants::TerminalType_WxOfficialAccount:
                            $terminal_string .= '公众号、';
                            continue;
                        case CodeConstants::TerminalType_WxApp:
                            $terminal_string .= '小程序、';
                            continue;
                    }
                }
                if ($terminal_string) {
                    $coupon['coupon']['terminal_type'] = rtrim($terminal_string, '、');
                }
            }

            $coupon['coupon']['doorsill_full_money'] = $coupon['coupon']['doorsill_full_money'] ? moneyCent2Yuan($coupon['coupon']['doorsill_full_money']) : 0;
            $coupon['coupon']['coupon_money'] = $coupon['coupon']['coupon_type'] == 0 ? bcdiv($coupon['coupon']['coupon_money'], 100) : $coupon['coupon']['coupon_money'];
            $acceptCoupons[] = $coupon;
        }
        return $acceptCoupons;
    }
}
