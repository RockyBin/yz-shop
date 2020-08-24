<?php

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentCommission;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentCommissionConfig;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Model\CouponItemModel;
use App\Modules\ModuleShop\Libs\Model\CouponModel;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionConfig;
use App\Modules\ModuleShop\Libs\Distribution\Distribution;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Product\ProductSku;
use YZ\Core\Member\Auth;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Agent\AgentOrderCommisionConfig;
use App\Modules\ModuleShop\Libs\Agent\AgentReward;
use App\Modules\ModuleShop\Libs\Agent\AgentSaleRewardCommisionConfig;

/**
 * 通用商品
 */
abstract class BaseShopProduct implements IShopProduct
{
    public $productId = 0; //产品ID
    public $skuId = 0; //规格ID
    public $supplierPrice = 0; //商品的供货价
    public $oriPrice = 0; // 商品原单价（不包含任何优惠时）
    public $money = 0; //最终销售单价
    public $costMoney = 0; //成本单价
    public $totalMoney = 0; //这个商品总的实付金额（已经计算了所有优惠和购买数量）
    public $couponMoney = 0; //优惠券抵扣的金额
    public $pointMoney = 0; //积分抵扣的金额
    public $pointUsed = 0; //使用了多少积分
    public $freight = 0; //运费
    public $supplierMemberId = 0; //商品的供应商会员ID
    public $isCommissionProduct = 0; //是否分销商品，目前是当成为分销的条件是购买某商品时候，才设置此值
    public $num = 1; //订购数量
    public $name = ''; //产品名称
    public $image = ''; //小图路径
    public $skuNames = []; //规格名称
    public $fenxiaoRule = 0; //此产品是否开启了分销规则
    public $agentOrderCommissionRule = 0; // 此产品是否开启了订单分红规则
    public $agentSaleRewardRule = 0; // 此产品是否开启了销售奖规则
    protected $_productModel = null;
    protected $_productSku = null;
    protected $_freightModel = null;
    protected $_memberPriceRule = null;
    protected $_memberLevel = null;

    /**
     * BaseShopProduct constructor.
     * @param $productId
     * @param int $skuId
     * @param int $num
     * @throws \Exception
     */
    public function __construct($productIdOrModel, $skuIdOrModel = 0, $num = 1)
    {
        $this->productId = $productIdOrModel;
        $this->skuId = $skuIdOrModel;
        $this->num = $num;
        $this->initProduct();
        $this->initProductSku();
        $this->costMoney = $this->_productSku->supply_price;
        $this->fenxiaoRule = $this->_productSku->fenxiao_rule;
        $this->agentOrderCommissionRule = $this->_productSku->agent_order_commission_rule;
        $this->agentSaleRewardRule = $this->_productSku->agent_sale_reward_rule;
        $this->name = $this->_productModel->name;
        $this->image = $this->image ?: explode(',', $this->_productModel->small_images)[0];
        $this->after_sale_setting =  $this->_productModel->after_sale_setting;
    }

    /**
     * 初始化商品
     * @throws \Exception
     */
    protected function initProduct(){
        if(is_numeric($this->productId)) {
            $this->_productModel = ProductModel::query()
                ->where('id', $this->productId)
                ->first();
            if (!$this->_productModel) {
                throw new \Exception(trans('shop-front.shop.cant_found'));
            }
            if (Site::getCurrentSite() && $this->_productModel->site_id != Site::getCurrentSite()->getSiteId()) {
                throw new \Exception('site id not match ' . Site::getCurrentSite()->getSiteId() . ' -- ' . $this->_productModel->site_id);
            }
        }else{
            $this->_productModel = $this->productId;
            $this->productId = $this->_productModel->id;
        }
        $this->supplierMemberId = $this->_productModel->supplier_member_id;
    }

    /**
     * 动态改变类的SKU
     */
    public function setSku($skuIdOrModel = 0){
        $this->skuId = $skuIdOrModel;
        $this->initProductSku();
    }

    /**
     * 初始化商品sku
     * @throws \Exception
     */
    protected function initProductSku()
    {
        if ($this->skuId) {
            if(is_numeric($this->skuId)) {
                $this->_productSku = $this->_productModel->productSkus()->find($this->skuId);
            }else{
                $this->_productSku = $this->skuId;
                $this->skuId = $this->_productSku->id;
            }
            if ($this->_productSku->sku_image) $this->image = $this->_productSku->sku_image;
            if ($this->_productSku->sku_name) $this->skuNames = json_decode($this->_productSku->sku_name, true);
        } else {
            $this->_productSku = $this->_productModel->productSkus()->where('sku_code', '=', '0')->first();
        }
        if(!$this->supplierPrice) $this->supplierPrice = $this->_productSku->supplier_price;
        if(!$this->oriPrice) $this->oriPrice = $this->_productSku->price;
    }

