<?php
namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\AfterSaleItemModel;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierSettleItemModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierSettleModel;
use App\Modules\ModuleShop\Libs\Model\UniqueLogModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\Finance;
use YZ\Core\Model\FinanceModel;

class SupplierSettle
{
    /**
     * @param int|OrderModel $orderIdOrModel 订单ID 或 模型
     * 订单交费后，生成供应商结算记录
     */
    public static function createOrderSettle($orderIdOrModel){
        if($orderIdOrModel instanceof OrderModel){
            $orderModel = $orderIdOrModel;
        }else{
            $orderModel = OrderModel::find($orderIdOrModel);
        }
        $orderItems = $orderModel->items;
        //保存订单商品结算记录
        $orderSupplierMoney = 0;
        foreach ($orderItems as $item){
            $orderSupplierMoney += $item->supplier_price * $item->num;
            $settleItemModel = new SupplierSettleItemModel();
            $settleItemModel->fill([
                'site_id' => $item->site_id,
                'supplier_member_id' => $item->supplier_member_id,
                'order_id' => $item->order_id,
                'item_id' => $item->id,
                'money' => $item->supplier_price * $item->num,
                'after_sale_money' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            $settleItemModel->save();
        }
        //保存订单结算记录
        $settleModel = new SupplierSettleModel();
        $settleModel->fill([
            'site_id' => $orderModel->site_id,
            'status' => 0,
            'supplier_member_id' => $orderModel->supplier_member_id,
            'order_id' => $orderModel->id,
            'money' => $orderSupplierMoney,
            'freight' => $orderModel->freight,
            'after_sale_money' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        $settleModel->save();
    }

    /**
     * 当发生售后成功处理时，重算供应商结算金额
     * @param OrderModel $orderModel 订单ID 或 模型
     * @param AfterSaleModel $afterSaleModel
     * @param $freightFlag 是否扣减结算运费，true = 将结算的运费扣减为0，否则不处理，一般是在未发货并完全退单时，扣除运费
     */
    public static function deductOrderSettle(OrderModel $orderModel, AfterSaleModel $afterSaleModel){
        $uKey = static::class.'_deductSettleData_'.$afterSaleModel->id;
        if(!UniqueLogModel::newLog($uKey)) return; //设置KEY避免重复执行导致数据错误
        $settleModel = SupplierSettleModel::query()->where('order_id',$orderModel->id)->first();
        if($settleModel->status > 0) return; //已经结算成功或结算失败的记录，不允许再更改
        $orderItemList = OrderItemModel::query()->where('order_id',$orderModel->id)->get();
        $afterSaleItemList = AfterSaleItemModel::query()->where('after_sale_id',$afterSaleModel->id)->where('order_id',$orderModel->id)->get();
        $settleItemList = SupplierSettleItemModel::query()->where('order_id',$orderModel->id)->get();
        foreach ($afterSaleItemList as $afterSaleItem){
            $orderItem = $orderItemList->where('id','=',$afterSaleItem->order_item_id)->first();
            $settleItem = $settleItemList->where('item_id','=',$afterSaleItem->order_item_id)->first();
            if($settleItem && $orderItem){
                $newAfterSaleMoney = $orderItem->after_sale_over_num * $orderItem->supplier_price * -1;
                $newAfterSaleNum = $orderItem->after_sale_over_num * -1;
                $settleItem->after_sale_money = $newAfterSaleMoney;
                $settleItem->after_sale_num = $newAfterSaleNum;
                $settleItem->updated_at = Carbon::now();
                $settleItem->save();
                //print_r($settleItem->toArray());
            }
        }
        //当运费也需要退还给购买者时，售后金额加上运费
        $settleModel->after_sale_money = $settleItemList->sum('after_sale_money');
        $settleModel->after_sale_freight += abs($afterSaleModel->total_refund_freight) * -1;
        $settleModel->updated_at = Carbon::now();
        if($settleModel->money + $settleModel->after_sale_money == 0 && $settleModel->freight + $settleModel->after_sale_freight == 0){
            $settleModel->status = Constants::SupplierSettleStatus_Fail;
        }
        $settleModel->save();
        //print_r($settleModel->toArray());
    }

    /**
     * @param int|OrderModel $orderIdOrModel 订单ID 或 模型
     */
    public static function activeOrderSettle($orderIdOrModel,$clearOld = 0){
        if($orderIdOrModel instanceof OrderModel){
            $orderModel = $orderIdOrModel;
        }else{
            $orderModel = OrderModel::find($orderIdOrModel);
        }
        $settleModel = SupplierSettleModel::query()->where('order_id',$orderModel->id)->first();
        if($settleModel->status > 0) return; //已经结算成功或结算失败的记录，不允许再更改
        DB::beginTransaction();
        try {
            $settleModel->status = Constants::SupplierSettleStatus_Active;
            $settleModel->updated_at = Carbon::now();
            $settleModel->save();

            // 查询此订单是否已经有生效的正数的货款，如果有，要注意避免重复发放
            $activeCount = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_Supplier, 'status' => \YZ\Core\Constants::FinanceStatus_Active, 'sub_type' => \YZ\Core\Constants::FinanceSubType_Supplier_Goods, 'order_id' => $orderModel->id])->where('money', '>', 0)->count('id');
            // 先将此订单之前的记录删除
            if($clearOld) {
                FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_Supplier, 'status' => \YZ\Core\Constants::FinanceStatus_Active, 'sub_type' => \YZ\Core\Constants::FinanceSubType_Supplier_Goods, 'order_id' => $orderModel->id])->where('money', '>', 0)->delete();
                $activeCount = 0;
            }
            if($activeCount) {
                DB::commit();
                return;
            }
            // 记录财务记录
            $financeObj = new Finance();
            $finInfo = [
                'site_id' => $orderModel->site_id,
                'member_id' => $orderModel->supplier_member_id,
                'type' => \YZ\Core\Constants::FinanceType_Supplier,
                'sub_type' => \YZ\Core\Constants::FinanceSubType_Supplier_Goods,
                'pay_type' => \YZ\Core\Constants::FinanceInType_Reverse,
                'in_type' => \YZ\Core\Constants::FinanceInType_SupplierGoods,
                'tradeno' => 'SUPPLIER_GOODS_' . $orderModel->id,
                'order_id' => $orderModel->id,
                'terminal_type' => \YZ\Core\Constants::TerminalType_Unknown,
                'money' => $settleModel->money + $settleModel->freight + $settleModel->after_sale_money + $settleModel->after_sale_freight,
                'created_at' => date('Y-m-d H:i:s'),
                'about' => '供应商货款，订单号：' . $orderModel->id,
                'status' => \YZ\Core\Constants::FinanceStatus_Active,
                'active_at' => date('Y-m-d H:i:s')
            ];
            $financeId = $financeObj->add($finInfo);
            DB::commit();
        } catch (\Exception $ex){
            DB::rollBack();
        }
    }
}