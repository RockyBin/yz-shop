<?php
/**
 * 前端提货订单
 * User: liyaohui
 * Date: 2019/8/31
 * Time: 11:17
 */

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderItemModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Model\StoreConfigModel;
use App\Modules\ModuleShop\Libs\Shop\BaseCalOrderFreight;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberAddressModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use Illuminate\Foundation\Bus\DispatchesJobs;
use YZ\Core\Constants as CoreConstants;
use Illuminate\Support\Facades\Redis;

class FrontTakeDeliveryOrder
{
    use DispatchesJobs;
    protected $_siteId;
    protected $_memberId;
    private $orderItemList;

    public function __construct($memberId)
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_memberId = $memberId;
    }

    /**
     * 获取订单列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getList($params, $page = 1, $pageSize = 15)
    {
        $query = CloudStockTakeDeliveryOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $this->_memberId)
            ->with(['item' => function ($with) {
                $with->select(['order_id', 'sku_names', 'image', 'name', 'num', 'logistics_id', 'is_virtual']);
            }]);
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        $list = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->orderByDesc('created_at')
            ->select(['id', 'status', 'logistics_id', 'product_num', 'freight', 'virtual_flag'])
            ->get();
        $hasNextPage = false;
        // 有下一页
        if ($list->count() > $pageSize) {
            $hasNextPage = true;
            $list->pop();
        }
        foreach ($list as &$item) {
            $item->status_text = self::getOrderStatusText($item->status);
            // sku name做一下处理
            foreach ($item->item as $val) {
                $val->sku_names = $val->sku_names ? json_decode($val->sku_names, true) : [];
            }
            $item->freight = moneyCent2Yuan($item->freight);
        }
        return [
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'list' => $list
        ];
    }

    /**
     * 获取单个订单详情
     * @param string $orderId
     * @return array
     * @throws \Exception
     */
    public function getOrderInfo($orderId)
    {
        $orderModel = $this->getOrderModel($orderId);
        // 获取订单中商品
        $orderModel->load(['item' => function ($item) {
            $item->select([
                'order_id',
                'name',
                'image',
                'sku_names',
                'num',
                'logistics_id',
                'is_virtual'
            ]);
        }]);
        // 是否需要获取商家电话号码
        if (
            $orderModel->status == Constants::CloudStockTakeDeliveryOrderStatus_Delivered
            || $orderModel->status == Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver
        ) {
            $customMobile = StoreConfigModel::query()
                ->where('site_id', $this->_siteId)
                ->where('store_id', 0)
                ->value('custom_mobile');
            $orderModel->custom_mobile = $customMobile;
        }
        $orderModel->status_text = $this->getOrderStatusText($orderModel->status);
        $orderModel->cancel_message = Constants::getCloudStockOrderCancelReasonText($orderModel->cancel_message);
        $orderModel->freight = moneyCent2Yuan($orderModel->freight);
        // sku name做一下处理
        foreach ($orderModel->item as &$val) {
            $val->sku_names = $val->sku_names ? json_decode($val->sku_names, true) : [];
        }
        $orderArray = $orderModel->toArray();
        unset($orderArray['remark_inside']);
        return $orderArray;
    }

    /**
     * 取消订单
     * @param int $memberId 会员ID
     * @param string $orderId 订单ID
     * @param string $reason 取消原因
     * @throws \Exception
     */
    public function cancel($memberId, $orderId, $reason)
    {
        $order = CloudStockTakeDeliveryOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $memberId)
            ->where('id', $orderId)
            ->where('status', Constants::CloudStockTakeDeliveryOrderStatus_Nopay)
            ->first();
        if ($order) {
            DB::beginTransaction();
            $items = $order->item()->get();
            foreach ($items as $item) {
                CloudStockSku::cloudStockRefundTakeDelivery(
                    $item['product_id'],
                    $item['sku_id'],
                    $memberId,
                    $order->id,
                    $item['id'],
                    $item['num']
                );
            }
            $order->status = Constants::CloudStockTakeDeliveryOrderStatus_Cancel;
            $order->cancel_message = $reason;
            $order->save();
            DB::commit();
        } else {
            DB::rollBack();
            throw new \Exception('订单不存在');
        }
    }

    /**
     * 获取订单model
     * @param string $orderId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getOrderModel($orderId)
    {
        if (!$orderId) {
            throw new \Exception(trans('shop-front.shop.data_error'));
        }
        $orderModel = CloudStockTakeDeliveryOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $this->_memberId)
            ->where('id', $orderId)
            ->first();
        if (!$orderModel) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
        return $orderModel;
    }

    /**
     * 获取
     * @param array $ids
     * @return array
     * @throws \Exception
     */
    public function getCreateOrderData($ids, $addressId)
    {
        if (!$ids) {
            throw new \Exception(trans('shop-front.shop.data_error'));
        }
        // 根据购物车传入的id 获取商品列表
        $shoppingCart = new TakeDeliveryShoppingCart($this->_memberId);
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $list = $shoppingCart->getSelectProductList($ids);
        if (!$list) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }

        $address = $addressId;
        $calFreight = 0;
        // 虚拟商品标记，0=非虚拟商品(必定含有一个实物商品,就必须去计算运费)，1=全部为虚拟商品
        $virtualFlag = 1;
        foreach ($list as $item) {
            if ($item['info']['product_type'] == 0) {
                $virtualFlag = 0;
                break;
            }
        }

        $addressText = $this->getAddressInfo($address, false);
        // 如果有地址就计算运费
        if ($address && $virtualFlag == 0) {
            $freightProductList = $shoppingCart->getSelectProductList($ids, false);
            $calFreight = $this->calFreight($address, $freightProductList);
            if ($calFreight === false) {
                return makeServiceResult(400, '', ['productList' => $list, 'address' => $addressText]);
            }
        }
        $freight = moneyCent2Yuan($calFreight);
        return makeServiceResult(200, '', ['productList' => $list, 'address' => $addressText, 'freight' => $freight, 'virtual_flag' => $virtualFlag]);
    }


    function calFreight($adressId, $productList)
    {
        // 计算运费
        $addressFreight = MemberAddressModel::find($adressId);
        // 虚拟商品标记，0=非虚拟商品(只要含有一个实物商品,就必须去计算运费)，1=全部为虚拟商品(不需要计算运费)
        $virtualFlag = 1;
        foreach ($productList as $item) {
            if ($item->product_type == 0) {
                $virtualFlag = 0;
                break;
            }
        }
        if ($virtualFlag == 1) return 0;
        foreach ($productList as $item) {
            $item->num = $item->product_quantity;
            $item->weight = $item->sku_weight ? $item->sku_weight : $item->cart_weight;
        }
        $calFreight = new BaseCalCloudStockOrderFreight($addressFreight['city'], $productList);
        $canDelivery = $calFreight->canDelivery();
        if (!$canDelivery) return false;
        $freight = $calFreight->getOrderFreight();
        return $freight;
    }

    /**
     * 创建提货订单
     * @param array $ids 购物车记录id
     * @param int $addressId
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function createOrder(array $ids, int $addressId, $data = [])
    {
        if (!$ids) {
            throw new \Exception(trans('shop-front.shop.data_error'));
        }
        if (!$addressId) {
            throw new \Exception(trans('shop-front.shop.must_choose_the_shipping_address'));
        }
        DB::beginTransaction();
        try {
            $addressData = $this->getAddressInfo($addressId);
            $shoppingCart = new TakeDeliveryShoppingCart($this->_memberId);
            $productList = $shoppingCart->getSelectProductList($ids, false);
            if ($productList->count() < 1) {
                throw new \Exception(trans('shop-front.shop.cant_found'));
            }
            $memberModel = ((new Member($this->_memberId))->getModel());

            // 有隐藏等级按隐藏等级的算
            $minTakeDeliveryNum = $memberModel->dealerHideLevel ? $memberModel->dealerHideLevel->min_take_delivery_num : $memberModel->dealerLevel->min_take_delivery_num;
            $skuCartCount = 0;
            foreach ($productList as $item) {
                $skuCartCount += $item->product_quantity;
            }
            if ($minTakeDeliveryNum && $skuCartCount < $minTakeDeliveryNum) {
                DB::rollBack();
                return makeServiceResult(405, '还差' . abs($skuCartCount - $minTakeDeliveryNum) . '件商品就可以提交订单啦', ['hide' => $memberModel->dealerHideLevel]);
            }
            $items = [];
            $productTotal = 0; // 订单商品总数
            // 如果有传入update_inventory 则是不使用购物车的商品数量 使用传入的数量
            $updateInventory = is_array($data['update_inventory']) && count($data['update_inventory'])
                ? $data['update_inventory']
                : [];
            // 虚拟商品标记，0=非虚拟商品，1=全部为虚拟商品，2=混合（同时有虚拟和非虚拟）
            $virtualFlagArray = [];
            foreach ($productList as &$item) {
                // 如果有更新数量 则使用传入的数量
                $item->product_quantity = isset($updateInventory['id-' . $item->id]) && $updateInventory['id-' . $item->id] > 0
                    ? $updateInventory['id-' . $item->id]
                    : $item->product_quantity;
                $items[] = [
                    'id' => $item->cloud_stock_sku_id,
                    'num' => $item->product_quantity
                ];
                $productTotal += $item->product_quantity;
                $item->num = $item->product_quantity;
                $item->is_virtual = $item->product_type;
                array_push($virtualFlagArray, $item->product_type);
            }
            // 虚拟商品标记，0=非虚拟商品，1=全部为虚拟商品，2=混合（同时有虚拟和非虚拟）
            $uniqueVirtualFlagArray = array_unique($virtualFlagArray);
            if (count($uniqueVirtualFlagArray) == 1) {
                if ($uniqueVirtualFlagArray[0] == 0) $virtualFlag = 0;
                else if ($uniqueVirtualFlagArray[0] == 1) $virtualFlag = 1;
            } else {
                // 如果去除掉所有重复的值，大于1证明这笔订单是混合
                $virtualFlag = 2;
            }
            // 检测库存
            $checkRes = $shoppingCart->checkInventory($items, false);
            // 库存不足 直接把库存信息返回
            if (!$checkRes['all_enough']) {
                DB::rollBack();
                return makeServiceResult(402, trans('shop-front.shop.inventory_not_enough'), $checkRes);
            }
            // 运费价格
            $freight = $this->calFreight($addressId, $productList);
            // 库存满足 直接生成订单
            // 保存订单数据
            $orderId = generateOrderId();
            $orderModel = new CloudStockTakeDeliveryOrderModel();
            $orderModel->id = $orderId;
            $orderModel->site_id = $this->_siteId;
            $orderModel->member_id = $this->_memberId;
            $orderModel->remark = $data['remark'] ? trim($data['remark']) : '';
            $orderModel->product_num = $productTotal;
            // 运费等于0时，状态直接变成已付款 待发货 状态
            $orderModel->status = $freight == 0 ? Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver : Constants::CloudStockTakeDeliveryOrderStatus_Nopay;
            $orderModel->freight = $freight;
            $orderModel->virtual_flag = $virtualFlag;
            $orderModel->fill($addressData)->save();
            // 保存订单 item数据 扣取库存
            $this->saveOrderItems($orderId, $productList);
            $this->createdOrderAfter(['ids' => $ids], $orderModel);
            DB::commit();
            MessageNotice::dispatch(CoreConstants::MessageType_Order_NewPay, $orderModel);
            return makeServiceResultSuccess('ok', ['order_id' => $orderId, 'freight' => $freight]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 创建订单成功后的逻辑
     * @param $data
     * @param $orderModel
     */
    private function createdOrderAfter($data, $orderModel)
    {
        // 删除购物车数据
        if (is_array($data['ids'])) {
            (new TakeDeliveryShoppingCart($this->_memberId))->remove($data['ids']);
        }
        if ($orderModel) {
            //用于发送通知
            $orderItemModel = new CloudStockTakeDeliveryOrderItemModel();
            $orderFirstItemModel = $orderItemModel->where(['order_id' => $orderModel->id])->first();
            $cloudStockSku = new CloudStockSku($orderModel->member_id, $orderFirstItemModel->product_id, $orderFirstItemModel->sku_id);
            $num_after = $cloudStockSku->getModel()->inventory;
            $extend['num'] = $orderFirstItemModel->num;
            $extend['num_before'] = $cloudStockSku->getModel()->inventory + $orderFirstItemModel->num;
            $extend['num_after'] = $num_after;
            $extend['member_id'] = $orderModel->member_id;
            $extend['take_delivery_order'] = true;
            $extend['created_at'] = $orderModel->created_at;
            $RedisKey = $orderModel->id . $orderModel->member_id;
            if (!Redis::exists($RedisKey)) {
                Redis::setex($RedisKey, 60, '');
                MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Inventory_Change, $orderFirstItemModel, $extend);
            }
        }

    }

    /**
     * 支付订单
     * @param int $memberId 会员ID
     * @param string $orderId 订单ID
     * @param int $payType 支付类型
     * @param $vouchers 支付凭证
     *   当使用余额支付时，它是支付密码，此时数据格式为字符串
     *   当使用线下支付时，它是用户上传的线下支付凭证图片(最多三张)，此时数据格式为 \Illuminate\Http\UploadedFile|array $voucherFiles
     *   当使用线上支付时，它是支付成功后的入账财务记录
     * @throws \Exception
     */
    public function pay($memberId, $orderId, $payType, $vouchers)
    {
        $config = static::getPayConfig();
        $coll = new Collection($config['types']);
        $curConfig = $coll->where('type', $payType)->values()->first();
        if (!$curConfig) {
            throw new \Exception('支付方式错误，无法支付方式');
        }
        $order = CloudStockTakeDeliveryOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $memberId)
            ->where('id', $orderId)
            ->first();
        // 判断订单是否已经支付过
        if ($order->status != Constants::CloudStockTakeDeliveryOrderStatus_Nopay) {
            return makeApiResponseFail(trans('shop-front.shop.order_paid'));
        }
        // 余额支付的情况
        if ($payType == CoreConstants::PayType_Balance) {
            // 如果是余额支付 要验证支付密码
            $member = new Member($memberId);
            if ($member->payPasswordIsNull()) {
                return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
            }
            if (!$member->payPasswordCheck($vouchers)) {
                return makeApiResponse(406, trans('shop-front.shop.pay_password_error'));
            }
            // 扣钱
            $payInfo = ['pay_type' => $payType, 'tradeno' => 'PAYORDER_' . $order->id];
            $financeId = FinanceHelper::payOrder($order->site_id, $order->member_id, $order->id, $order->freight,
                $payInfo, CoreConstants::FinanceOrderType_CloudStock_TakeDelivery);
            $order->status = Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver;
        }
        // 线上支付的情况
        if (in_array($payType, \YZ\Core\Constants::getOnlinePayType())) {
            $financeId = FinanceHelper::payOrder($order->site_id, $order->member_id, $order->id, $order->freight,
                $vouchers, CoreConstants::FinanceOrderType_CloudStock_TakeDelivery);
            $order->status = Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver;
        }

        $order->pay_type = $payType;
        $order->pay_at = date('Y-m-d H:i:s');
        $order->save();
        //消息通知
        // $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_PaySuccess, $order));
        // $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_NewPay, $order));
        // $this->dispatch(new MessageNotice(CoreConstants::MessageType_CloudStock_Purchase_Commission_Under, $order));

        return makeApiResponse(200, 'ok');
    }

    /**
     * 保存订单item
     * @param string $orderId
     * @param array $products
     * @throws \Exception
     */
    public function saveOrderItems($orderId, $products)
    {
        $returnData = [];
        foreach ($products as $pro) {
            // 使用最新的商品数据
            $pro['product_name'] = $pro['pro_name'] ?: $pro['product_name'];
            // sku 图片 优先使用sku的图片 如果
            $pro['product_image'] = $pro['sku_image']
                ?: ($pro['pro_images']
                    ? explode(',', $pro['pro_images'])[0]
                    : $pro['product_image']);
            $pro['product_skus_name'] = $pro['pro_sku_name'] ?: $pro['product_skus_name'] ?: '[]';
            $returnData[] = [
                'site_id' => $this->_siteId,
                'order_id' => $orderId,
                'product_id' => $pro['product_id'],
                'sku_id' => $pro['product_skus_id'],
                'name' => $pro['product_name'],
                'image' => $pro['product_image'],
                'sku_names' => $pro['product_skus_name'],
                'num' => $pro['product_quantity'],
                'cloud_stock_sku_id' => $pro['cloud_stock_sku_id'],
                'is_virtual' => $pro['is_virtual']
            ];
        }
        // 保存数据
        CloudStockTakeDeliveryOrderItemModel::query()->insert($returnData);
        $data = CloudStockTakeDeliveryOrderItemModel::query()
            ->where('site_id', $this->_siteId)
            ->where('order_id', $orderId)
            ->get();
        foreach ($data as $item) {
            // 扣库存 生成log
            CloudStockSku::cloudStockCreateTakeDelivery(
                $item['product_id'],
                $item['sku_id'],
                $this->_memberId,
                $orderId,
                $item['id'],
                $item['num']
            );
        }
    }

    /**
     * 获取地址数据
     * @param int|\Illuminate\Database\Eloquent\Model $idOrModel
     * @param bool $throw 是否抛出错误
     * @return array
     * @throws \Exception
     */
    public function getAddressInfo($idOrModel, $throw = true)
    {
        if ($idOrModel instanceof MemberAddressModel) {
            $addressModel = $idOrModel;
        } else {
            $addressModel = MemberAddressModel::find($idOrModel);
        }
        if (!$addressModel) {
            if ($throw) {
                throw new \Exception(trans('shop-front.shop.must_choose_the_shipping_address'));
            } else {
                return [];
            }
        } else {
            $addressText = $addressModel->addressText();
            return [
                'address_id' => $addressModel->id,
                'country' => $addressModel->country,
                'prov' => $addressText['prov'],
                'city' => $addressText['city'],
                'area' => $addressText['area'],
                'receiver_address' => $addressModel->address,
                'receiver_name' => $addressModel->name,
                'receiver_tel' => $addressModel->phone,
            ];
        }
    }

    /**
     * 确认收货
     * @param string $orderId
     * @return bool
     * @throws \Exception
     */
    public function receipt($orderId)
    {
        $orderModel = $this->getOrderModel($orderId);
        // 状态不是待收货
        if ($orderModel->status != Constants::CloudStockTakeDeliveryOrderStatus_Delivered) {
            return false;
        }
        $orderModel->status = Constants::CloudStockTakeDeliveryOrderStatus_Finished;
        $orderModel->receive_at = Carbon::now();
        return $orderModel->save();
    }

    /**
     * TODO 关闭订单 退库存逻辑  暂时未实现
     * @param $orderId
     * @throws \Exception
     */
    public function cancelOrder($orderId)
    {
        DB::beginTransaction();
        try {
            $orderModel = $this->getOrderModel($orderId);
            // 没有发货的才可以关闭
            if (
                $orderModel->status != Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver
                || $orderModel->delivery_status != Constants::CloudStockTakeDeliveryOrderDeliverStatus_No
            ) {
                throw new \Exception(trans('shop-front.shop.data_error'));
            }
            // 修改状态
            $orderModel->status = Constants::CloudStockTakeDeliveryOrderStatus_Cancel;
            $orderModel->save();
            // 退库存
            $items = CloudStockTakeDeliveryOrderItemModel::query()
                ->where('site_id', $this->_siteId)
                ->where('member_id', $this->_memberId)
                ->where('order_id', $orderId)
                ->select(['num', 'cloud_stock_sku_id'])
                ->get()->toArray();
            // TODO
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 获取提货订单状态文案
     * @param int $status
     * @return string
     */
    public static function getOrderStatusText(int $status)
    {
        // 暂时调用后台的文案 有需要再改
        return AdminTakeDeliveryOrder::getOrderStatusText($status);
    }

    /**
     * 返回一个订单实例
     * @param $orderId
     * @param int $siteId
     * @return Order
     */
    public static function find($orderId, $siteId = 0)
    {
        $order = New FrontTakeDeliveryOrder($siteId);
        $order->setOrder($orderId);
        return $order;
    }

    /**
     * 实例化
     * @param $orderIdOrModal
     */
    public function setOrder($orderIdOrModal)
    {
        if (is_numeric($orderIdOrModal)) {
            $this->findById($orderIdOrModal);
        } else {
            $this->init($orderIdOrModal);
        }
    }

    /**
     * 根据订单id查找
     * @param $orderId
     */
    private function findById($orderId)
    {
        $query = CloudStockTakeDeliveryOrderModel::query()->where('id', $orderId);
        if ($this->siteId) {
            $query->where('site_id', $this->siteId);
        }
        $this->init($query->first());
    }

    /**
     * 初始化数据，并获取订单产品列表
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            // 获取明细，按发货码
            $this->orderItemList = CloudStockTakeDeliveryOrderItemModel::query()
                ->from('tbl_cloudstock_take_delivery_order_item as item')
                ->leftJoin('tbl_logistics as logistics', 'item.logistics_id', '=', 'logistics.id')
                ->where('item.site_id', $model->site_id)
                ->where('item.order_id', $model->id)
                ->select('item.*', 'logistics.logistics_name', 'logistics.logistics_no', 'logistics.logistics_company')
                ->get();
        }
    }

    public function getOrderItemList()
    {
        return $this->orderItemList;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public static function getPayConfig()
    {
        $payConfig = (new PayConfig(-1))->getInfo(true);
        $config = ['types' => []];
        $config['types'][] = [
            'type' => \YZ\Core\Constants::PayType_Balance,
            'text' => "余额",
            'group' => "online",
            'account' => 'balance'
        ];
        if ($payConfig->type['wxpay']) {
            if ($payConfig->wxpay_online_entrance['pay_cloudstock_take_delivery_freight']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Weixin,
                    'text' => "微信钱包",
                    'group' => "online",
                    'account' => 'weixin'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxOfficialAccount && getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp && $payConfig->type['alipay']) {
            if ($payConfig->alipay_online_entrance['pay_cloudstock_take_delivery_freight']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Alipay,
                    'text' => "支付宝",
                    'group' => "online",
                    'account' => 'alipay'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp && $payConfig->type['tlpay']) {
            if ($payConfig->tlpay_online_entrance['pay_cloudstock_take_delivery_freight']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_TongLian,
                    'text' => "通联支付",
                    'group' => "online",
                    'account' => 'tlpay'
                ];
            }
        }
        return $config;
    }

}