    protected function getMemberLevel(){
        if(!$this->_memberLevel) $this->_memberLevel = new MemberLevel();
        return $this->_memberLevel;
    }

    /**
     * 获取检测库存时的锁ID
     * @return string
     */
    public function getSkuLockId()
    {
        return "Locker_" . $this->productId . "_" . $this->skuId;
    }

    /**
     * 计算产品价格
     * @param int $memberId
     * @return mixed
     */
    public function calPrice(int $memberId)
    {
        $mem = new Member($memberId);
        $mModel = $mem->getInfo();
        $money = $this->getMemberPrice($mModel->level);
        return $money * $this->num;
    }

    /**
     * 返回商品本身的金额（含数量，未扣除优惠），应该先调用 calPrice() 再调用此方法
     * @return mixed
     */
    public function getProductMoney()
    {
        return $this->money * $this->num;
    }

    /**
     * 获取此商品的会员单价
     * @return int $money
     */
    public function getMemberPrice($userLevel,$rule = null)
    {
        $this->money = $this->_productSku->price;
        $privateRule = false;
        if ($this->_productSku->member_rule > 0) {
            if(!$rule) {
                $expression = ProductPriceRuleModel::query()->from('tbl_product_price_rule');
                //寻找自定义规则
                $expression->where(['id' => $this->_productSku->member_rule]);
                $rule = $expression->first();
            }
            if ($rule) {
                $ruleArr = json_decode($rule->rule_info, true);
                $rule = $ruleArr['rule'];
                if ($rule[$userLevel]) {
                    $privateRule = true;
                    $this->_memberPriceRule = $rule;
                    if ($ruleArr['amountType'] == 1) {
                        $this->money = $rule[$userLevel]['discount'];
                    } else {
                        $this->money = moneyMul($this->money, $rule[$userLevel]['discount'] / 100);
                    }
                }
            }
        } else if ($this->_productSku->member_rule == -1) {
            //没开启的时候直接
            return $this->money;
        }
        //如果使用默认的时候，就用系统的
        if (!$privateRule) {
            $memberLevel = $this->getMemberLevel();
            $discount = $memberLevel->getDiscountById($userLevel);
            if ($discount > 0 && $discount < 100) {
                $this->money = moneyMul($this->money, $discount / 100);
            }
        }
        return $this->money;
    }

    /**
     * 计算产品能用的积分
     * @param int $orderMoney 订单可使用积分的产品总金额，单位分，积分抵扣规则里，有最大金额限制，在运算过程中会用到订单总金额
     * @param int $myPoint 用户拥有的积分
     * @param boolean $usePoint 是否使用积分 为false则只是计算可以使用多少积分
     * @return mixed
     */
    public function calPoint(int $orderMoney, int $myPoint, $usePoint = true)
    {
        $pointConfig = $this->getPointDeductionConfig();
        if ($_REQUEST['debug']) $pointConfig->enable = true;
        $discount = new Discount();
        $res = $discount->calPointMoney($myPoint, $orderMoney, $this->getProductMoney() - $this->couponMoney, $pointConfig);
        if ($usePoint) {
            $this->pointMoney = $res['money'];
            $this->pointUsed = $res['usedPoint'];
        }
        return $res;
    }

    /** 计算产品的优惠券抵扣金额
     * @param int $ordrMoney 订单可使用优惠券的产品总金额
     * @param int $couponItemId 用户领取的优惠券的ID
     * @return 抵扣的金额，单位：分
     */
    public function calCoupon(int $ordrMoney, int $couponItemId)
    {
        $d = new Discount();
        $res = $d->calCouponMoney($couponItemId, $ordrMoney, $this->getProductMoney());
        $this->couponMoney = $res;
        return $res;
    }

    /**
     * 算其它优惠，根据不同的产品类型会不同，通用产品暂不实现
     * @param mixed ...$args
     * @return mixed
     */
    public function calOtherDiscount(...$args)
    {
        return 0;
    }

