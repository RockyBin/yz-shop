<?php
namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierBaseSettingModel;

trait SupplierShopProductTrait
{
    private function getBaseSetting(){
        if (!$this->_supplierBaseSetting){
            $this->_supplierBaseSetting = SupplierBaseSettingModel::find($this->_productModel->site_id);
        }
        return $this->_supplierBaseSetting;
    }

    /**
     * 获取此商品的会员单价
     * @return int $money
     */
    public function getMemberPrice($userLevel,$rule = null){
        if (!$this->getBaseSetting()->open_member_price) {
            $this->money = $this->_productSku->price;
        }else{
            parent::getMemberPrice($userLevel,$rule);
        }
        return $this->money;
    }

    /**
     * 此产品是否可以使用积分
     * @return mixed
     */
    public function canUsePoint(){
        if (!$this->getBaseSetting()->open_point) {
            return false;
        }else{
            return parent::canUsePoint();
        }
    }

    /**
     * 判断此产品是否能使用某张优惠券
     * @param $orderMoney 订单商品的总金额
     * @param $couponId
     * @return mixed
     */
    public function canUseCoupon($orderMoney,$couponId){
        if (!$this->getBaseSetting()->open_coupon) {
            return false;
        }else{
            return parent::canUseCoupon($orderMoney, $couponId);
        }
    }

    /**
     * 判断会员能否购买此商品，要检测会员限制规则和库存
     * @param int $memberId 会员ID
     * @param int $quantity 订购数量
     * @return mixed
     */
    public function checkBuyPerm(){
        if (!$this->getBaseSetting()->buy_perm) {
            //跟随系统
            return parent::checkBuyPerm();
        } else {
            //自定义，这期暂时不实现
            return parent::checkBuyPerm();
        }
    }

    /**
     * 计算此商品的分销金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额，格式如
     * ['memberId1' => money1,'memberId2' => money2]
     */
    public function calDistribution(int $memberId) : array{
        if (!$this->getBaseSetting()->open_distribution) {
            return [];
        } else {
            return parent::calDistribution($memberId);
        }
    }

    /**
     * 计算此商品的正常团队代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentOrderCommision(int $memberId) : array{
        if (!$this->getBaseSetting()->open_agent) {
            return [];
        } else {
            return parent::calAgentOrderCommision($memberId);
        }
    }

    /**
     * 计算此商品的平级/越级代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentSaleRewardCommision(int $memberId) : array{
        if (!$this->getBaseSetting()->open_agent) {
            return [];
        } else {
            return parent::calAgentSaleRewardCommision($memberId);
        }
    }

    /**
     * 计算此商品的区域代理佣金
     * @param int $memberId 购买者的会员ID
     * @param int $areaId 收货人的县/区ID
     * @return array
     */
    public function calAreaAgentCommission(int $memberId,int $areaId) : array{
        if (!$this->getBaseSetting()->open_area_agent) {
            return [];
        } else {
            return parent::calAreaAgentCommission($memberId,$areaId);
        }
    }
}