<?php

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;
use App\Modules\ModuleShop\Jobs\UpgradeDistributionLevelJob;
use App\Modules\ModuleShop\Jobs\UpgradeMemberLevelJob;
use App\Modules\ModuleShop\Libs\Activities\FreeFreight;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentOtherReward;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentBaseSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentPerformance;
use App\Modules\ModuleShop\Libs\Distribution\Distribution;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentCommission;
use App\Modules\ModuleShop\Libs\Express\ExpressHelper;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentBaseSettingModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use App\Modules\ModuleShop\Libs\Point\Point;
use App\Modules\ModuleShop\Libs\Point\PointGiveHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\Become\BecomeDistributorHelper;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\CouponModel;
use App\Modules\ModuleShop\Libs\Model\CouponItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkuValueModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForConsume;
use App\Modules\ModuleShop\Libs\Point\PointConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Locker\Locker;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberAddressModel;
use YZ\Core\Model\PointModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Point\PointHelper;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Agent\AgentReward;
use App\Modules\ModuleShop\Libs\Statistics\Statistics;
use YZ\Core\Task\TaskHelper;

/**
 * 基础订单类
 * Class BaseOrder
 * @package App\Modules\ModuleShop\Libs\Shop
 */
class BaseShopOrder implements IShopOrder
{
    public $productMoney = 0; //产品本身的价格
    public $productCostMoney = 0; //产品的成本价格
    public $couponMoney = 0; //优惠券抵扣的金额
    public $pointMoney = 0; //积分抵扣的金额
    public $pointUsed = 0; //使用了多少积分
    public $otherDiscount = 0; // 其他优惠 比如活动的优惠
    public $freight = 0; //运费
    public $supplierFreight = []; //供应商的运费
    public $totalMoney = 0; // 总金额
    public $originalMoney = 0; // 订单的原始总金额（没有经过优惠运算的原始商品总金额，不含运费）
    protected $_memberId = 0; //会员ID
    protected $_memberObj = null; // 会员数据
    protected $_addressId = 0; //送货地址ID
    protected $_addressModel = null; // 送货地址model
    protected $_couponItemId = 0; //使用的优惠券的ID
    protected $_productList = []; //订单中的产品列表
    protected $_orderModel = null; //订单表记录
    protected $_remark = ''; //备注信息
    protected $_usePoint = false; // 是否使用积分
    protected $_snapshots = []; // 订单快照
    protected $_type = Constants::OrderType_Normal; // 订单类型默认为普通订单

    /**
     * 初始化
     * constructor.
     */
    public function __construct($memberId = 0)
    {
        $this->initMember($memberId);
    }

    protected function initMember($memberId)
    {
        if ($memberId) {
            $this->_memberId = $memberId;
            $this->_memberObj = new Member($this->_memberId);
        }
    }

    /**
     * @param $params 可能需要的参数
     * 根据订单id 初始化的时候 设置其他参数
     */
    public function initByOrderIdOtherParams($params = null)
    {

    }

    /**
     * 获取其他参数 暂时给根据id初始化订单时用
     * @return array
     */
    public function getOtherParams()
    {
        return [];
    }

    /**
     * @param $orderIdOrModel
     * @param bool $initProduct 是否需要初始化商品
     * @return mixed|void
     * @throws \Exception
     */
    public function initByOrderId($orderIdOrModel, $initProduct = true)
    {
        if (is_string($orderIdOrModel)) {
            $this->_orderModel = OrderModel::find($orderIdOrModel);
            if ($this->_memberId && $this->_orderModel->member_id != $this->_memberId) {
                throw new \Exception('订单会员ID不匹配');
            }
        } else {
            $this->_orderModel = $orderIdOrModel;
        }
        $this->initMember($this->_orderModel->member_id);
        $this->setOrderType($this->_orderModel->type);
        if ($initProduct) {
            $type = $this->_orderModel->type;
            $items = $this->_orderModel->items;
            $this->initByOrderIdOtherParams($items);
            foreach ($items as $item) {
                $skuId = $type == Constants::OrderType_GroupBuying ? $item->activity_sku_id : $item->sku_id;
                $pro = ShopProductFactory::createShopProduct($item->product_id, $skuId, $item->num, $type, $this->getOtherParams());
                $pro->money = $item->price;
                $pro->costMoney = $item->cost;
                $pro->couponMoney = $item->coupon_money;
                $pro->pointMoney = $item->point_money;
                $pro->pointUsed = $item->point_used;
                $pro->freight = $item->freight;
                $pro->isCommissionProduct = $item->is_commission_product;
                $pro->oriPrice = $item->ori_price;
                $pro->supplierPrice = $item->supplier_price;
                $pro->supplierMemberId = $item->supplier_member_id;
                $pro->totalMoney = $item->total_money;
                $this->addProduct($pro);
            }
            $this->couponMoney = $this->_orderModel->coupon_money;
            $this->productMoney = $this->_orderModel->product_money;
            $this->pointMoney = $this->_orderModel->point_money;
            $this->productCostMoney = $this->_orderModel->product_cost;
            $this->pointUsed = $this->_orderModel->point;
            $this->freight = $this->_orderModel->freight;
            $this->totalMoney = $this->_orderModel->money;
            $this->originalMoney = $this->_orderModel->original_money;
            $this->setAddressId($this->_orderModel->address_id);
            $this->setCouponID($this->_orderModel->coupon_item_id);
        }
    }

    /**
     * 返回代表已付款的订单的状态列表
     * @return array
     */
    public static function getPaidStatusList()
    {
        return [
            Constants::OrderStatus_OrderPay,
            Constants::OrderStatus_OrderSend,
            Constants::OrderStatus_OrderSuccess,
            Constants::OrderStatus_OrderReceive,
            Constants::OrderStatus_OrderFinished
        ];
    }

    /**
     * 返回代表尚未过维权期的订单状态
     * @return array
     */
    public static function getNoFinishStatusList()
    {
        return [
            Constants::OrderStatus_OrderPay,
            Constants::OrderStatus_OrderSend,
            Constants::OrderStatus_OrderSuccess,
            Constants::OrderStatus_OrderReceive,
        ];
    }

    /**
     * 返回代表已成交的订单的状态列表
     * @return array
     */
    public static function getDealStatusList()
    {
        return [
            Constants::OrderStatus_OrderFinished
        ];
    }

    /**
     * 设置订单的送货地址
     * @param $addressId 用户收货地址ID
     * @return mixed
     */
    public function setAddressId($addressId)
    {
        if ($this->getVirtualFlag() !== 1)
            $this->_addressId = $addressId;
    }

    /**
     * 添加备注信息
     * @param $remark
     */
    public function setRemark($remark)
    {
        $this->_remark = $remark;
    }

    /**
     * 设置订单使用的优惠券
     * @param $couponItemId 优惠券的ID
     * @return mixed
     */
    public function setCouponID($couponItemId)
    {
        $this->_couponItemId = $couponItemId;
    }

    /**
     * 检测此订单是否能使用某张优惠券，要先调用 calProductMoney() 再调用此方法
     * @param $couponItemId 优惠券的ID
     * @return mixed
     */
    public function canUseCoupon($couponItemId)
    {
        $orderMoney = $this->productMoney;
        foreach ($this->_productList as $item) {
            if ($item->canUseCoupon($orderMoney, $couponItemId)) return true;
        }
        return false;
    }

    /**
     * 计算订单的产品金额(不含优惠部分)
     * @return mixed
     */
    public function calProductMoney()
    {
        $this->productMoney = 0;
        foreach ($this->_productList as $item) {
            $this->productMoney += $item->calPrice($this->_memberId);
        }
        return $this->productMoney;
    }

    /**
     * @return int 计算订单的产品成本价
     */
    public function calProductCostMoney()
    {
        $this->productCostMoney = 0;
        foreach ($this->_productList as $item) {
            $this->productCostMoney += $item->costMoney * intval($item->num);
        }
        return $this->productCostMoney;
    }

    /**
     * 计算订单的总运费
     * @return float|int|mixed
     * @throws \Exception
     */
    public function calFreight()
    {
        $this->freight = 0;
        $totalMoneyExceptFreight = $this->calTotalMoney();
        $this->supplierFreight = [];
        $address = MemberAddressModel::find($this->_addressId);
        $splitProducts = OrderHelper::splitSupplierProduct($this->_productList);
        foreach ($splitProducts as $supplierMemberId => $list) {
            $freight = new BaseCalOrderFreight($address['city'], $list);
            $money = $freight->getOrderFreight();
            $this->supplierFreight['supplier_' . $supplierMemberId] = $money;
            $this->freight += $money;
        }
        //满额包邮(注意要先调用 calTotalMoney() 也就是要先算好商品价格和优惠，否则这里的 $totalMoneyExceptFreight 会为0)
        $productIds = [];
        $freeFreight = (new FreeFreight())->getWithProducts([$productIds]);
        if ($freeFreight && $freeFreight->status == 1 && $totalMoneyExceptFreight >= $freeFreight->money) {
            $this->freight = 0;
            foreach ($this->supplierFreight as $k => $v) {
                $this->supplierFreight[$k] = 0;
            }
        }

        return $this->freight;
    }