    /**
     * 计算产品的运费
     * @param int $cityId 地级市ID
     * @param int $hasFirstFee 是否算首件运费 1为计算 0为不计算(是为了相同产品 不同sku的运费计算)
     * @return float|int|mixed|string
     * @throws \Exception
     */
    public function calFreight(int $cityId, $hasFirstFee = 1)
    {
        if ($this->isVirtual()) return 0; //虚拟商品强制返回0
        $freight = 0;
        if ($this->_productModel->freight_id) {
            $this->_freightModel = FreightTemplateModel::find($this->_productModel->freight_id);
            if (!$this->_freightModel) {
                throw new \Exception(trans("shop-front.shop.freight_template_not_exists"));
            }
            // 运费模板信息
            $areas = json_decode($this->_freightModel->delivery_area, true);
            $firstFee = 0;
            $renewFee = 0;
            // 全国统一运费
            if ($this->_freightModel->delivery_type == 0) {
                if ($this->_freightModel->fee_type == 2) { //固定价格
                    $freight = $hasFirstFee == 1 ? $areas[0]['firstFee'] * 100 : 0; //转为分
                    $this->freight = $freight;
                    return $freight;
                } else {
                    $firstFee = floatval($areas[0]['firstFee']);
                    $renewFee = floatval($areas[0]['renewFee']);
                }
            } // 指定地区不同运费
            else {
                if ($this->_freightModel->fee_type == 2) {
                    foreach ($areas as $item) {
                        if (strpos($item['area'], strval($cityId)) !== false) {
                            $freight = $hasFirstFee == 1 ? moneyYuan2Cent(floatval($item['firstFee'])) : 0;
                            $this->freight = $freight;
                            return $freight;
                        }
                    }
                } else {
                    foreach ($areas as $item) {
                        if (strpos($item['area'], strval($cityId)) !== false) {
                            $firstFee = floatval($item['firstFee']);
                            $renewFee = floatval($item['renewFee']);
                        }
                    }
                }
            }
            $firstNum = 1;
            // 如果不用算首件的运费 把首件运费设为0
            if ($hasFirstFee != 1) {
                $firstNum = 0;
                $firstFee = 0;
            }
            if ($this->_freightModel->fee_type == 0) { //计重
                $freight = $firstFee + ceil($this->_productSku->weight * $this->num - $firstNum) * $renewFee;
            } else if ($this->_freightModel->fee_type == 1) { //计件
                $freight = $firstFee + ($this->num - $firstNum) * $renewFee;
            }
            $freight = moneyYuan2Cent($freight); //转为分
        }
        $this->freight = $freight;
        return $freight;
    }

    /**
     * 是否包邮
     * @return mixed
     */
    public function isFreeFreight()
    {
        return $this->_productModel->freight_id == 0;
    }

    /**
     * 判断用户选定的送货城市是否能送货
     * @param int $cityId
     * @return mixed
     */
    public function canDelivery(int $cityId = 0)
    {
        if ($this->isVirtual()) return true;
        if (!$this->_productModel->freight_id) return true;
        $mFreight = FreightTemplateModel::find($this->_productModel->freight_id);
        if ($mFreight && $mFreight->delivery_type != 1) return true;
        $areas = json_decode($mFreight->delivery_area, true);
        foreach ($areas as $item) {
            if (strpos($item['area'], strval($cityId)) !== false) return true;
        }
        return false;
    }

    /**
     * 此产品是否可以使用积分
     * @return mixed
     */
    public function canUsePoint()
    {
        $config = $this->getPointDeductionConfig();
        return $config->enable;
    }

    /**
     * 判断此产品是否能使用某张优惠券
     * @param $orderMoney 订单商品的总金额
     * @param $couponId
     * @return mixed
     */
    public function canUseCoupon($orderMoney, $couponItemId)
    {
        $canUse = 0;
        $discount = new Discount();
        $item = CouponItemModel::find($couponItemId);
        $coupon = CouponModel::find($item->coupon_id);
        if (!$coupon) {
            return $canUse;
        }
        if (Site::getCurrentSite() && $coupon->site_id != Site::getCurrentSite()->getSiteId()) {
            throw new \Exception('site id not match');
        }
        if ($discount->checkCoupon($coupon, $item)) {
            //检测终端类型
            $terminal = getCurrentTerminal();
            $terminalTypes = myExplode(',', $coupon->terminal_type);
            $terminalOk = in_array($terminal, $terminalTypes);
            //检测会员等级
            $memberOk = true;
            //检测订单金额
            $moneyOk = $orderMoney >= $coupon->doorsill_full_money || $coupon->doorsill_type == 0;
            //检测产品分类
            if (intval($coupon->product_type) == 0) $productOk = true;
            else if(intval($coupon->product_type) == 1)
            {
                $couponProTypes = myExplode(',', $coupon->product_info);
                $proTypes = [];
                foreach ($this->_productModel->productClass as $key => $v) {
                    $proTypes[] = $v->id;
                }
                $productOk = count(array_intersect($couponProTypes, $proTypes));
            }else{
                $couponProTypes = myExplode(',', $coupon->product_info);

                $productOk = in_array($this->_productModel->id, $couponProTypes);
            }
            //添加指定商品检测
            if ($coupon->status == 1 && $terminalOk && $memberOk && $productOk && $moneyOk) {
                $canUse = 1;
            }
        }
        return $canUse;
    }

