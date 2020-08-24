<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/15
 * Time: 15:34
 */

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;

/**
 * 购物订单接口，实现这个接口的实例类更多是面向下单过程、算价、结算这些，并不是面向订单管理，订单管理部分最好是新建不同的类来处理
 * Interface IShopOrder
 * @package App\Modules\ModuleShop\Libs\Shop
 */
interface IShopOrder
{
    /**
     * 根据订单ID或订单的主数据库记录初始化订单
     * @param $orderIdOrModel
     * @return mixed
     */
    public function initByOrderId($orderIdOrModel);

    /**
     * 设置订单的送货地址
     * @param $addressId 用户收货地址ID
     * @return mixed
     */
    public function setAddressId($addressId);

    /**
     * 添加备注信息
     * @param $remark
     */
    public function setRemark($remark);

    /**
     * 设置订单使用的优化券
     * @param $couponItemId 优惠券的ID
     * @return mixed
     */
    public function setCouponID($couponItemId);

    /**
     * 检测此订单是否能使用某张优惠券,要先调用 calProductMoney() 再调用此方法
     * @param $couponItemId 优惠券的ID
     * @return mixed
     */
    public function canUseCoupon($couponItemId);

    /**
     * 计算订单的产品金额(不含优惠部分)
     * @return mixed
     */
    public function calProductMoney();

    /**
     * 计算订单的总运费
     * @return mixed
     */
    public function calFreight();

    /**
     * 计算订单的积分，要先调用 calProductMoney(),calCoupon() 再调用此方法
     * @return mixed
     */
    public function calPoint();

    /**
     * 计算订单的优惠券金额，要先调用 calProductMoney() 再调用此方法
     * @return mixed
     */
    public function calCoupon();

    /**
     * 计算订单的其它优惠（可能包含算优惠券，算积分，以及其它优惠），这个过程抽出独立是为了后面不同的订单类型，它的优惠结算流程可能变化比较大，这样可以在此过程中控制算优惠的流程
     * @return mixed
     */
    public function calDiscount();

    /**
     * 计算分销佣金
     * @return mixed
     */
    public function calDistribution();

    /**
     * 计算分销佣金并保存
     * @return mixed
     */
    public function doDistribution();

    /**
     * 扣减分销佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductDistributionCommision();

    /**
     * 计算代理分佣金额
     * @return mixed
     */
    public function calAgentOrderCommision();

    /**
     * 计算代理分佣金额并保存
     * @return mixed
     */
    public function doAgentOrderCommision();

    /**
     * 扣减代理正常佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductAgentOrderCommision();

    /**
     * 计算代理销售奖(平级/越级奖)分佣金额
     * @return mixed
     */
    public function calAgentSaleRewardCommision();

    /**
     * 计算代理销售奖(平级/越级奖)分佣金额并保存
     * @return mixed
     */
    public function doAgentSaleRewardCommision();

    /**
     * 扣减代理销售奖佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductAgentSaleRewardCommision();

    /**
     * 计算代理其他奖的奖金
     * @return mixed
     */
    public function doAgentOtherRewardCommision();

    /**
     * 扣减代理其他奖的奖金，一般在发生售后时使用
     * @return mixed
     */
    public function deductAgentOtherRewardCommision();

	/**
     * 计算区域代理佣金
     * @return mixed
     */
    public function calAreaAgentCommission();

    /**
     * 计算区域代理佣金并保存
     * @return mixed
     */
    public function doAreaAgentCommission();

    /**
     * 扣减区域代理佣金，一般在发生售后时使用
     * @return mixed
     */
    public function deductAreaAgentCommission();

    /**
     * 添加商品到订单
     * @param IShopProduct $pro
     * @return mixed
     */
    public function addProduct(IShopProduct $pro);

    /**
     * 保存订单
     * @param array $params     额外的一些参数
     * @return mixed
     */
    public function save($params);

    /**
     * 计算订单的总金额（包含产品金额，运费，税费等）
     * @return mixed
     */
    public function calTotalMoney();

    /**
     * 返回订单的总金额（
     * @return mixed
     */
    public function getTotalMoney();

    /**
     * 订单交费
     * @param $payInfo，支付相关信息，如支付类型，相关交易号等
     * @return mixed
     */
    public function pay(array $payInfo);

    /**
     *支付成功后 订单分佣 处理分销、代理升级 更新会员统计数据等
     */
    public function payAfterUpdateMemberAndCommission();

    /**
     * 订单支付成功后的一些操作
     * @param $payInfo
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function payAfter($payInfo);

    /**
     * 取消订单，只有已付款未发货和未付款状态可以调用此方法
     * @return mixed
     */
    public function cancel();

    /**
     * 订单结束
     * @param int $type 0 = 付款后过维权期正常结束，1=部分商品退款结束
     * @return mixed
     */
    public function finish(int $type = 0);

    /**
     * 订单交费成功后，进行的一些操作，比如虚拟产品的开通等
     * @return mixed
     */
    public function afterSettlement();

    /**
     * 确认收货
     * @return mixed
     */
    public function receipt();

    /**
     * 返回订单里的商品的虚拟标记(虚拟商品标记，0=非虚拟商品，1=全部为虚拟商品，2=混合（同时有虚拟和非虚拟）)
     *
     * @return int
     */
    public function getVirtualFlag();

    /**
     * 当发生售后成功处理时，重算供应商结算金额
     * @param AfterSaleModel $afterSaleModel
     * @param $freightFlag 是否扣减结算运费，true = 将结算的运费扣减为0，否则不处理，一般是在未发货并完全退单时，扣除运费
     */
    public function deductSettleData(AfterSaleModel $afterSaleModel);
}