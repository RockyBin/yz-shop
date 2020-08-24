<?php

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentCommissionConfig;
use App\Modules\ModuleShop\Libs\Distribution\DistributionConfig;
use App\Modules\ModuleShop\Libs\Agent\AgentOrderCommisionConfig;
use App\Modules\ModuleShop\Libs\Agent\AgentSaleRewardCommisionConfig;

/**
 * 此接口用于加入到购物车或订单中的商品(具体到相应的SKUID)
 * Interface ShopProduct
 * @package App\Modules\ModuleShop\Libs\Shop
 */
interface IShopProduct {

    /**
     * 计算产品本身价格
     * @param int $memberId
     * @return mixed
     */
    public function calPrice(int $memberId);

    /**
     * 返回商品本身的金额（含数量，未扣除优惠），应该先调用 calPrice() 再调用此方法
     * @return mixed
     */
    public function getProductMoney();

    /**
     * 计算产品能用的积分
     * @param int $ordrMoney 订单可使用积分的产品总金额，单位分，积分抵扣规则里，有最大金额限制，在运算过程中会用到订单总金额
     * @param int $myPoint 用户拥有的积分
     * @return mixed
     */
    public function calPoint(int $ordrMoney,int $myPoint);

    /** 计算产品的优惠券抵扣金额
     * @param int $ordrMoney 订单可使用优惠券的产品总金额
     * @param int $couponItemId 用户领取的优惠券的ID
     * @return mixed
     */
    public function calCoupon(int $ordrMoney,int $couponItemId);

    /**
     * 算其它优惠，根据不同的产品类型会不同
     * @param mixed ...$args
     * @return mixed
     */
    public function calOtherDiscount(...$args);

    /**
     * 计算产品的运费
     * @param int $cityId 地级市ID
     * @param int $hasFirstFee 是否算首件运费
     * @return mixed
     */
    public function calFreight(int $cityId, $hasFirstFee);

    /**
     * 是否包邮
     * @return mixed
     */
    public function isFreeFreight();

    /**
     * 判断用户选定的送货城市是否能送货
     * @param int $cityId
     * @return mixed
     */
    public function canDelivery(int $cityId);

    /**
     * 此产品是否可以使用积分
     * @return mixed
     */
    public function canUsePoint();

    /**
     * 判断此产品是否能使用某张优惠券
     * @param $orderMoney 订单商品的总金额
     * @param $couponId
     * @return mixed
     */
    public function canUseCoupon($orderMoney,$couponId);

    /**
     * 判断会员能否购买此商品，要检测会员限制规则和库存
     * @param int $memberId 会员ID
     * @param int $quantity 订购数量
     * @return mixed
     */
    public function canBuy(int $memberId, int $quantity);

    /**
     * 判断当前登录会员是否有权限购买此商品
     * @return bool
     */
    public function checkBuyPerm();

    /**
     * 获取此商品的会员单价
     * @return int $money
     */
    public function getMemberPrice($userLevel,$rule = null);

    /**
     * 获取此商品的积分抵扣设置
     * @return PointDeductionConfig
     */
    public function getPointDeductionConfig(): PointDeductionConfig;

    /**
     * 获取此商品的分销设置规则
     * @return DistributionConfig
     */
    public function getDistributionConfig(): DistributionConfig;
    
    /**
     * 计算此商品的分销金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额，格式如
     * ['memberId1' => money1,'memberId2' => money2]
     */
    public function calDistribution(int $memberId) : array;

    /**
     * 获取此产品在订购时需要记录的快照信息
     * @return mixed
     */
    public function getSnapShotInfo();

    /**
     * 获取此商品的正常订单代理分佣规则
     * @return AgentOrderCommisionConfig
     */
    public function getAgentOrderCommisionConfig(): AgentOrderCommisionConfig;
    
    /**
     * 计算此商品的正常团队代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentOrderCommision(int $memberId) : array;

    /**
     * 获取此商品的平级/越级代理分佣规则
     * @return AgentSaleRewardCommisionConfig
     */
    public function getAgentSaleRewardCommisionConfig(): AgentSaleRewardCommisionConfig;
    
    /**
     * 计算此商品的平级/越级代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentSaleRewardCommision(int $memberId) : array;

    /**
     * 获取此商品的区域代理分佣规则
     * @return AreaAgentCommissionConfig
     */
    public function getAreaAgentCommissionConfig(): AreaAgentCommissionConfig;

    /**
     * 计算此商品的区域代理佣金
     * @param int $memberId 购买者的会员ID
     * @param int $areaId 收货人的县/区ID
     * @return array
     */
    public function calAreaAgentCommission(int $memberId,int $areaId) : array;

    /**
     * 是否为虚拟商品
     *
     * @return boolean
     */
    public function isVirtual();

    /**
     * 检测是否满足限购条件
     * @param int $quantity 准备订购的数量
     * @return bool|array 成功返回 true,否则返回数组，里面包含一些数据 [code=414,max=最大可购买数]
     */
    public function checkBuyLimit();

    /**
     * 结合当前登录会员获取商品的限购量
     * @return int -1表示不限制
     */
    public function getBuyLimit();

    /**
     * 检测是否满足最低起购量
     * @return bool|array 成功返回 true,否则返回数组，里面包含一些数据 [code=415,min=最少要购买数]
     */
    public function checkMinBuyNum();

    /**
     * 结合当前登录会员获取商品的起购量
     * @return int
     */
    public function getMinBuyNum();
}

?>