    /**
     * 判断当前登录会员是否有权限购买此商品
     * @return bool
     */
    public function checkBuyPerm(){
        //检测购买权限
        $pro = new Product($this->productId);
        $checkBuyPerm = $pro->checkBuyPerm();
        return $checkBuyPerm;
    }

    /**
     * 判断会员能否购买此商品，要检测会员限制规则和库存
     * @param int $memberId 会员ID
     * @param int $quantity 订购数量
     * @return array|mixed
     * @throws \Exception
     */
    public function canBuy(int $memberId, int $quantity)
    {
        //检测购买权限
        $checkBuyPerm = $this->checkBuyPerm();
        if ($checkBuyPerm == 0) {
            return makeServiceResult(401, trans('shop-front.shop.product_noperm'), ['noperm' => 1]);
        }

        $locker = new \YZ\Core\Locker\Locker($this->getSkuLockId());
        if ($locker->lock()) {
            try {
                //检测是否已经下架
                $this->_productModel = ProductModel::find($this->productId);
                if ($this->_productModel->status != 1) {
                    $locker->unlock();
                    return makeServiceResult(410, trans('shop-front.shop.product_noactive'), ['need' => $quantity, 'have' => 0]);
                }

                //检测库存
                $checkInventory = $this->canBuyCheckInventory($quantity);

                //检测限购
                $checkLimit = $this->checkBuyLimit();

                //检测最低购买量
                if (($checkMin = $this->checkMinBuyNum()) !== true){
                    $locker->unlock();
                    $max = $checkLimit !== true && $checkInventory['inventory'] > $checkLimit['max'] ? $checkLimit['max'] : $checkInventory['inventory'];
                    if($checkMin['min'] > $checkInventory['inventory'] || ($checkLimit !== true && $checkMin['min'] > $checkLimit['max'])){
                        //如果最低限购量>库存或限购量
                        return makeServiceResult($checkMin['code'],'起购量为'.$checkMin['min'].'件,超出库存量或限购量', ['min' => $checkMin['min'], 'max' => $max]);
                    }else {
                        return makeServiceResult($checkMin['code'],'至少购买' . $checkMin['min'] . '件哦~', ['min' => $checkMin['min'], 'max' => $max]);
                    }
                }

                //检测限购返回
                if ($checkLimit !== true){
                    $locker->unlock();
                    $max = $checkLimit['max'] > $checkInventory['inventory'] ? $checkInventory['inventory'] : $checkLimit['max'];
                    return makeServiceResult($checkLimit['code'], '最多只能购买'.$max.'件哦~', ['max' => $max]);
                }

                //检测库存返回
                if (!$checkInventory['check']) {
                    $locker->unlock();
                    return makeServiceResult(413, trans('shop-front.shop.inventory_not_enough'), ['need' => $quantity, 'have' => $checkInventory['inventory']]);
                } else {
                    $locker->unlock();
                    return makeServiceResult(200, 'ok');
                }
            } catch (\Exception $e) {
                $locker->unlock();
                throw $e;
            }
        } else {
            throw new \Exception('can not init locker');
        }
    }

    /**
     * 检测是否满足限购条件
     * @return bool|array
     */
    public function checkBuyLimit(){
        if($this->_productModel->buy_limit_status){
            $have = $this->getBuyNumWithLimit();
            if($have + $this->num > $this->_productModel->buy_limit_num){
                $max = $this->_productModel->buy_limit_num - $have;
                if($max < 0) $max = 0;
                return ['code' => 414,'max' => $max];
            }
        }
        return true;
    }

