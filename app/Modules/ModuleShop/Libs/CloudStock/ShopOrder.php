<?php

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting;
use App\Modules\ModuleShop\Libs\Dealer\DealerHelper;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderItemModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Nwidart\Modules\Collection;
use YZ\Core\Member\Auth;
use YZ\Core\Site\Site;

/**
 * 云仓进货订单类
 * Class ShopOrder
 * @package App\Modules\ModuleShop\Libs\CloudStock
 */
class ShopOrder
{
    public $productMoney = 0; //产品本身的价格
    public $totalMoney = 0; // 总金额
    protected $_memberId = 0; //会员ID
    protected $_memberObj = null; // 会员数据
    protected $_productList = []; //订单中的产品列表
    protected $_orderModel = null; //订单表记录
    protected $_remark = ''; //备注信息

    /**
     * 初始化
     * constructor.
     */
    public function __construct($memberId = 0)
    {
        if (!$memberId) $memberId = Auth::hasLogin();
        if (!$memberId) {
            throw new \Exception("meber_id is null");
        }
        $this->initMember($memberId);
    }

    protected function initMember($memberId)
    {
        if ($memberId) {
            $this->_memberId = $memberId;
            $this->_memberObj = new Member($this->_memberId);
        }
    }

    public function initByOrderId($orderIdOrModel)
    {
        if (is_string($orderIdOrModel)) {
            $this->_orderModel = CloudStockPurchaseOrderModel::find($orderIdOrModel);
        } else {
            $this->_orderModel = $orderIdOrModel;
        }
        $this->initMember($this->_orderModel->member_id);
        $items = $this->_orderModel->items;
        foreach ($items as $item) {
            $pro = new ShopProduct($this->_memberObj, $item->product_id, $item->sku_id, $item->num);
            $pro->price = $item->price;
            $this->addProduct($pro);
        }
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
     * 计算订单的产品金额
     * @return mixed
     */
    public function calProductMoney()
    {
        $this->productMoney = 0;
        foreach ($this->_productList as $item) {
            $this->productMoney += $item->calMoney();
        }
        return $this->productMoney;
    }

    public function addProduct(ShopProduct $pro)
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
     * 获取 未保存 订单中产品的信息，在下单时的结算页面用
     * @return array
     */
    public function getProductListInfo()
    {
        $productList = $this->_productList;
        $productListInfo = [];
        foreach ($productList as $item) {
            $pro = $item->getThisProductModel()->toArray();
            $sku = $item->getThisProductSkuModel()->toArray();
            // 取出来第一张图片
            $image = explode(',', $pro['small_images']);
            $pro['image'] = $image[0];
            unset($pro['small_images']);
            $pro['sku_name'] = $sku['sku_name'] ? json_decode($sku['sku_name'], true) : [];
//            $pro['image'] = $sku['sku_image'] ?: $pro['image'];
            $pro['sku_id'] = $sku['id'];
            $pro['num'] = $item->num;
            // 计算产品的会员价
            $pro['price'] = moneyCent2Yuan($item->price);
            $pro['money'] = moneyCent2Yuan($item->calMoney());
            $productListInfo[] = $pro;
        }
        //对数据按产品进行分组
        $coll = new Collection($productListInfo);
        $proList = $coll->unique('id')->values()->toArray();
        $proList = array_map(function ($item) {
            $item = ['id' => $item['id'], 'name' => $item['name'], 'image' => $item['image']];
            return $item;
        }, $proList);
        foreach ($proList as &$pro) {
            $skus = $coll->where('id', $pro['id'])->values()->toArray();
            $skus = array_map(function ($item) use ($pro) {
                $item = ['product_id' => $pro['id'], 'sku_id' => $item['sku_id'], 'sku_name' => $item['sku_name'], 'price' => $item['price'], 'num' => $item['num'], 'money' => $item['money']];
                return $item;
            }, $skus);
            $pro['skus'] = $skus;
        }
        unset($pro);
        unset($productListInfo);
        return $proList;
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
        if (moneyCent2Yuan($this->getTotalMoney()) != $originMoney['totalMoney']) {
            return trans('shop-front.shop.order_money_change');
        }
        return false;
    }

    /**
     * 保存订单
     * @return mixed
     */
    /**
     * 保存订单
     * @param array $params 额外的一些参数
     * @return array|mixed
     * @throws \Exception
     */
    public function save($params = [])
    {
        // 先检测是否开启了云仓
        $cloudStock = new CloudStock($this->_memberId);
        if (!$cloudStock->enable()) {
            return makeServiceResult('500', trans('shop-front.cloud_stock.cloud_stock_closed'));
        }
        // 检测是否能买
        $notActiveList = []; // 下架列表
        $notHasPermList = []; // 无权购买的列表
        foreach ($this->_productList as $item) {
            $res = $item->canBuy();
            if ($res['code'] != 200) {
                if ($res['data']['noperm']) $notHasPermList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId];
                elseif ($res['data']['noactive']) $notActiveList[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId];
            }
        }
        if (count($notActiveList) > 0 || count($notHasPermList) > 0) {
            return makeServiceResult(400, '', ['not_active_list' => $notActiveList, 'not_hasperm_list' => $notHasPermList]);
        }

        // 先计算下所有的价格
        $this->calProductMoney();

        //检查订单金额和数量是否足够
        $member = new Member($this->_memberId);
        $mModel = $member->getModel();
        $mainLevel = DealerLevelModel::find($mModel->dealer_level);
        $minNumFirst = $mainLevel->min_purchase_num_first; //首单最小数量
		$minNum = $mainLevel->min_purchase_num; //复购最小数量
        $minMoneyFirst = $mainLevel->min_purchase_money_first; //首单最小金额
        $minMoney = $mainLevel->min_purchase_money; //复购最小金额
        if ($mainLevel->has_hide) {
            $subLevel = DealerLevelModel::find($mModel->dealer_hide_level);
            if ($subLevel) {
                $minNumFirst = $subLevel->min_purchase_num_first; //首单最小数量
				$minNum = $subLevel->min_purchase_num; //复购最小数量
                $minMoneyFirst = $subLevel->min_purchase_money_first; //首单最小金额
                $minMoney = $subLevel->min_purchase_money; //复购最小金额
            }
        }

		// 检测是否为复购
		$hasOldOrder = CloudStockPurchaseOrderModel::query()
                ->where('site_id','=',$params['site_id'])
                ->where('payment_status','=',1)
                ->where('member_id','=',$this->_memberId)
                ->exists();
        // 最小进货量检测
		$differenceNum = 0;
		//首次购买
        if(!$hasOldOrder && $minNumFirst > 0) {
			$differenceNum = $minNumFirst - $this->getTotalProductNum();
			$minNum = $minNumFirst;
		}
		//重复购买
		if($hasOldOrder && $minNum > 0) {
			$differenceNum = $minNum - $this->getTotalProductNum();
		}

        if ($differenceNum > 0) {
            return makeServiceResult(406, '', [
                'min_num' => $minNum,
                'order_num' => $this->getTotalMoney(),
                'difference_num' => $differenceNum
            ]);
        }

		//检测最小进货金额
        $differenceMoney = 0;
		//首次购买
		if (!$hasOldOrder && $minMoneyFirst > 0) {
			$differenceMoney = $minMoneyFirst - $this->getTotalMoney();
			$minMoney = $minMoneyFirst;
		}
		//重复购买
		if ($hasOldOrder && $minMoney > 0) {
			$differenceMoney = $minMoney - $this->getTotalMoney();
		}
        if($differenceMoney > 0){
            return makeServiceResult(402, '', [
                'min_money' => moneyCent2Yuan($minMoney),
                'order_money' => moneyCent2Yuan($this->getTotalMoney()),
                'difference_money' => moneyCent2Yuan($differenceMoney)
            ]);
        }

        // 保存前 和之前计算的金额做一下对比 如果金额有变化 需要提醒用户
        // 如果有goBuy 说明用户点击了继续购买
        if ($params && $params['goBuy'] != 1 && $params['originMoneyData']) {
            $msg = $this->beforeSave($params);
            if ($msg) {
                return makeServiceResult(405, $msg);
            }
        }
        $cloudStockId = CloudStock::getCloudStockParent($this->_memberId);
        if (!$cloudStockId) {
            $cloudStockId = 0;
        }
        // 上级付款人
        $parentMemberId = 0;
        $dealerBaseSetting = DealerBaseSetting::getCurrentSiteSetting();
        if ($dealerBaseSetting->purchases_money_target == 1) {
            $parents = DealerHelper::getParentDealers($this->_memberId, false);
            $parentMemberId = is_array($parents['normal']) && count($parents['normal']) ? $parents['normal'][0]['id'] : 0;
        }


        DB::beginTransaction();
        try {
            // 保存到数据库
            $mOrder = new CloudStockPurchaseOrderModel();
            $orderId = generateOrderId();
            $mOrder->id = $orderId;
            $mOrder->site_id = Site::getCurrentSite()->getSiteId();
            $mOrder->store_id = 0;
            $mOrder->member_id = $this->_memberId;
            $mOrder->status = 0;
            $mOrder->total_money = $this->getTotalMoney();
            $mOrder->transaction_id = '';
            $mOrder->remark = $this->_remark;
            $mOrder->created_at = date('Y-m-d H:i:s');
            $mOrder->cloudstock_id = $cloudStockId;
            $mOrder->payee = $parentMemberId;
            // 更新log
            $mOrder->order_log = Constants::getCloudStockOrderLogText(0);
            $mOrder->save();
            $this->_orderModel = $mOrder;

            // 保存订单商品列表
            foreach ($this->_productList as $item) {
                $mItem = new CloudStockPurchaseOrderItemModel();
                $mItem->site_id = Site::getCurrentSite()->getSiteId();
                $mItem->order_id = $orderId;
                $mItem->product_id = $item->productId;
                $mItem->sku_id = $item->skuId;
                $mItem->name = $item->name;
                $mItem->image = $item->image;
                $mItem->sku_names = json_encode($item->skuName, JSON_UNESCAPED_UNICODE);
                $mItem->price = $item->price;
                $mItem->num = $item->num;
                $mItem->money = $item->calMoney();
                $mItem->cloudstock_id = $cloudStockId;
                $mItem->save();
            }

            // 清除购物车里的相关数据
            $skusToRemove = [];
            foreach ($this->_productList as $item) {
                $skusToRemove[] = ['product_id' => $item->productId, 'sku_id' => $item->skuId];
            }
            if (count($skusToRemove)) {
                $cart = new ShopCart($this->_memberId);
                $cart->removeSku($skusToRemove);
            }
            DB::commit();

            // 记录订单历史
            FrontPurchaseOrder::buildOrderMembersHistory($orderId);

            return makeServiceResultSuccess('ok', ['order_id' => $orderId, 'money' => moneyCent2Yuan($this->getTotalMoney())]);
        } catch (\Exception $e) {
            DB::rollBack();
            return makeServiceResult(500, trans('shop-front.shop.create_order_error') . " - " . $e->getMessage());
        }
    }

    public function getTotalMoney()
    {
        $this->totalMoney = $this->productMoney;
        return $this->totalMoney;
    }

    public function getTotalProductNum()
    {
        $num = 0;
        foreach ($this->_productList as $item) {
            $num += $item->num;
        }
        return $num;
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
                //更改状态
                $this->_orderModel->status = Constants::CloudStockPurchaseOrderStatus_Cancel;
                // 取消原因
                $this->_orderModel->cancel_message = $msg;
                $this->_orderModel->end_at = date('Y-m-d H:i:s');
                $this->_orderModel->save();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw new \Exception(trans('shop-front.refund.cancel_order_fail'));
            }
        }
    }
}
