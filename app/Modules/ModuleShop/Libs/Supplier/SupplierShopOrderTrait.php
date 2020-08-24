<?php
namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;

trait SupplierShopOrderTrait
{
    /**
     * 订单交费后，生成供应商结算记录
     */
    public function createSettleData(){
        $orderModel = $this->getOrderModel();
        SupplierSettle::createOrderSettle($orderModel);
    }

    /**
     * 当发生售后成功处理时，重算供应商结算金额
     * @param AfterSaleModel $afterSaleModel
     * @param $freightFlag 是否扣减结算运费，true = 将结算的运费扣减为0，否则不处理，一般是在未发货并完全退单时，扣除运费
     */
    public function deductSettleData(AfterSaleModel $afterSaleModel){
        $orderModel = $this->getOrderModel();
        SupplierSettle::deductOrderSettle($orderModel,$afterSaleModel);
    }

    /**
     * 将订单结算数据转为生效
     * @param int $clearOld
     */
    public function activeSettleData($clearOld = 0){
        $orderModel = $this->getOrderModel();
        SupplierSettle::activeOrderSettle($orderModel);
    }
}