    private function getBuyNumWithLimit(){
        $timeStart = "1970-01-01 00:00:00";
        $timeEnd = date('Y-m-d 23:59:59');
        if($this->_productModel->buy_limit_type == 1){ //每天
            $timeStart = date('Y-m-d 00:00:00');
        }
        if($this->_productModel->buy_limit_type == 2){ //每周
            $timeStart = date("Y-m-d 00:00:00", strtotime('monday this week'));
        }
        if($this->_productModel->buy_limit_type == 3){ //每月
            $timeStart = date('Y-m-01 00:00:00');
        }
        if($this->_productModel->buy_limit_type == 4){ //每季
            if(in_array(intval(date('m')),[1,2,3])) $m = 01;
            if(in_array(intval(date('m')),[4,5,6])) $m = 04;
            if(in_array(intval(date('m')),[7,8,9])) $m = 07;
            if(in_array(intval(date('m')),[10,11,12])) $m = 10;
            $timeStart = date('Y-'.$m.'-01 00:00:00');
        }
        if($this->_productModel->buy_limit_type == 5){ //每年
            $timeStart = date('Y-01-01 00:00:00');
        }
        $sql = "select sum(item.num) as total from tbl_order_item as item left join tbl_order as o on o.id = item.order_id ";
        $sql .= "where o.status in (".implode(',',BaseShopOrder::getPaidStatusList()) .") ";
        $sql .= "and o.created_at >= '".$timeStart."' and o.created_at <= '".$timeEnd."' ";
        $sql .= "and item.product_id = '".$this->productId."' ";
        $sql .= "and item.sku_id = '".$this->skuId."' ";
        $sql .= "and o.member_id = ".Auth::hasLogin()." ";
        $res = BaseModel::runSql($sql);
        $have = intval($res[0]->total);
        return $have;
    }

    /**
     * 结合当前登录会员获取商品的限购量
     * @param int $checkInventory 是否需要根据库存量返回最大值
     * @return int -1表示不限制
     */
    public function getBuyLimit(){
        if($this->_productModel->buy_limit_status) {
            $have = $this->getBuyNumWithLimit();
            $max = $this->_productModel->buy_limit_num - $have;
            if($max > $this->_productSku->inventory) $max = $this->_productSku->inventory;
            return $max;
        }
        return -1;
    }

    /**
     * 检测是否满足最低起购量
     * @return bool|array
     */
    public function checkMinBuyNum(){
        if($this->_productModel->min_buy_num > 1 && $this->num < $this->_productModel->min_buy_num){
            return ['code' => 415,'min' => $this->_productModel->min_buy_num];
        }
        return true;
    }

    /**
     * 结合当前登录会员获取商品的起购量
     * @return int -1表示不限制
     */
    public function getMinBuyNum(){
        if($this->_productModel->min_buy_num > 1){
            return $this->_productModel->min_buy_num;
        }
        return 1;
    }

    /**
     * 检测库存 用于判断是否可以购买
     * @param $quantity
     * @return array
     */
    public function canBuyCheckInventory($quantity)
    {
        $this->initProductSku(); //重新查找sku信息，保证信息是最新的
        return [
            'check' => $quantity <= $this->_productSku->inventory, // 库存是否满足
            'inventory' => $this->_productSku->inventory // 现有库存
        ];
    }

    /**
     * 单独检测产品上架下架状态
     * @return mixed
     */
    public function checkStatus()
    {
        $locker = new \YZ\Core\Locker\Locker($this->getSkuLockId());
        if ($locker->lock()) {
            $this->_productModel = ProductModel::find($this->productId);
            if ($this->_productModel->status != 1) {
                $locker->unlock();
                return false;
            }else{
                $locker->unlock();
                return true;
            }

        } else {
            throw new \Exception('can not init locker');
        }
    }

    /**
     * 单独检测库存
     * @param int $quantity 订购数量
     * @return boolean  fasle 库存不足 true 库存充足
     */
    public function checkInventory(int $quantity)
    {
        $locker = new \YZ\Core\Locker\Locker($this->getSkuLockId());
        if ($locker->lock()) {
            //检测库存
            $this->initProductSku(); //重新查找sku信息，保证信息是最新的
            if ($quantity > $this->_productSku->inventory) {
                $locker->unlock();
                return false;
            } else {
                $locker->unlock();
                return true;
            }
        } else {
            throw new \Exception('can not init locker');
        }
    }



    /**
     * 获取此商品的积分抵扣设置
     * @return PointDeductionConfig
     */
    public function getPointDeductionConfig(): PointDeductionConfig
    {
        return PointDeductionConfig::getPointDeductionWithProduct($this->productId, $this->skuId);
    }