    /**
     * 计算订单的积分，要先调用 calProductMoney(),calCoupon() 再调用此方法
     * @param bool $use 是否使用积分
     * @param bool $getCanUse 不使用积分时 是否返回可以使用的积分数据
     * @return mixed
     */
    public function calPoint($use = true, $getCanUse = false)
    {
        $usePointMoney = 0;
        $usePoint = 0;
        $this->_usePoint = $use;
        if ($use || $getCanUse) {
            //要先统计可以使用积分的产品的总额(排除不能使用积分的产品)
            $orderMoney = 0;
            $canUsePointList = [];
            foreach ($this->_productList as $item) {
                if ($item->canUsePoint()) {
                    $orderMoney += $item->getProductMoney() - $item->couponMoney;
                    $canUsePointList[] = $item;
                }
            }
            //第一轮，先按单品算出最大可能可以用的积分
            $myPoint = PointHelper::getPointBalance($this->_memberId);
            $productPointArr = [];
            foreach ($canUsePointList as $item) {
                $res = $item->calPoint($orderMoney, $myPoint, $use);
                $usePointMoney += $res['money'];
                $usePoint += $res['usedPoint'];
                $productPointArr[$item->productId] = $res['usedPoint'];
            }
            //第二轮，因为每个单品都是按当前积分余额算的，所以单品累加可能会超出积分余额总数，如果单品的累加起来超过积分余额，要按比例再算一次
            if ($usePoint > $myPoint) {
                $needPoint = $usePoint;
                $usePointMoney = 0;
                $usePoint = 0;
                foreach ($canUsePointList as $item) {
                    $productPoint = $productPointArr[$item->productId] * ($myPoint / $needPoint);
                    $res = $item->calPoint($orderMoney, $productPoint, $use);
                    $usePointMoney += $res['money'];
                    $usePoint += $res['usedPoint'];
                }
            }
            if ($use) {
                $this->pointMoney = $usePointMoney;
                $this->pointUsed = $usePoint;
            }
        }
        return ['money' => $usePointMoney, 'usedPoint' => $usePoint];
    }

    /**
     * 计算订单的优惠券金额，要先调用 calProductMoney() 再调用此方法
     * @return mixed
     */
    public function calCoupon()
    {
        $this->couponMoney = 0;
        // 没有使用优惠券 直接返回
        if (!$this->_couponItemId) {
            return $this->couponMoney;
        }
        //要先统计可以使用优惠券的产品的总额(排除不能使用优惠券的产品)
        $orderMoney = 0;
        $canUseCouponList = [];
        foreach ($this->_productList as $item) {
            if ($item->canUseCoupon($this->productMoney, $this->_couponItemId)) {
                $orderMoney += $item->getProductMoney();
                $canUseCouponList[] = $item;
            }
        }
        $last = null;
        foreach ($canUseCouponList as $product) {
            $this->couponMoney += $product->calCoupon($orderMoney, $this->_couponItemId);
            $last = $product;
        }
        $couponItem = CouponItemModel::find($this->_couponItemId);
        $couponInfo = CouponModel::find($couponItem->coupon_id);

        if ($couponInfo->coupon_type == 0) {
            // 优惠券金额如果比订单总额大  需要重新设置优惠券的金额为订单金额
            $couponInfo->coupon_money = $couponInfo->coupon_money > $orderMoney ? $orderMoney : $couponInfo->coupon_money;
            // 如果是现金券 会有金额对不上的情况  需要补齐金额
            if ($this->couponMoney < $couponInfo->coupon_money) {
                $last->couponMoney += ($couponInfo->coupon_money - $this->couponMoney);
                $this->couponMoney = $couponInfo->coupon_money;
            }
        }

        return $this->couponMoney;
    }

    /**
     * 计算订单的其它优惠（可能包含算优惠券，算积分，以及其它优惠）
     * @return mixed
     */
    public function calDiscount()
    {
        $this->calCoupon();
        $this->calPoint($this->_usePoint);
        $this->calOtherDiscount();
    }

    /**
     * 计算其他的优惠
     */
    public function calOtherDiscount()
    {

    }

    /**
     * 获取所有的优惠金额
     * @return int
     */
    public function getAllDiscount()
    {
        return $this->couponMoney + $this->pointMoney + $this->otherDiscount;
    }

    /**
     * 计算分销佣金
     * @return mixed 数组，格式如
     * [
     *  memberId1 = ['chain' => 分钱的链条,'money' => 金额],
     *  memberId2 = ['chain' => 分钱的链条,'money' => 金额]
     * ]
     */
    public function calDistribution()
    {
        $res = [];
        foreach ($this->_productList as $item) {
            $tmpres = $item->calDistribution($this->_memberId);
            //保存产品分销计算结果到订单产品项
            if ($this->_orderModel) {
                $updateFields = ['commission' => json_encode($tmpres)];
                $dbItem = OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->first();
                if ($dbItem) {
                    $snapshot = json_decode($dbItem->snapshot, true);
                    $snapshot['distribution_config'] = $item->getDistributionConfig();
                    $updateFields['snapshot'] = json_encode($snapshot);
                }
                OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->update($updateFields);
            }
            foreach ($tmpres as $item) {
                if (!$res[$item['member_id']]) {
                    $res[$item['member_id']] = [];
                    $res[$item['member_id']]['chain'] = $item['chain'];
                    $res[$item['member_id']]['floor_level'] = $item['floor_level'];
                }
                $res[$item['member_id']]['money'] += $item['money'];
            }
        }
        //重新封装数据格式与 order_item 表一致
        $commission = [];
        foreach ($res as $memberId => $item) {
            $commission[] = ['member_id' => $memberId, 'money' => $item['money'], 'chain' => $item['chain'], 'floor_level' => $item['floor_level']];
        }
        return $commission;
    }

    public function addProduct(IShopProduct $pro)
    {
        $this->_productList[] = $pro;
    }

    /**
     * 获取订单的产品列表
     * @return array
     */
    public function getProductList()
    {
        return $this->_productList;
    }

    /**
     * 获取 未保存 订单中产品的信息
     * @return array
     */
    public function getProductListInfo()
    {
        $member = (new Member($this->_memberId))->getInfo();
        $productList = $this->_productList;
        // 获取产品仲所有供应商的名称
        $supplier = $this->getProductListSupplierName();
        $productListInfo = [];
        foreach ($productList as $pro) {
            $item = $pro->getThisProductModel()->toArray();
            if ($pro->getThisProductSkuModel()) {
                $sku = $pro->getThisProductSkuModel()->toArray();
            } else {
                $sku = [];
            }
            // 取出来第一张图片
            $image = explode(',', $item['small_images']);
            $item['image'] = $image[0];
            unset($item['small_images'], $item['detail'], $item['params']);
            // 查找sku的name
            if ($sku['sku_code'] != '0') {
                $values = explode(',', trim($sku['sku_code'], ','));
                $skuInfo = ProductSkuValueModel::query()->whereIn('id', $values)->select(['value', 'small_image'])->get();
                $skuName = $skuInfo->pluck('value')->all(); // sku的名称
                $smallImage = $skuInfo->pluck('small_image')->filter(); // 获取sku图片
                $smallImage = $smallImage->count() > 0 ? implode('', $smallImage->all()) : '';
                $item['sku_name'] = implode(' ', $skuName);
                $item['image'] = $smallImage ?: $item['image'];
            } else {
                $item['sku_name'] = '';
            }
            $item['sku_id'] = $sku['id'];
            $item['num'] = $pro->num;
            // 计算产品的会员价
            $item['price'] = moneyCent2Yuan($pro->getMemberPrice($member->level));
            if ($supplier && $item['supplier_member_id'] > 0) {
                $item['supplier_name'] = $supplier[$item['supplier_member_id']];
            }
            $productListInfo[] = $item;
        }
        return $productListInfo;
    }

    /**
     * 对 未保存 订单中产品的所用供应商
     * @return array
     */
    public function getProductListSupplierName()
    {
        $productList = $this->_productList;
        // 拿取所有供应商的
        $supplierMember = [];
        foreach ($productList as $pro) {
            $item = $pro->getThisProductModel()->toArray();
            if ($item['supplier_member_id']) {
                array_push($supplierMember, $item['supplier_member_id']);
            }
        }
        if (count($supplierMember) > 0) {
            $supplierMemberUnique = array_unique($supplierMember);
            $supplierMemberArray = SupplierModel::query()
                ->where('site_id', getCurrentSiteId())
                ->whereIn('member_id', $supplierMemberUnique)
                ->pluck('name', 'member_id')
                ->all();
        }
        return $supplierMemberArray;
    }

    /**
     * 获取订单model
     * @return orderModel
     */
    public function getOrderModel()
    {
        return $this->_orderModel;
    }