    /**
     * 获取此商品的分销设置规则
     * @return DistributionConfig
     */
    public function getDistributionConfig(): DistributionConfig
    {
        return DistributionConfig::getProductDistributionConfig($this->skuId);
    }

    /**
     * 计算此商品的分销金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额，格式如
     * ['memberId1' => money1,'memberId2' => money2]
     */
    public function calDistribution(int $memberId): array
    {
        if ($this->isCommissionProduct) return []; //分销商品不分钱
        if ($this->_productSku->fenxiao_rule == -1) return [];
        $config = $this->getDistributionConfig();
        $d = new Distribution();
        //echo 'money='.$this->money.',num='.$this->num.',coupon='.$this->couponMoney.',point='.$this->pointMoney.',cost='.$this->costMoney.'<br>';
        $res = $d->calDistributionMoney($memberId,  $this->totalMoney, $this->costMoney * $this->num, $this->num, $config);
        return $res;
    }

    /**
     * 获取此商品的正常订单代理分佣规则
     * @return AgentOrderCommisionConfig
     */
    public function getAgentOrderCommisionConfig(): AgentOrderCommisionConfig
    {
        return AgentOrderCommisionConfig::getProductCommisionConfig($this->skuId);
    }

    /**
     * 计算此商品的正常团队代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentOrderCommision(int $memberId): array
    {
        $config = $this->getAgentOrderCommisionConfig();
        if (intval($this->_productSku->agent_order_commission_rule == -1)) return [];
        $res = AgentReward::calOrderCommisionMoney($memberId, $this->totalMoney, $this->costMoney * $this->num, $this->num, $config);
        return $res;
    }

    /**
     * 获取此商品的平级/越级代理分佣规则
     * @return AgentSaleRewardCommisionConfig
     */
    public function getAgentSaleRewardCommisionConfig(): AgentSaleRewardCommisionConfig
    {
        return AgentSaleRewardCommisionConfig::getProductCommisionConfig($this->skuId);
    }

    /**
     * 计算此商品的平级/越级代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentSaleRewardCommision(int $memberId): array
    {
        $config = $this->getAgentSaleRewardCommisionConfig();
        if (intval($this->_productSku->agent_sale_reward_rule == -1)) return [];
        $res = AgentReward::calSaleRewardCommisionMoney($memberId, $this->totalMoney, $this->costMoney * $this->num, $this->num, $config);
        return $res;
    }

    /**
     * 获取此商品的区域代理分佣规则
     * @return AreaAgentCommissionConfig
     */
    public function getAreaAgentCommissionConfig(): AreaAgentCommissionConfig
    {
        return AreaAgentCommissionConfig::getProductCommissionConfig($this->skuId);
    }

    /**
     * 计算此商品的区域代理佣金
     * @param int $memberId 购买者的会员ID
     * @param int $areaId 收货人的县/区ID
     * @return array
     */
    public function calAreaAgentCommission(int $memberId,int $areaId): array
    {
        $config = $this->getAreaAgentCommissionConfig();
        if (intval($this->_productSku->area_agent_rule == -1)) return [];
        $res = AreaAgentCommission::calCommissionMoney($memberId,$areaId,$this->totalMoney, $this->costMoney * $this->num, $this->num, $config);
        return $res;
    }

    /**
     * 获取此产品在订购时需要记录的快照信息
     * @return mixed
     */
    public function getSnapShotInfo()
    {
        return [
            'freight' => $this->_freightModel,
            'member_rule' => $this->_memberPriceRule,
            'point_config' => $this->getPointDeductionConfig(),
            'distribution_config' => $this->getDistributionConfig(),
            'agent_order_commision_config' => $this->getAgentOrderCommisionConfig(),
            'agent_sale_reward_commision_config' => $this->getAgentSaleRewardCommisionConfig(),
            'area_agent_commission_config' => $this->getAreaAgentCommissionConfig(),
        ];
    }

    /**
     * 获取当前的产品model
     * @return \LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection|null
     */
    public function getThisProductModel()
    {
        return $this->_productModel;
    }

    /**
     * 获取当前的sku model
     * @return null
     */
    public function getThisProductSkuModel()
    {
        return $this->_productSku;
    }

    /**
     * 获取当前产品的分类信息
     * @return mixed
     */
    public function getThisProductClass()
    {
        return $this->_productModel->productClass;
    }

    public function getThisProductInfo()
    {

    }

    /**
     * 是否为虚拟商品
     *
     * @return boolean
     */
    public function isVirtual()
    {
        return intval($this->_productModel->type) === 1 || intval($this->_productModel->type) === 9;
    }
}