    /**
     * 订单保存之前做一下对比
     * @param array|null $params 现在只有originMoneyData和goBuy  用来对比订单金额是否有变化
     * @return array|bool|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function beforeSave($params)
    {
        $originMoney = $params['originMoneyData'];
        if (moneyCent2Yuan($this->couponMoney) != $originMoney['couponMoney']) {
            return trans('shop-front.shop.coupon_status_change');
        }
        if (
            $this->_usePoint &&
            ($this->pointUsed != $originMoney['point']['usedPoint']
                || moneyCent2Yuan($this->pointMoney) != $originMoney['point']['money'])
        ) {
            return trans('shop-front.shop.point_status_change');
        }
        if (moneyCent2Yuan($this->calTotalMoney()) != $originMoney['totalPrice']) {
            //return $this->calTotalMoney();
            return trans('shop-front.shop.order_money_change');
        }
        return false;
    }

    /**
     * 检测是否可以购买
     * @return array
     */
    public function checkCanBuy()
    {
        $notActiveList = []; // 下架列表
        $notEnoughList = []; // 库存不足的列表
        $notHasPermList = []; // 无权购买的列表
        $overQuotaList = []; // 超出限购的列表
        $notReachedMinimumList = []; // 没达到最少起购量的列表
        $minimumMoreThanLimitList = []; // 起购量大于限购或库存的
        foreach ($this->_productList as $item) {
            $res = $item->canBuy($this->_memberId, $item->num);
            if ($res['code'] != 200) {
                if ($res['data']['noperm']) $notHasPermList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId];
                elseif ($res['code'] == '414') {
                    //超限购的，暂时将其归到库存不足或失效里处理
                    //$overQuotaList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId,'max' => $res['data']['max']];
                    if ($res['data']['max'] < 1) {
                        //$notActiveList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId,'reason' => '您已购买数量超出限购数量'];
                        $overQuotaList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId, 'max' => $res['data']['max']];
                    } else {
                        $notEnoughList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId, 'have' => $res['data']['max']];
                    }
                } elseif ($res['code'] == '415') {
                    if ($res['data']['min'] < $res['data']['have']) {
                        $notReachedMinimumList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId, 'min' => $res['data']['min']];
                    } else {
                        $minimumMoreThanLimitList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId, 'min' => $res['data']['min']];
                    }
                } elseif (!$res['data']['have']) $notActiveList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId];
                else $notEnoughList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId, 'have' => $res['data']['have']];
            }
        }
        return [
            'notActiveList' => $notActiveList, // 下架列表
            'notEnoughList' => $notEnoughList, // 库存不足列表
            'notHasPermList' => $notHasPermList, // 无权购买列表
            'overQuotaList' => $overQuotaList, // 超出限购的列表
            'notReachedMinimumList' => $notReachedMinimumList, // 没达到最少起购量的列表,
            'minimumMoreThanLimitList' => $minimumMoreThanLimitList, // 起购量大于限购或库存的
        ];
    }

    public function getAddressModel()
    {
        if ($this->_addressModel === null) {
            $this->_addressModel = MemberAddressModel::find($this->_addressId);
        }
        return $this->_addressModel;
    }

    /**
     * 检测送货地址
     * @return array 不配送的商品
     */
    public function checkDelivery()
    {
        // 检测送货地址
        $notDeliveryList = [];
        foreach ($this->_productList as $item) {
            if (!$item->isVirtual() && !$item->canDelivery($this->getAddressModel()->city)) {
                $notDeliveryList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId];
            }
        }
        return $notDeliveryList;
    }

    /**
     * 检测优惠是否已经被使用
     * @return array|bool
     */
    public function checkCoupon()
    {
        $data = [
            'citem' => null,
            'coupon' => null
        ];
        if ($this->_couponItemId) {
            $locker = new \YZ\Core\Locker\Locker($this->_couponItemId);
            if (!$locker->lock()) {
                return makeServiceResult(500, 'can not init coupon locker');
            }
            $data['ctiem'] = $citem = CouponItemModel::find($this->_couponItemId);
            $data['coupon'] = $coupon = CouponModel::find($citem->coupon_id);
            $locker->unlock();
            $discount = new Discount();
            if (!$discount->checkCoupon($coupon, $citem)) {
                return makeServiceResult(402, trans('shop-front.shop.coupon_invalid'));
            }
        }
        return makeServiceResult(200, 'ok', $data);
    }

    /**
     * 设置快照
     * @param $data
     */
    public function setSnapshots($data)
    {
        $this->_snapshots = [
            'coupon' => $data['coupon'],
            'coupon_item' => $data['citem'],
            'point_config' => (new PointConfig(Site::getCurrentSite()->getSiteId()))->getInfo(),
            'address' => $this->getAddressModel()
        ];
    }

    /**
     * 获取快照
     * @return array
     */
    public function getSnapshots()
    {
        return $this->_snapshots;
    }

    /**
     * 设置订单类型
     * @param $type
     */
    public function setOrderType($type)
    {
        $this->_type = $type;
    }

    /**
     * 获取订单类型
     * @return int
     */
    public function getOrderType()
    {
        return $this->_type;
    }

    /**
     * 保存订单数据
     * @param array $params 额外保存的数据 分为两个 order_data 和 product_data
     * @return string
     */
    public function saveOrderData($params = [])
    {
        $address = $this->getAddressModel();
        $mOrder = new OrderModel();
        $orderId = generateOrderId();
        $mOrder->id = $orderId;
        $mOrder->site_id = Site::getCurrentSite()->getSiteId();
        $mOrder->store_id = 0;
        $mOrder->member_id = $this->_memberId;
        $mOrder->status = 0;
        $mOrder->type = $this->getOrderType();
        $mOrder->product_cost = $this->productCostMoney;
        $mOrder->product_money = $this->productMoney;
        $mOrder->freight = $this->freight;
        $mOrder->ori_freight = $this->freight;
        $mOrder->supplier_freight = json_encode($this->supplierFreight);
        $mOrder->point = $this->pointUsed;
        $mOrder->point_money = $this->pointMoney;
        $mOrder->coupon_item_id = $this->_couponItemId;
        $mOrder->coupon_money = $this->couponMoney;
        $mOrder->money = $this->calTotalMoney();
        $mOrder->terminal_type = getCurrentTerminal();
        $mOrder->transaction_id = '';
        $mOrder->address_id = $this->_addressId;
        $mOrder->receiver_address = $address->address;
        $mOrder->receiver_name = $address->name;
        $mOrder->receiver_tel = $address->phone;
        $mOrder->remark = $this->_remark;
        $mOrder->created_at = date('Y-m-d H:i:s');
        $mOrder->snapshot = json_encode($this->getSnapshots());
        // 是否虚拟订单
        $mOrder->virtual_flag = $this->getVirtualFlag();
        // 填充额外数据
        if ($params['order_data'] && is_array($params['order_data'])) {
            foreach ($params['order_data'] as $field => $data) {
                $mOrder->{$field} = $data;
            }
        }

        $mOrder->save();
        $this->_orderModel = OrderModel::find($orderId);
        // 获取分销配置
        $distributionConfig = DistributionSetting::getCurrentSiteSetting();
        // 保存订单商品列表
        $supplierIds = [];
        foreach ($this->_productList as $item) {
            $mItem = new OrderItemModel();
            $mItem->site_id = Site::getCurrentSite()->getSiteId();
            $mItem->order_id = $orderId;
            $mItem->product_id = $item->productId;
            $mItem->sku_id = $item->skuId;
            $mItem->name = $item->name;
            $mItem->image = $item->image;
            $mItem->sku_names = json_encode($item->skuNames, JSON_UNESCAPED_UNICODE);
            $mItem->type = Constants::ShopProductType_Normal;
            $mItem->cost = $item->costMoney;
            $mItem->price = $item->money;
            $mItem->num = $item->num;
            $mItem->point_used = $item->pointUsed;
            $mItem->point_money = $item->pointMoney;
            $mItem->coupon_money = $item->couponMoney;
            $mItem->freight = $item->freight;
            $mItem->is_virtual = $item->isVirtual();
            $mItem->snapshot = json_encode($item->getSnapShotInfo());
            // 当前是否是分销商品
            if ($distributionConfig['level'] > 0 && $item->fenxiaoRule != -1) {
                $mItem->has_commission_product = 1;
            } else {
                $mItem->has_commission_product = 0;
            }
            $mItem->product_after_sale_setting = $item->after_sale_setting;
            // 填充额外数据
            if ($params['product_data'] && is_array($params['product_data'])) {
                foreach ($params['product_data'] as $field => $data) {
                    $mItem->{$field} = $data;
                }
            }
            $mItem->ori_price = $item->oriPrice;
            $mItem->supplier_price = $item->supplierPrice;
            $mItem->supplier_member_id = $item->supplierMemberId;
            $mItem->save();
            $supplierIds[$item->supplierMemberId] = 1;
        }
        //更新订单的所属供应商
        if (count($supplierIds) > 1) $this->_orderModel->supplier_member_id = -1; //供应商的自营混合
        else $this->_orderModel->supplier_member_id = array_keys($supplierIds)[0];
        $this->_orderModel->save();
        return $orderId;
    }

    /**
     * 扣减库存
     * @return array 返回库存预警的数据
     */
    public function reduceInventory()
    {
        $stockWarnList = [];
        foreach ($this->_productList as $item) {
            $locker = new \YZ\Core\Locker\Locker($item->getSkuLockId());
            if (!$locker->lock()) {
                return makeServiceResult(500, 'can not init sku locker');
            }
            $sendMessageStockWarn = false; // 是否需要发送预警通知
            $productModel = $item->getThisProductModel();
            $warningInventory = intval($productModel->warning_inventory); // 库存预警值
            if ($item->skuId) {
                $productSku = ProductSkusModel::where(['product_id' => $item->productId, 'id' => $item->skuId])->first();
                if ($warningInventory > 0 && $productSku->inventory > $warningInventory) {
                    $sendMessageStockWarn = $productSku->inventory - intval($item->num) <= $warningInventory;
                }
                $productSku->decrement('inventory', $item->num);
                // 如果sku的库存为0了，要检测一下产品是否已售罄
                if ($productSku->inventory <= 0) {
                    $hasInventory = ProductModel::where('id', $item->productId)
                        ->whereHas('productSkus', function ($query) {
                            $query->where('inventory', '>', 0);
                        })->count();
                    // 没有库存大于0的sku 说明没有库存 修改状态为已售罄
                    if ($hasInventory == 0) {
                        ProductModel::where('id', $item->productId)
                            ->update([
                                'is_sold_out' => \YZ\Core\Constants::Product_Sold_Out,
                                'sold_out_at' => Carbon::now()
                            ]);
                    }
                }
            } else {
                $productSku = ProductSkusModel::where(['product_id' => $item->productId, 'sku_code' => 0])->first();
                if ($warningInventory > 0 && $productSku->inventory > $warningInventory) {
                    $sendMessageStockWarn = $productSku->inventory - intval($item->num) <= $warningInventory;
                }
                $productSku->decrement('inventory', $item->num);
                // 修改产品的状态为已售罄
                if ($productSku->inventory <= 0) {
                    ProductModel::where('id', $item->productId)
                        ->update([
                            'is_sold_out' => \YZ\Core\Constants::Product_Sold_Out,
                            'sold_out_at' => Carbon::now()
                        ]);
                }
            }
            // 如果需要预警通知，压进发送列表
            if ($sendMessageStockWarn) {
                $stockWarnList[] = $productModel;
            }
            $locker->unlock();
        }
        return makeServiceResult(200, 'ok', ['stock_warn_list' => $stockWarnList]);
    }

    /**
     * 锁定积分
     * @return array
     */
    public function lockPoint()
    {
        $order = $this->getOrderModel();
        if ($this->pointUsed) {
            $locker = new \YZ\Core\Locker\Locker('point_' . $order->id);
            if (!$locker->lock()) {
                return makeServiceResult(500, 'can not init point locker');
            }
            $point = new PointModel();
            $point->fill([
                'site_id' => Site::getCurrentSite()->getSiteId(),
                'member_id' => $this->_memberId,
                'point' => $this->pointUsed * -1,
                'status' => CoreConstants::PointStatus_UnActive,
                'created_at' => date('Y-m-d H:i:s'),
                'out_type' => CoreConstants::PointInOutType_OrderPay,
                'out_id' => $order->id,
                'about' => '购物使用积分，订单尚未支付',
                'terminal_type' => $order->terminal_type,
            ]);
            $point->save();
            $locker->unlock();
        }
        return makeServiceResultSuccess();
    }

    /**
     * 锁定优惠券
     * @return array
     */
    public function lockCoupon()
    {
        if ($this->_couponItemId) {
            $locker = new \YZ\Core\Locker\Locker($this->_couponItemId);
            if (!$locker->lock()) {
                return makeServiceResult(500, 'can not init coupon locker');
            }
            CouponItemModel::where('id', $this->_couponItemId)->update(['status' => 4, 'use_terminal_type' => getCurrentTerminal(), 'use_time' => date('Y-m-d H:i:s')]);
            $locker->unlock();
        }
        return makeServiceResultSuccess();
    }

    /**
     * 保存订单
     * @param array $params 额外的一些参数
     * @return array|mixed
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function save($params = [])
    {
        // 检测是否能买
        $notActiveList = []; // 下架列表
        $notEnoughList = []; // 库存不足的列表
        $notHasPermList = []; // 无权购买的列表
        $overQuotaList = []; // 超出限购的列表
        $notReachedMinimumList = []; // 没达到最少起购量的列表
        $minimumMoreThanLimitList = []; // 起购量大于限购或库存的
        $checkCanBuy = $this->checkCanBuy();
        extract($checkCanBuy, EXTR_OVERWRITE);
        // 检测送货地址
        $notDeliveryList = $this->checkDelivery();

        // 根据检测的情况 返回相关数据
        if (count($notDeliveryList) > 0 || count($notActiveList) > 0 || count($notEnoughList) > 0 || count($notHasPermList) > 0 || count($overQuotaList) > 0 || count($notReachedMinimumList) > 0 || count($minimumMoreThanLimitList)) {
            return makeServiceResult(400, '', [
                'not_active_list' => $notActiveList,
                'not_enough_list' => $notEnoughList,
                'not_delivery_list' => $notDeliveryList,
                'not_hasperm_list' => $notHasPermList,
                'over_quota_list' => $overQuotaList,
                'not_reached_minimum_list' => $notReachedMinimumList,
                'minimum_more_than_limit_list' => $minimumMoreThanLimitList,
            ]);
        }

        // 检测优惠是否已经被使用
        $checkCoupon = $this->checkCoupon();
        if ($checkCoupon['code'] != 200) {
            return $checkCoupon;
        }

        // 先计算下所有的价格
        $this->calProductMoney();
        $this->calProductCostMoney();
        $this->calDiscount();

        // 保存前 和之前计算的金额做一下对比 如果金额有变化 需要提醒用户
        // 如果有goBuy 说明用户点击了继续购买
        if ($params && $params['goBuy'] != 1 && $params['originMoneyData']) {
            $msg = $this->beforeSave($params);
            if ($msg) {
                return makeServiceResult(405, $msg);
            }
        }
        // 记录相关的设置快照
        $this->setSnapshots($checkCoupon['data']);

        DB::beginTransaction();
        try {
            // 保存到数据库
            $orderId = $this->saveOrderData($params);

            // 扣除库存
            $reductInventory = $this->reduceInventory();
            if ($reductInventory['code'] != 200) {
                return $reductInventory;
            } else {
                // 达到库存预警的商品列表（ProductModel）
                $stockWarnList = $reductInventory['data']['stock_warn_list'];
            }
            //锁定抵扣积分
            $lockPoint = $this->lockPoint();
            if ($lockPoint['code'] != 200) {
                return $lockPoint;
            }

            //锁定优惠券
            $lockCoupon = $this->lockCoupon();
            if ($lockCoupon['code'] != 200) {
                return $lockCoupon;
            }

            // 更新地址的最后一次使用时间
            if ($this->_addressId) {
                $address = MemberAddressModel::find($this->_addressId);
                $address->last_use_at = Carbon::now();
                $address->save();
            }
            DB::commit();
            // 发送库存预警通知
            if (count($stockWarnList) > 0) {
                foreach ($stockWarnList as $stockWarnProduct) {
                    MessageNoticeHelper::sendMessageProductStockWarn($stockWarnProduct);
                }
            }
            return makeServiceResultSuccess('ok', ['order_id' => $orderId, 'money' => $this->getTotalMoney()]);
        } catch (\Exception $e) {
            DB::rollBack();
            return makeServiceResult(500, trans('shop-front.shop.create_order_error') . " - " . $e->getMessage());
        }
    }

    public function calTotalMoney()
    {
        $this->totalMoney = $this->productMoney - $this->couponMoney - $this->pointMoney - $this->otherDiscount + $this->freight;
        return $this->totalMoney;
    }

    public function getTotalMoney()
    {
        return $this->totalMoney;
    }

    public function doDistribution()
    {
        if (in_array($this->_orderModel->status, self::getNoFinishStatusList())) {
            $commission = $this->calDistribution();
            FinanceHelper::addCommission($this->_orderModel->site_id, $this->_orderModel->id, $commission);
            //记录分佣记录
            $this->_orderModel->commission = json_encode($commission);
            // 记录订单是否是分销订单
            $d = new Distribution();
            $hasCommission = $d->isDistributionOrder($this->_orderModel->member_id, $this->_productList);
            $this->_orderModel->has_commission = $hasCommission;
            $this->_orderModel->save();
        }
    }

    /**
     * 扣减分销佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductDistributionCommision()
    {
        $items = $this->_orderModel->items;
        foreach ($items as $item) {
            Distribution::deductCommissionByItem($this->_orderModel, $item);
        }
    }

    /**
     * 计算代理分佣金额
     * @return mixed
     */
    public function calAgentOrderCommision()
    {
        $res = [];
        foreach ($this->_productList as $item) {
            $tmpres = $item->calAgentOrderCommision($this->_memberId);
            // 保存产品分销计算结果到订单产品项
            if ($this->_orderModel) {
                $updateFields = ['agent_order_commision' => json_encode($tmpres)];
                $dbItem = OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->first();
                if ($dbItem) {
                    $snapshot = json_decode($dbItem->snapshot, true);
                    $snapshot['agent_order_commision_config'] = $item->getAgentOrderCommisionConfig();
                    $updateFields['snapshot'] = json_encode($snapshot);
                }
                OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->update($updateFields);
            }
            foreach ($tmpres as $item) {
                if (!$res[$item['member_id']]) {
                    $res[$item['member_id']] = [];
                    $res[$item['member_id']]['chain'] = $item['chain'];
                    $res[$item['member_id']]['agent_level'] = $item['agent_level'];
                }
                $res[$item['member_id']]['money'] += $item['money'];
            }
        }
        // 重新封装数据格式与 order_item 表一致
        $commission = [];
        foreach ($res as $memberId => $item) {
            $commission[] = ['member_id' => $memberId, 'agent_level' => $item['agent_level'], 'money' => $item['money'], 'chain' => $item['chain']];
        }
        return $commission;
    }

    /**
     * 计算代理分佣金额并保存
     * @return mixed|void
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function doAgentOrderCommision()
    {
        if (in_array($this->_orderModel->status, self::getNoFinishStatusList())) {
            $commission = $this->calAgentOrderCommision();
            AgentReward::addAgentOrderCommission($this->_orderModel->site_id, $this->_orderModel->id, $commission);
            if (is_array($commission) && count($commission) > 0) {
                $commisionGrantTime = intval(AgentBaseSetting::getCurrentSiteSetting()->commision_grant_time);
                $this->_orderModel->has_agent_order_commision = $commisionGrantTime ? Constants::OrderAgentOrderCommissionStatus_YesButFreeze : Constants::OrderAgentOrderCommissionStatus_Yes;
            } else {
                $this->_orderModel->has_agent_order_commision = Constants::OrderAgentOrderCommissionStatus_No;
            }
            $this->_orderModel->agent_order_commision = json_encode($commission);
            $this->_orderModel->save();
        }
    }

    /**
     * 扣减代理销售奖佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductAgentOrderCommision()
    {
        $items = $this->_orderModel->items;
        $orderItemAgentCommisionList = [];
        $memberCommisionList = []; // 会员最终得到多少佣金
        foreach ($items as $item) {
            $orderItemAgentCommisionList[] = AgentReward::deductAgentOrderCommisionByItem($this->_orderModel, $item);
        }
        if (count($orderItemAgentCommisionList) > 0) {
            // 统计
            foreach ($orderItemAgentCommisionList as $orderItemAgentCommisionItem) {
                if (is_array($orderItemAgentCommisionItem)) {
                    foreach ($orderItemAgentCommisionItem as $orderItemAgentCommision) {
                        $memberId = intval($orderItemAgentCommision['member_id']);
                        $money = intval($orderItemAgentCommision['money']);
                        if (array_key_exists($memberId, $memberCommisionList)) {
                            $memberCommisionList[$memberId] += $money;
                        } else {
                            $memberCommisionList[$memberId] = $money;
                        }
                    }
                }
            }
            // 更新财务记录
            foreach ($memberCommisionList as $memberId => $money) {
                if ($money < 0) $money = 0;
                FinanceModel::query()
                    ->where('status', CoreConstants::FinanceStatus_Freeze)
                    ->where('type', CoreConstants::FinanceType_AgentCommission)
                    ->where('sub_type', CoreConstants::FinanceSubType_AgentCommission_Order)
                    ->where('order_id', $this->_orderModel->id)
                    ->where('member_id', $memberId)
                    ->update([
                        'money' => $money,
                        'money_real' => $money,
                    ]);
            }
            // 更新订单的 agent_order_commision 字段
            $orderAgentCommision = json_decode($this->_orderModel->agent_order_commision, true);
            if (is_array($orderAgentCommision) && count($orderAgentCommision) > 0) {
                foreach ($orderAgentCommision as &$orderAgentCommisionItem) {
                    $memberId = intval($orderAgentCommisionItem['member_id']);
                    $orderAgentCommisionItem['money'] = intval($memberCommisionList[$memberId]);
                }
                unset($orderAgentCommisionItem);
                $this->_orderModel->agent_order_commision = json_encode($orderAgentCommision);
                $this->_orderModel->save();
            }
        }
    }

    /**
     * 计算代理销售奖(平级/越级奖)分佣金额
     * @return mixed
     */
    public function calAgentSaleRewardCommision()
    {
        $res = [];
        foreach ($this->_productList as $item) {
            $tmpres = $item->calAgentSaleRewardCommision($this->_memberId);
            //保存产品分销计算结果到订单产品项
            if ($this->_orderModel) {
                $updateFields = ['agent_sale_reward_commision' => json_encode($tmpres)];
                $dbItem = OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->first();
                if ($dbItem) {
                    $snapshot = json_decode($dbItem->snapshot, true);
                    $snapshot['agent_sale_reward_commision_config'] = $item->getAgentSaleRewardCommisionConfig();
                    $updateFields['snapshot'] = json_encode($snapshot);
                }
                OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->update($updateFields);
            }
            foreach ($tmpres as $item) {
                if (!$res[$item['member_id']]) {
                    $res[$item['member_id']] = [];
                    $res[$item['member_id']]['chain'] = $item['chain'];
                    $res[$item['member_id']]['agent_level'] = $item['agent_level'];
                    $res[$item['member_id']]['is_samelevel'] = $item['is_samelevel'];
                    $res[$item['member_id']]['is_lowlevel'] = $item['is_lowlevel'];
                }
                $res[$item['member_id']]['money'] += $item['money'];
            }
        }
        //重新封装数据格式与 order_item 表一致
        $commission = [];
        foreach ($res as $memberId => $item) {
            $commission[] = ['member_id' => $memberId, 'agent_level' => $item['agent_level'], 'is_samelevel' => $item['is_samelevel'], 'is_lowlevel' => $item['is_lowlevel'], 'money' => $item['money'], 'chain' => $item['chain']];
        }
        return $commission;
    }

    /**
     * 计算代理销售奖(平级/越级奖)分佣金额并保存
     * @return mixed
     */
    public function doAgentSaleRewardCommision()
    {
        if (in_array($this->_orderModel->status, self::getNoFinishStatusList())) {
            $commission = $this->calAgentSaleRewardCommision();
            AgentReward::addAgentSaleRewardCommission($this->_orderModel->site_id, $this->_orderModel->id, $commission);
            $this->_orderModel->agent_sale_reward_commision = json_encode($commission);
            $this->_orderModel->save();
        }
    }

    /**
     * 计算代理其他奖的奖金
     * @return mixed
     */
    public function doAgentOtherRewardCommision()
    {
        if (in_array($this->_orderModel->status, self::getNoFinishStatusList())) {
            AgentOtherReward::calcReward(Constants::AgentOtherRewardType_Grateful, $this->_orderModel->id);
        }
    }

    /**
     * 扣减代理其他奖的奖金 退款时使用
     * @return mixed
     */
    public function deductAgentOtherRewardCommision()
    {
        AgentOtherReward::calcAfterSaleReward(Constants::AgentOtherRewardType_Grateful, $this->_orderModel->id);

    }

    /**
     * 扣减代理销售奖佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductAgentSaleRewardCommision()
    {
        $items = $this->_orderModel->items;
        $orderItemAgentCommisionList = [];
        $memberCommisionList = []; // 会员最终得到多少佣金
        foreach ($items as $item) {
            $orderItemAgentCommisionList[] = AgentReward::deductAgentSaleRewardCommisionByItem($this->_orderModel, $item);
        }
        if (count($orderItemAgentCommisionList) > 0) {
            // 统计
            foreach ($orderItemAgentCommisionList as $orderItemAgentCommisionItem) {
                if (is_array($orderItemAgentCommisionItem)) {
                    foreach ($orderItemAgentCommisionItem as $orderItemAgentCommision) {
                        $memberId = intval($orderItemAgentCommision['member_id']);
                        $money = intval($orderItemAgentCommision['money']);
                        if (array_key_exists($memberId, $memberCommisionList)) {
                            $memberCommisionList[$memberId] += $money;
                        } else {
                            $memberCommisionList[$memberId] = $money;
                        }
                    }
                }
            }
            // 更新财务记录
            foreach ($memberCommisionList as $memberId => $money) {
                if ($money < 0) $money = 0;
                FinanceModel::query()
                    ->where('status', CoreConstants::FinanceStatus_Freeze)
                    ->where('type', CoreConstants::FinanceType_AgentCommission)
                    ->where('sub_type', CoreConstants::FinanceSubType_AgentCommission_SaleReward)
                    ->where('order_id', $this->_orderModel->id)
                    ->where('member_id', $memberId)
                    ->update([
                        'money' => $money,
                        'money_real' => $money,
                    ]);
            }
            // 更新订单的 agent_sale_reward_commision 字段
            $orderAgentCommision = json_decode($this->_orderModel->agent_sale_reward_commision, true);
            if (is_array($orderAgentCommision) && count($orderAgentCommision) > 0) {
                foreach ($orderAgentCommision as &$orderAgentCommisionItem) {
                    $memberId = intval($orderAgentCommisionItem['member_id']);
                    $orderAgentCommisionItem['money'] = intval($memberCommisionList[$memberId]);
                }
                unset($orderAgentCommisionItem);
                $this->_orderModel->agent_sale_reward_commision = json_encode($orderAgentCommision);
                $this->_orderModel->save();
            }
        }
    }

    /**
     * 计算区域代理佣金
     * @return mixed
     */
    public function calAreaAgentCommission()
    {
        $snapshot = json_decode($this->_orderModel->snapshot, true);
        $areaId = $snapshot['address']['area'];
        if (!$areaId) return [];
        $res = [];
        foreach ($this->_productList as $item) {
            $tmpres = $item->calAreaAgentCommission($this->_memberId, $areaId);
            // 保存产品分销计算结果到订单产品项
            if ($this->_orderModel) {
                $updateFields = ['area_agent_commission' => json_encode($tmpres)];
                $dbItem = OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->first();
                if ($dbItem) {
                    $snapshot = json_decode($dbItem->snapshot, true);
                    $snapshot['area_agent_commission_config'] = $item->getAreaAgentCommissionConfig();
                    $updateFields['snapshot'] = json_encode($snapshot);
                }
                OrderItemModel::where(['order_id' => $this->_orderModel->id, 'product_id' => $item->productId, 'sku_id' => $item->skuId])->update($updateFields);
            }
            foreach ($tmpres as $item) {
                if (!$res[$item['member_id']]) {
                    $res[$item['member_id']] = [];
                    $res[$item['member_id']]['area_agent_level'] = $item['area_agent_level'];
                    $res[$item['member_id']]['area_type'] = $item['area_type'];
                    $res[$item['member_id']]['chain'] = $item['chain'];
                }
                $res[$item['member_id']]['money'] += $item['money'];
            }
        }
        // 重新封装数据格式与 order_item 表一致
        $commission = [];
        foreach ($res as $memberId => $item) {
            $commission[] = ['member_id' => $memberId, 'area_agent_level' => $item['area_agent_level'], 'money' => $item['money'], 'area_type' => $item['area_type'], 'chain' => $item['chain']];
        }
        return $commission;
    }

    /**
     * 计算区域代理佣金并保存
     * @return mixed|void
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function doAreaAgentCommission()
    {
        $setting = AreaAgentBaseSettingModel::query()->where('site_id', getCurrentSiteId())->first();
        if (intval($setting->status) === 1 && in_array($this->_orderModel->status, self::getNoFinishStatusList())) {
            $commission = $this->calAreaAgentCommission();
            if (is_array($commission) && count($commission) > 0) {
                AreaAgentCommission::addOrderCommission($this->_orderModel->site_id, $this->_orderModel->id, $commission);
                $setting = AreaAgentBaseSettingModel::query()->where('site_id', getCurrentSiteId())->first();
                $commissionGrantTime = intval($setting->commision_grant_time);
                $this->_orderModel->area_agent_commission_status = $commissionGrantTime ? Constants::OrderAreaAgentOrderCommissionStatus_YesButFreeze : Constants::OrderAreaAgentOrderCommissionStatus_Yes;
            } else {
                $this->_orderModel->area_agent_commission_status = Constants::OrderAreaAgentOrderCommissionStatus_No;
            }
            $this->_orderModel->area_agent_commission = json_encode($commission);
            $this->_orderModel->save();
        }
    }

    /**
     * 扣减区域代理佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductAreaAgentCommission()
    {
        $items = $this->_orderModel->items;
        foreach ($items as $item) {
            AreaAgentCommission::deductCommissionByItem($this->_orderModel, $item);
        }
    }

    /**
     * 订单支付前的检测
     * @param $payInfo
     * @throws \Exception
     */
    public function payBeforeCheck($payInfo)
    {
        if ($this->_orderModel->status == Constants::OrderStatus_Cancel) {
            if (in_array($payInfo['pay_type'], \YZ\Core\Constants::getOnlinePayType())) {
                Log::writeLog("order", "订单 " . $this->_orderModel->id . " 已超时关闭，款项退回");
                FinanceHelper::refund($this->_orderModel->member_id, $this->_orderModel->id, $payInfo['pay_type'], $payInfo['tradeno'], 0, $payInfo['money'], "订单已超时关闭，退款", 0);
                DB::commit();
            }
            throw new \Exception('order in cancel status');
        }
        if ($this->_orderModel->status != Constants::OrderStatus_NoPay) {
            Log::writeLog("order", "订单 " . $this->_orderModel->id . " 状态不对，当前状态为：" . $this->_orderModel->status);
            throw new \Exception('order status error, current status = ' . $this->_orderModel->status);
        }
    }

    /**
     * 订单支付后 更新状态等数据
     */
    public function payAfterUpdateOrderData()
    {
        $this->_orderModel->status = Constants::OrderStatus_OrderPay;
        $this->_orderModel->pay_at = date('Y-m-d H:i:s');
    }

    public function payAfterAddProductSoldCount()
    {
        $items = $this->_orderModel->items;
        foreach ($items as $item) {
            if ($item->sku_id) {
                ProductSkusModel::where(['product_id' => $item->product_id, 'id' => $item->sku_id])->increment('sku_sold_count', $item->num);
            } else {
                ProductSkusModel::where(['product_id' => $item->product_id, 'sku_code' => 0])->increment('sku_sold_count', $item->num);
            }
            ProductModel::where('id', $item->product_id)
                ->increment('sold_count', $item->num);
        }
    }

    /**
     * 支付成功后 更新订单数据
     * @param $payInfo
     */
    public function payAfterUpdateOrderSave($payInfo)
    {
        $this->payAfterUpdateOrderData();
        // 记录积分赠送的配置
        $snapshot = json_decode($this->_orderModel->snapshot, true);
        $snapshot['point_config_pay'] = (new PointConfig(Site::getCurrentSite()->getSiteId()))->getInfo();
        $this->_orderModel->snapshot = json_encode($snapshot);
        // 更新订单中的相关交易号
        $this->_orderModel->pay_type = $payInfo['pay_type'];
        $this->_orderModel->transaction_id = $payInfo['tradeno'];
        $this->_orderModel->save();
    }

    /**
     * 支付成功后更新积分 优惠券等
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function payAfterUpdateOther()
    {
        // 扣除积分(在生成订单时已锁定)
        $pointModel = PointModel::query()
            ->where('out_id', $this->_orderModel->id)
            ->where('site_id', $this->_orderModel->site_id)
            ->where('point', $this->_orderModel->point * -1)
            ->where('out_type', CoreConstants::PointInOutType_OrderPay)
            ->first();
        if ($pointModel) {
            $pointHandel = new Point();
            $pointHandel->active($pointModel->id, [
                'about' => '支付订单抵扣积分，订单号：' . $this->_orderModel->id,
            ]);
        }
        // 抵扣的优惠券变为已使用
        CouponItemModel::where('id', $this->_orderModel->coupon_item_id)->update(['status' => 1]);

        // 更新此会员累计的付款次数以及付款金额
        $member = MemberModel::where('id', $this->_orderModel->member_id)->first();
        $member->increment('buy_times');
        $member->increment('buy_money', $this->_orderModel->money);

        // 送积分（冻结状态的）
        $pointHandle = new PointGiveForConsume($this->_orderModel->member_id, $this->_orderModel->id);
        $pointHandle->setTerminalType($this->_orderModel->terminal_type);
        $pointHandle->addPoint();

        // 更新下单会员的一些统计数据
        $statistics = new Statistics($this->_orderModel);
        $statistics->calcMemberStatistics();

        // 会员升级于支付完毕
        //MemberLevelUpgradeHelper::levelUpgrade($this->_orderModel->member_id);
        TaskHelper::addTask(new UpgradeMemberLevelJob($this->_orderModel->member_id));
    }

    /**
     * 订单支付成功后的一些操作
     * @param $payInfo
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function payAfter($payInfo)
    {
        // 更新订单数据
        $this->payAfterUpdateOrderSave($payInfo);
        // 产品的销量要增加
        $this->payAfterAddProductSoldCount();
        // 更新积分 优惠券 等
        $this->payAfterUpdateOther();
    }

    /**
     * 订单支付失败时的处理
     * @param \Exception $e
     */
    public function payFail($e)
    {

    }

    /**
     * 支付成功后是否需要绑定关系等
     */
    public function payAfterBindInvite()
    {
        if ($this->needBindInvite()) $this->bindInvite();
        else $this->payAfterUpdateMemberAndCommission();
    }

    /**
     * 订单交费
     * @param array $payInfo 支付相关信息，如支付类型，相关交易号等
     * @return mixed|void
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function pay(array $payInfo)
    {
        $locker = new Locker('order_pay_' . $this->_orderModel->id);
        $orderIds = [];
        try {
            // lock
            if (!$locker->lock()) throw new \Exception('can not lock order for pay()');
            // check
            $this->payBeforeCheck($payInfo);
            $orderInstances = [];
            $financeIds = [];
            $splitOrders = OrderHelper::splitSupplierOrder($this->_orderModel);
            if (count($splitOrders) > 1) $this->_orderModel->delete(); //如果需要拆单，删除旧的订单记录
            if ($splitOrders) {
                foreach ($splitOrders as $item) {
                    $order = ShopOrderFactory::createOrderByOrderId($item);
                    $orderInstances[] = $order;
                }
            } else {
                //$orderInstances[] = $this;
                //这里要重新初始化一下，用 $this 时订单类型是不对的
                $orderInstances[] = ShopOrderFactory::createOrderByOrderId($this->getOrderModel());
            }

            foreach ($orderInstances as $item) {
                try {
                    $orderIds[] = $item->getOrderModel()->id;
                    // 扣钱
                    $payInfoNew = $payInfo;
                    if ($splitOrders && intval($payInfoNew['pay_type']) == \YZ\Core\Constants::PayType_Balance) {
                        $payInfoNew['tradeno'] = 'PAYORDER_' . $item->getOrderModel()->id; //重写tradeno，保证唯一
                    }
                    DB::beginTransaction();
                    $financeIds[$item->getOrderModel()->id] = FinanceHelper::payOrder($item->getOrderModel()->site_id, $item->getOrderModel()->member_id, $item->getOrderModel()->id, $item->getOrderModel()->money, $payInfoNew);
                    // 更新订单相关
                    $item->payAfter($payInfoNew);
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
                $item->payAfterBindInvite();
            }

            foreach ($orderInstances as $item) {
                // 通知买家
                MessageNoticeHelper::sendMessageOrderPaySuccess($item->getOrderModel());
                // 通知卖家
                MessageNoticeHelper::sendMessageOrderNewPay($item->getOrderModel());
                // 余额支付，发送通知
                if (intval($item->getOrderModel()->pay_type) == CoreConstants::PayType_Balance && $financeIds[$item->getOrderModel()->id]) {
                    MessageNoticeHelper::sendMessageBalanceChange(FinanceModel::find($financeIds[$item->getOrderModel()->id]));
                }
            }

            $locker->unlock();
        } catch (\Exception $e) {
            $locker->unlock();
            $this->payFail($e);
            throw $e;
        }

        return $orderIds;
    }

    /**
     * 判断是否需要绑定推荐人
     * @return bool
     */
    public function needBindInvite()
    {
        if (Site::getCurrentSite()->getConfig()->getModel()->bind_invite_time == 1) {
            return $this->_memberObj->getModel()->has_bind_invite == 0;
        }
        return false;
    }

    /**
     * 绑定推荐人
     */
    public function bindInvite()
    {
        $fans = WxUserModel::query()->where('member_id', $this->_orderModel->member_id)->first();
        if ($fans && $fans->invite) $inviteCode = intval($fans->invite);
        if (!$inviteCode) $inviteCode = intval(Session::get('invite')); //其次从Session里取
        if (!$inviteCode) $inviteCode = intval(Request::cookie('invite')); //再次从Cookie里取
        $this->_memberObj->setParent($inviteCode, $this->_orderModel->id);
    }

    /**
     * 支付成功后 订单分佣 会员升级 等
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function payAfterUpdateMemberAndCommission()
    {
        // 更新此会员的分销上级的付款次数以及付款金额
        $distributor = new Distributor($this->_orderModel->member_id);
        $distributor->accumulateBuyTimes($this->_orderModel->id, $this->_orderModel->member_id);
        $distributor->accumulateBuyMoney($this->_orderModel->id, $this->_orderModel->member_id, $this->_orderModel->money);
        // 生成分佣记录
        $this->doDistribution();
        // 生成代理正常订单分佣记录
        $this->doAgentOrderCommision();
        // 生成代理订单销售奖分佣记录
        $this->doAgentSaleRewardCommision();
        // 生成代理其他奖分佣记录 此方法必须放在doAgentOrderCommision后面
        $this->doAgentOtherRewardCommision();
        // 生成区域代理返佣记录
        $this->doAreaAgentCommission();

        //判断假如后台佣金计算条件为付款后，立马放发佣金
        $needDoUpgradeDistributionLevel = false;
        if ((new DistributionSetting())->getSettingModel()->calc_commission_valid_condition == 0) {
            FinanceHelper::commissionChangeStatusByOrder($this->_orderModel->id);
            $needDoUpgradeDistributionLevel = true;
        }
        // 因为代理有其他奖励所以需要移动到这里发信息，条件为付款后就发
        $financeModelList = FinanceHelper::getCommissionFinanceByOrderId($this->_orderModel->id);
        if ($financeModelList) {
            foreach ($financeModelList as $financeModel) {
                MessageNoticeHelper::sendMessageCommissionActive($financeModel);
            }
        }
        // 因为代理有其他奖励所以需要移动到这里发信息，条件为付款后就发
        $agentFinanceList = FinanceHelper::getAgentRewardFinanceByOrderId($this->_orderModel->id);
        if (key_exists('normal', $agentFinanceList)) {
            foreach ($agentFinanceList['normal'] as $financeModel) {
                MessageNoticeHelper::sendMessageAgentCommission($financeModel);
            }
        }


        // 记录订单会员历史关系（这段代码必须放在doDistribution，doAgentOrderCommision后面）
        OrderHelper::buildOrderMembersHistory($this->_orderModel->id);

        //判断假如后台升级计算条件为付款后，立马去判断分销员是否能升级
        if ((new DistributionSetting())->getSettingModel()->calc_upgrade_valid_condition == 0) {
            $needDoUpgradeDistributionLevel = true;
        }

        // 分销商升级
        if ($needDoUpgradeDistributionLevel) TaskHelper::addTask(new UpgradeDistributionLevelJob($this->_orderModel->member_id));

        //代理商升级
        TaskHelper::addTask(new UpgradeAgentLevelJob($this->_orderModel->member_id));

        // 业绩统计
        AgentReward::buildOrderPerformance($this->_orderModel, intval($this->_orderModel->money));
        // 区域业绩统计 付款后
        AreaAgentPerformance::createAreaAgentPerformance($this->_orderModel, 0);
    }

    /**
     * 订单关闭之前的其他处理
     */
    public function cancelBeforeUpdate()
    {

    }

    /**
     * 取消订单，只有未付款状态可以调用此方法
     * @param string $msg 关闭原因
     * @return mixed|void
     * @throws \Exception
     */
    public function cancel($msg = '')
    {
        if ($this->_orderModel) {
            if ($this->_orderModel->status > 1) {
                throw new \Exception(trans('shop-front.refund.nocancel_1'));
            }
            DB::beginTransaction();
            try {
                //退钱，只有已付款未发货状态才能直接退钱(此过程已经在售后流程内处理，这里不作调用)
                /*if (in_array($this->_orderModel->status, [Constants::OrderStatus_OrderPay])) {
                    FinanceHelper::refund($this->_orderModel->member_id, $this->_orderModel->id, $this->_orderModel->pay_type, $this->_orderModel->transaction_id, 0, $this->_orderModel->money, '订单退款');
                }*/
                //退积分
                PointModel::where('out_id', $this->_orderModel->id)->where('point', '=', $this->_orderModel->point * -1)->where('out_type', CoreConstants::PointInOutType_OrderPay)->delete();
                //退优惠券
                $coupon_item = CouponItemModel::where('id', $this->_orderModel->coupon_item_id)->first();
                $coupon = CouponModel::where('id', $coupon_item->coupon_id)->first();
                //如果在取消订单的时候，这张优惠券在后台被选为禁用的时候，这张优惠券应该转为失效
                if ($coupon->status == 0) {
                    CouponItemModel::where('id', $this->_orderModel->coupon_item_id)->update(['status' => Constants::CouponStatus_Invalid, 'use_time' => null]);
                } else {
                    CouponItemModel::where('id', $this->_orderModel->coupon_item_id)->update(['status' => Constants::CouponStatus_NoUse, 'use_time' => null]);
                }
                //库存返回去
                $this->backInventory();
                //删除分佣记录
                FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_Commission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'order_id' => $this->_orderModel->id])->where('money', '>', '0')->delete();
                //删除代理分佣记录
                AgentReward::deleteAgentCommisionStatusByOrder($this->_orderModel->id, ['status' => \YZ\Core\Constants::FinanceStatus_Freeze]);
                //更改状态
                $this->_orderModel->status = Constants::OrderStatus_Cancel;
                // 取消原因
                $this->_orderModel->cancel_message = $msg;
                $this->_orderModel->end_at = date('Y-m-d H:i:s');
                $this->cancelBeforeUpdate();
                $this->_orderModel->save();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw new \Exception(trans('shop-front.refund.cancel_order_fail'));
            }
        }
    }

    /**
     * 返还库存
     * @param bool $backSoldCount 是否退销量
     */
    protected function backInventory($backSoldCount = false)
    {
        $items = $this->_orderModel->items;
        foreach ($items as $item) {
            // 要退销量
            if ($backSoldCount) {
                if ($item->sku_id) {
                    ProductSkusModel::where(['product_id' => $item->product_id, 'id' => $item->sku_id])->decrement('sku_sold_count', $item->num);
                    ProductSkusModel::where(['product_id' => $item->product_id, 'id' => $item->sku_id])->increment('inventory', $item->num);
                } else {
                    ProductSkusModel::where(['product_id' => $item->product_id, 'sku_code' => 0])->decrement('sku_sold_count', $item->num);
                    ProductSkusModel::where(['product_id' => $item->product_id, 'sku_code' => 0])->increment('inventory', $item->num);
                }
                ProductModel::where('id', $item->product_id)
                    ->decrement('sold_count', $item->num);
            } else {
                if ($item->sku_id) {
                    ProductSkusModel::where(['product_id' => $item->product_id, 'id' => $item->sku_id])->increment('inventory', $item->num);
                } else {
                    ProductSkusModel::where(['product_id' => $item->product_id, 'sku_code' => 0])->increment('inventory', $item->num);
                }
            }
            // 已经是已售罄的商品 返还库存后 要把已售罄状态取消
            ProductModel::where('id', $item->product_id)
                ->where('is_sold_out', \YZ\Core\Constants::Product_Sold_Out)
                ->update(['is_sold_out' => \YZ\Core\Constants::Product_No_Sold_Out]);
        }
    }

    /**
     * 订单完成之后的操作
     * @param $type
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function finishUpdate($type)
    {
        if ($type == 0) {
            // 统计分销商相关的数据[directly_under_deal_times directly_under_deal_money subordinate_deal_times subordinate_deal_money]
            //成交金额=订单金额-退款金额
            Distributor::accumulateDealTimes($this->_orderModel->id, $this->_orderModel->member_id);
            Distributor::accumulateDealMoney($this->_orderModel->id, $this->_orderModel->member_id, ($this->_orderModel->money + $this->_orderModel->after_sale_money));
            //会员 成交次数 以及 成交金额=订单金额-退款金额
            $member = MemberModel::where('id', $this->_orderModel->member_id)->first();
            $member->increment('deal_times');
            $member->increment('deal_money', ($this->_orderModel->money + $this->_orderModel->after_sale_money));
            // 更新订单状态为完成
            $this->_orderModel->status = Constants::OrderStatus_OrderFinished;
            $this->_orderModel->end_at = date('Y-m-d H:i:s');
            if (intval($this->_orderModel->has_agent_order_commision) == Constants::OrderAgentOrderCommissionStatus_YesButFreeze) {
                $this->_orderModel->has_agent_order_commision = Constants::OrderAgentOrderCommissionStatus_Yes;
            }
            $this->_orderModel->save();
            // 分佣记录状态变更
            $financeModelList = FinanceHelper::commissionChangeStatusByOrder($this->_orderModel->id);
            // 申请成为分销商
            BecomeDistributorHelper::applyBecomeDistributorForOrder($this->_memberObj, $this->_orderModel->terminal_type, Constants::Period_OrderFinish);
            // 相关分销商升级
            TaskHelper::addTask(new UpgradeDistributionLevelJob($this->_orderModel->member_id));
            // 代理分佣记录状态变更
            $agentFinanceList = AgentReward::activeAgentCommisionStatusByOrder($this->_orderModel->id);
            // 区域代理分佣记录状态变更
            $areaFinanceList = AreaAgentCommission::activeCommissionStatusByOrder($this->_orderModel->id);
            // 会员升级于订单完成
            //MemberLevelUpgradeHelper::levelUpgrade($this->_orderModel->member_id);
            TaskHelper::addTask
            (new UpgradeMemberLevelJob($this->_orderModel->member_id));
            // 购物送积分变为生效
            PointGiveHelper::DueForOrderFinish($this->_orderModel->id, $type);
            // 业绩统计（订单总金额 - 退款总金额）
            AgentReward::buildOrderPerformance($this->_orderModel, intval($this->_orderModel->money) - abs(intval($this->_orderModel->after_sale_money)), 1);
            // 区域代理业绩 维权期后
            AreaAgentPerformance::createAreaAgentPerformance($this->_orderModel, 1);
            // 代理其他奖状态更改
            AgentOtherReward::changeStatus([Constants::AgentOtherRewardType_Grateful], $this->_orderModel->id);
            //改为用队列处理 相关代理升级
            TaskHelper::addTask(new UpgradeAgentLevelJob($this->_orderModel->member_id));
            // 发送通知（佣金）
            if ($financeModelList && ((new DistributionSetting())->getSettingModel()->calc_commission_valid_condition == 1)) {
                foreach ($financeModelList as $financeModel) {
                    MessageNoticeHelper::sendMessageCommissionActive($financeModel);
                }
            }
            // 发送通知（分红、销售奖）
            if ($agentFinanceList && (AgentBaseSetting::getCurrentSiteSetting())->commision_grant_time == 1) {
                if (key_exists('normal', $agentFinanceList)) {
                    foreach ($agentFinanceList['normal'] as $financeModel) {
                        MessageNoticeHelper::sendMessageAgentCommission($financeModel);
                    }
                }
                if (key_exists('salereward', $agentFinanceList)) {
                    foreach ($agentFinanceList['salereward'] as $financeModel) {
                        MessageNoticeHelper::sendMessageAgentCommission($financeModel);
                    }
                }
            }
            if ((AreaAgentBaseSetting::getCurrentSiteSetting())->commision_grant_time == 1) {
                // 发送通知（区域代理佣金）
                if ($areaFinanceList) {
                    foreach ($areaFinanceList as $financeModel) {
                        MessageNoticeHelper::sendMessageAreaAgentCommission($financeModel);
                    }
                }
            }
        } else {
            // 更新订单状态为交易关闭
            $this->_orderModel->status = Constants::OrderStatus_OrderClosed;
            $this->_orderModel->end_at = date('Y-m-d H:i:s');
            if (intval($this->_orderModel->has_agent_order_commision) == Constants::OrderAgentOrderCommissionStatus_YesButFreeze) {
                $this->_orderModel->has_agent_order_commision = Constants::OrderAgentOrderCommissionStatus_YesButInvalid;
            }
            $this->_orderModel->save();
            $this->orderCloseAfter($type);
        }
    }

    /**
     * 订单结束
     * @param int $type 0 = 付款后过维权期正常结束，1=订单全部退
     * @return mixed|void
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function finish(int $type = 0)
    {
        if ($this->_orderModel) {
            if (in_array($this->_orderModel->status, [Constants::OrderStatus_Deleted, Constants::OrderStatus_NoPay, Constants::OrderStatus_Cancel, Constants::OrderStatus_OrderFinished, Constants::OrderStatus_OrderClosed])) {
                throw new \Exception('订单状态不对，不能执行此操作');
            }
            $this->finishUpdate($type);
        }
    }

    /**
     * 订单全部退款 关闭之后的处理
     * @param $type
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function orderCloseAfter($type)
    {
        // 分佣记录变为失效
        FinanceHelper::commissionChangeStatusByOrder($this->_orderModel->id);
        // 代理分佣记录变为失效
        AgentReward::cancelAgentCommisionByOrder($this->_orderModel->id);
        // 区域代理记录变为失效
        AreaAgentCommission::cancelCommissionByOrder($this->_orderModel->id);
        // 清理冻结的积分
        PointGiveHelper::DueForOrderFinish($this->_orderModel->id, $type);
        //退优惠券
        CouponItemModel::where('id', $this->_orderModel->coupon_item_id)->update(['status' => 2, 'use_time' => null]);
        // 返还库存和销量
        $this->backInventory(true);
        // 业绩统计（订单总金额 - 运费 - 退款总金额）
        AgentReward::buildOrderPerformance($this->_orderModel, intval($this->_orderModel->money) - abs(intval($this->_orderModel->after_sale_money)), 1);
        // 代理其他奖状态变为失效
        AgentOtherReward::changeStatus([Constants::AgentOtherRewardType_Grateful], $this->_orderModel->id);
        // 订单关闭后 快递状态的同步
        ExpressHelper::orderCancelSync($this->_orderModel);
    }

    /**
     * 订单交费成功后，进行的一些操作，比如开通相应产品等
     * @return mixed
     */
    public function afterSettlement()
    {

    }

    /**
     * 确认收货
     * @return bool|mixed
     * @throws \Exception
     */
    public function receipt()
    {
        if ($this->_orderModel) {
            if ($this->_orderModel->status != Constants::OrderStatus_OrderSend) {
                return false;
            }
            $siteId = $this->_orderModel->site_id;
            $orderId = $this->_orderModel->id;
            $orderHelper = new OrderHelper($siteId);
            // 检查订单状态
            if (!$orderHelper->canSetReceipt($orderId)) {
                return false;
            }
            // 更新状态和收货时间
            $this->_orderModel->status = Constants::OrderStatus_OrderSuccess;
            $this->_orderModel->receive_at = date('Y-m-d H:i:s');
            $this->_orderModel->save();
            // 如果用户关闭售后了，直接流转到交易完成
            $orderConfig = (new OrderConfig())->getInfo();
            $orderConfigAfterSaleIsOpen = intval($orderConfig['aftersale_isopen']);
            if ($orderConfigAfterSaleIsOpen != 1) {
                $this->finish();
            }
            return true;
        }
        return false;
    }

    /**
     * 申请成为分销商
     */
    public function applyBecomeDistributor()
    {
        $applyProductId = Session::get(CoreConstants::SessionKey_DistributorApply_ProductID);
        if ($applyProductId) {
            $applyProductId = intval($applyProductId);
            $items = $this->_orderModel->items;
            foreach ($items as $item) {
                // 把第一个命中的订单明细认为是分销产品
                if (intval($item->product_id) == $applyProductId) {
                    OrderItemModel::query()
                        ->where('site_id', $this->_orderModel->site_id)
                        ->where('order_id', $this->_orderModel->id)
                        ->where('id', $item->id)
                        ->update(['is_commission_product' => 1]);
                    // 申请分销商
                    BecomeDistributorHelper::applyBecomeDistributor($this->_orderModel->member_id, $this->_orderModel->terminal_type);
                    Session::remove(CoreConstants::SessionKey_DistributorApply_ProductID);
                    break;
                }
            }
        }
    }

    /**
     * @return int
     */
    public function getThisMemberId()
    {
        return $this->_memberId;
    }

    /**
     * @return array
     */
    public function getThisProductList()
    {
        return $this->_productList;
    }

    /**
     * 在保存订单前，返回订单里的商品的虚拟标记(虚拟商品标记，0=非虚拟商品，1=全部为虚拟商品，2=混合（同时有虚拟和非虚拟）)
     *
     * @return int
     */
    public function getVirtualFlag()
    {
        $realNum = 0; //实体商品数量
        $virtualNum = 0; //虚拟商品数量
        foreach ($this->_productList as $item) {
            if ($item->isVirtual()) $virtualNum++;
            else $realNum++;
        }
        if ($realNum && $virtualNum) return 2;
        elseif ($virtualNum === count($this->_productList)) return 1;
        else return 0;
    }

    /**
     * 当发生售后成功处理时，重算供应商结算金额
     * @param AfterSaleModel $afterSaleModel
     * @param $freightFlag 是否扣减结算运费，true = 将结算的运费扣减为0，否则不处理，一般是在未发货并完全退单时，扣除运费
     */
    public function deductSettleData(AfterSaleModel $afterSaleModel)
    {
        return;
    }
}