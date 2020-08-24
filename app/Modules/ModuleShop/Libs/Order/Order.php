<?php

namespace App\Modules\ModuleShop\Libs\Order;

use App\Modules\ModuleShop\Jobs\OrderExpressSyncJob;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentCommission;
use App\Modules\ModuleShop\Libs\Express\ExpressConstants;
use App\Modules\ModuleShop\Libs\Express\ExpressHelper;
use App\Modules\ModuleShop\Libs\Express\ExpressSetting;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AfterSaleItemModel;
use App\Modules\ModuleShop\Libs\Model\OpLogModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierSettleModel;
use App\Modules\ModuleShop\Libs\OpLog\OpLog;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use YZ\Core\Site\Site;
use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\LogisticsModel;

class Order
{
    private $siteId = -1; // 站点ID
    private $orderModal = null; // 订单模块数据
    private $orderItemList = []; // 订单产品列表模型数据

    /**
     * 初始化
     * Order constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        $this->orderItemList = new Collection();
        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 返回一个订单实例
     * @param $orderId
     * @param int $siteId
     * @return Order
     */
    public static function find($orderId, $siteId = 0)
    {
        $order = New Order($siteId);
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
     * 订单是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->orderModal && $this->orderModal->id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 返回订单的数据模型
     * @return null
     */
    public function getModel()
    {
        return $this->orderModal;
    }

    /**
     * 返回订单id
     * @return int
     */
    public function getOrderId()
    {
        if ($this->checkExist()) {
            return $this->getModel()->id;
        } else {
            return 0;
        }
    }

    /**
     * 尝试获取siteId
     * @return int|mixed
     */
    public function getSiteId()
    {
        if ($this->checkExist()) {
            return $this->getModel()->site_id;
        } else if ($this->siteId) {
            return $this->siteId;
        } else {
            return Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 获取会员id
     * @return int
     */
    public function getMemberId()
    {
        if ($this->checkExist()) {
            return $this->getModel()->member_id;
        } else {
            return 0;
        }
    }

    /**
     * 构造查询语句
     * @param $param
     * @param Builder $query
     * @return Builder
     */
    private function setQuery(Builder $query, $param)
    {
        $query->from('tbl_order as order')
            ->leftJoin('tbl_member as member', 'order.member_id', '=', 'member.id')
            ->leftJoin('tbl_supplier as supplier', 'order.supplier_member_id', '=', 'supplier.member_id')
            ->where('order.site_id', $this->siteId);

        if ($param['show_distribution_level']) {
            $query->leftJoin('tbl_distributor as distributor', 'distributor.member_id', '=', 'order.member_id');
            $query->leftJoin('tbl_distribution_level as distribution_level', 'distributor.level', '=', 'distribution_level.id');
        }
        if ($param['finance_status']) {
            //  $query->leftJoin('tbl_finance as finance','finance.order_id','=','order.id');
            // $query->where('finance.type','=',CodeConstants::FinanceType_Commission);
            $query->whereIn('finance.status', $param['finance_status']);
            // $query->addSelect('finance.status as finance_status');
        }
        // 订单号
        if (trim($param['id'])) {
            $query->where('order.id', 'like', '%' . trim($param['id']) . '%');
        }
        // 自营还是供应商
        if(array_key_exists('is_supplier',$param) && intval($param['is_supplier']) > -1){
            $isSupplier = intval($param['is_supplier']);
            if($isSupplier === 0) $query->where('order.supplier_member_id', 0);
            else $query->where('order.supplier_member_id', '>',0);
        }
        // 指定供应商
        if(array_key_exists('supplier_member_id',$param)){
            $supplierMemberId = intval($param['supplier_member_id']);
            $query->where('order.supplier_member_id', $supplierMemberId);
        }
        // 订单号列表
        if ($param['ids']) {
            $orderIds = [];
            if (is_array($param['ids'])) {
                $orderIds = $param['ids'];
            } else if (trim($param['ids'])) {
                $orderIds = explode(',', trim($param['ids']));
            }
            if (count($orderIds) > 0) {
                $query->whereIn('order.id', $orderIds);
            }
        }
        // 搜索
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            $asciiKeyword = preg_replace('/[^\w]/','',$keyword);
            $searchType = intval($param['search_type']);
            $query->where(function ($query) use ($searchType,$keyword,$asciiKeyword) {
                if($searchType === 0 && $asciiKeyword){
                    //搜索订单号
                    $query->orWhere('order.id', 'like', '%' . trim($asciiKeyword) . '%');
                } elseif ($searchType === 1) {
                    //搜索买家
                    $query->orWhere('member.nickname', 'like', '%' . trim($keyword) . '%');
                    $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                    if($asciiKeyword) $query->orWhere('member.mobile', 'like', '%' . trim($asciiKeyword) . '%');
                } elseif ($searchType === 2) {
                    //搜索供应商
                    $query->orWhere('supplier.name', 'like', '%' . trim($keyword) . '%');
                }
            });
        }
        // 状态
        if ($param['status'] != '') {
            $statusList = myToArray($param['status']);
            if (count($statusList) > 0) {
                $query->whereIn('order.status', $statusList);
            }
        }
        if (isset($param['type_status'])) {
            $typeStatuList = myToArray($param['type_status']);
            $query->whereIn('order.type_status',$typeStatuList);
        }
        if (isset($param['activity_id'])) {
            $query->where('order.activity_id', $param['activity_id']);
        }
        if (is_numeric($param['has_after_sale'])) {
            $query->where('order.has_after_sale', intval($param['has_after_sale']));
        }
        // 终端
        if ($param['terminal_type'] != '' && intval($param['terminal_type']) >= 0) {
            $query->where('order.terminal_type', intval($param['terminal_type']));
        }
        // 类型
        if (isset($param['type']) && $param['type'] !== '' && intval($param['type']) >= 0) {
            $query->where('order.type', intval($param['type']));
        }
        // 下单时间开始
        if (trim($param['created_start'])) {
            $query->where('order.created_at', '>=', trim($param['created_start']));
        }
        // 下单时间结束
        if (trim($param['created_end'])) {
            $query->where('order.created_at', '<=', trim($param['created_end']));
        }
        // 会员昵称 模糊搜索
        if (trim($param['nickname'])) {
            $query->where('member.nickname', 'like', '%' . trim($param['nickname']) . '%');
        }
        // 会员手机 模糊搜索
        if (trim($param['mobile'])) {
            $query->where('member.mobile', 'like', '%' . trim($param['mobile']) . '%');
        }
        //只寻找有分佣的订单
        if ($param['commission']) {
            if (is_numeric($param['commission'])) {
                $query->where('has_commission', $param['commission']);
            } else {
                $query->whereIn('has_commission', $param['commission']);
            }
        }
        // 只寻找付过款,付过款的，会有付款时间
        if (isset($param['do_pay'])) {
            $query->whereNotNull('order.pay_at');
        }
        // 查找订单同步状态
        if (isset($param['express_sync_status'])) {
            switch (intval($param['express_sync_status'])) {
                case 1:
                    // 同步中的
                    $query->where('express_sync_status', ExpressConstants::OrderSynStatus_InSync);
                    break;
                case 2:
                    // 已同步的 要包括更新和关闭状态的
                    $query->whereIn('express_sync_status', ExpressConstants::getSyncSuccessedStatus());
                    break;
                case 3:
                    // 未同步的 还要包括同步失败的
                    $query->where('express_sync_status', ExpressConstants::OrderSynStatus_NoSync);
                    break;
                case 4:
                    // 同步失败的
                    $query->where('express_sync_status', ExpressConstants::OrderSynStatus_SyncFail);
                    break;
                case 5:
                    // 可以同步的
                    $query->whereIn('express_sync_status', [
                        ExpressConstants::OrderSynStatus_NoSync,
                        ExpressConstants::OrderSynStatus_SyncFail,
                    ]);
                    break;
            }
        }
        // 查找是否是虚拟订单
        if (isset($param['virtual_flag'])) {
            $query->where('order.virtual_flag', intval($param['virtual_flag']));
        }

        // 发货状态
        if (isset($param['delivery_status'])) {
            $query->where('order.delivery_status', intval($param['delivery_status']));
        }

        return $query;
    }

    /**
     * 数据统计
     * @param $param
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function count($param)
    {
        $query = OrderModel::query();
        $this->setQuery($query, $param);
        $query->addSelect(DB::raw("count(1) as num"));
        $query->addSelect(DB::raw("sum(order.money) as money"));
        $query->addSelect(DB::raw("count(distinct(order.member_id)) as member_num"));
        return $query->first();
    }

    /**
     * 搜索列表
     * @param $param
     * @return array
     */
    public function getList($param)
    {
        $page = $param['page'] ? intval($param['page']) : 1; // 查询页码
        $pageSize = $param['page_size'] ? intval($param['page_size']) : 20; // 每页数据量
        $isShowAll = $param['show_all'] || ($param['ids'] && strlen($param['ids'] > 0)) ? true : false; // 是否显示全部数据（不分页）
        $isOutputText = $param['output_text'] ? true : false; // 是否输出文字描述
        $showAfterSaleDetail = $param['after_sale_detail'] ? true : false; // 是否显示售后详细状态
        $showDress = $param['show_dress'] ? true : false;

        $query = OrderModel::query();
        $this->setQuery($query, $param);

        // 总数据量
        $total = $query->count();
        // 如果拿出全部，就看作拿第一页且数量为总数量
        if ($total > 0 && $isShowAll) {
            $pageSize = $total;
            $page = 1;
        }
        $last_page = ceil($total / $pageSize); // 总页数

        // 分页
        $query->forPage($page, $pageSize);

        // 产品列表子查询
        /*$queryForItem = clone $query;
        $queryForItem->select('order.id as order_id');
        $queryForItem = DB::table(DB::raw("({$queryForItem->toSql()}) as order_tmp"))
            ->mergeBindings($queryForItem->getQuery())
            ->select('order_id');*/
        if ($param['get_ids']) {
            return $query->pluck('order.id')->toArray();
        }
        // 订单列表
        $query->addSelect('order.*', 'member.headurl as member_headurl','member.mobile as member_mobile', 'member.nickname as member_nickname', 'member.name as member_name', 'supplier.name as supplier_name');
        if ($param['show_distribution_level']) {
            $query->addSelect('distribution_level.name as distribution_level_name', 'distributor.status as distributor_status');
        }

        DB::enableQueryLog();
        $list = $query->orderBy('created_at', 'desc')->get();
        $orderIds = [];
        $memberIds = [];
        $districtIds = [];
        foreach ($list as $item) {
            $orderIds[] = $item->id;
            $memberIds[] = $item->member_id;
            $snapshot = json_decode($item->snapshot, true);
            $districtIds = array_merge($districtIds, [$snapshot['address']['country'], $snapshot['address']['prov'], $snapshot['address']['city'], $snapshot['address']['area']]);
            $commission = json_decode($item->commission, true);
            if (is_array($commission)) {
                foreach ($commission as $val) {
                    $memberIds[] = $val['member_id'];
                }
            }
        }
        if ($total > 0) {
            // 省市区的名称
            if ($showDress) {
                $listDistricts = DB::table('tbl_district')
                    ->whereIn('id', $districtIds)
                    ->select('name', 'id')->get();
                $districtNames = [];
                foreach ($listDistricts as $d) {
                    $districtNames[$d->id] = $d->name;
                }
            }
            // 相关会员的基本信息
            $listMembers = DB::table('tbl_member')
                ->where('site_id', $this->siteId)
                ->whereIn('id', $memberIds)
                ->select(['id', 'nickname', 'mobile','name'])->get();
            $memberInfos = [];
            foreach ($listMembers as $d) {
                $memberInfos[$d->id] = (array)$d;
            }

            // 订单产品列表
            $itemQuery = OrderItemModel::query()
                ->from('tbl_order_item as item')
                ->leftJoin('tbl_logistics as logistics', 'item.logistics_id', '=', 'logistics.id')
                ->leftJoin('tbl_product_skus as skus', 'item.sku_id', '=', 'skus.id')
                ->leftJoin('tbl_order_item_discount as discount', 'item.id', 'discount.item_id')
                ->leftJoin('tbl_supplier as supplier', 'item.supplier_member_id', 'supplier.member_id')
                ->whereIn('item.order_id', $orderIds)
                ->orderBy('item.order_id', 'desc')
                ->addSelect(['item.*', 'logistics.logistics_company', 'logistics.logistics_name', 'logistics.logistics_no', 'skus.serial_number', 'discount.discount_price','supplier.name as supplier_name']);
            $itemList = $itemQuery->get();

            // 售后详细状态
            if ($showAfterSaleDetail) {
                $itemIds = [];
                foreach ($itemList as $d) {
                    $itemIds[] = $d->id;
                }
                if (count($itemIds)) {
                    $listAfterSales = DB::table('tbl_after_sale_item')
                        ->leftJoin('tbl_after_sale', 'tbl_after_sale.id', '=', 'tbl_after_sale_item.after_sale_id')
                        ->whereIn('order_item_id', $itemIds)
                        ->whereNotIn('tbl_after_sale.status', [2, -1])
                        ->groupBy('order_item_id')
                        ->orderBy('tbl_after_sale.id', 'desc')
                        ->select(['order_item_id', 'tbl_after_sale.status', 'tbl_after_sale.id', 'tbl_after_sale.type'])->get();

                    foreach ($itemList as &$d) {
                        $foundObj = $listAfterSales->where('order_item_id', $d->id)->first();
                        $d->after_sale_status = $foundObj->status;
                        $d->after_sale_id = $foundObj->id;
                        $d->after_sale_type = $foundObj->type;
                    }
                    unset($d);
                }
            }

            // 把产品列表合并到订单列表 并处理数据
            foreach ($list as &$item) {
                $orderId = $item->id;
                $marchStart = false;
                $item->type_text = "普通订单";
                if($item->type == Constants::OrderType_GroupBuying) $item->type_text = "拼团订单";
                $item->item_list = new Collection();
                // 文字描述
                if ($isOutputText) {
                    $item->terminal_type_text = CodeConstants::getTerminalTypeText($item->terminal_type);
                    $item->status_text = Constants::getOrderStatusText($item->status);
                }
                //地址转换
                if ($showDress) {
                    $snapshot = json_decode($item->snapshot, true);
                    $item->country = $districtNames[$snapshot['address']['country']] ? $districtNames[$snapshot['address']['country']] : '';
                    $item->prov = $districtNames[$snapshot['address']['prov']] ? $districtNames[$snapshot['address']['prov']] : '';
                    $item->city = $districtNames[$snapshot['address']['city']] ? $districtNames[$snapshot['address']['city']] : '';
                    $item->area = $districtNames[$snapshot['address']['area']] ? $districtNames[$snapshot['address']['area']] : '';
                }
                $commission = json_decode($item->commission, true);
                $total_commission = 0;
                if ($commission) {
                    foreach ($commission as $key => &$val) {
                        $val['nickname'] = $memberInfos[$val['member_id']]['nickname'];
                        $val['mobile'] = $memberInfos[$val['member_id']]['mobile'];
                        $val['name'] = $memberInfos[$val['member_id']]['name'];
                        $val['id'] = $val['member_id'];
                        $total_commission += $val['money'];
                    }
                    unset($val);
                    $item->commission = new Collection($commission);
                }
                $item->total_commission = $total_commission ? moneyCent2Yuan($total_commission) : 0;
                for ($i = 0; $i < $itemList->count(); $i++) {
                    $subItem = $itemList[$i];
                    // 如果已经开始了匹配，并且order_id不一致，跳出循环
                    if ($marchStart && $subItem->order_id != $orderId) {
                        break;
                    }
                    // 如果属于当前订单
                    if ($subItem->order_id == $orderId) {
                        if (!$marchStart) {
                            // 标记为开始匹配
                            $marchStart = true;
                        }
                        if ($itemList[$i]['commission']) {
                            $commission_item = json_decode($itemList[$i]['commission'], true);
                            foreach ($commission_item as $k => &$v) {
                                $v['level'] = is_array($v['chain']) ? count($v['chain']) : 0;
                            }
                            unset($v);
                            $itemList[$i]['commission'] = new Collection($commission_item);
                        }
                        // 把匹配的从数组中剔除，以便后续运算更快
                        $productData = $itemList->splice($i, 1)[0];
                        $i = $i - 1;
                        // 计算
                        $this->calculation($productData);
                        $item->item_list->push($productData);
                    }
                }
            }
        }
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 获取单个数据
     * @param $orderId
     * @return bool|null
     */
    public function getInfo($orderId)
    {
        if ($orderId) {
            $this->setOrder($orderId);
        }
        if ($this->checkExist()) {
            $model = $this->getModel();
            $model->item_list = $this->orderItemList;
            foreach ($model->item_list as $itemDetail) {
                // 显示具体的售后情况
                $itemDetail->after_sale = false;
                $itemDetail->after_sale_status = null;
                $itemId = $itemDetail->id;
                if (intval($itemDetail->after_sale_num) > 0 || intval($itemDetail->after_sale_over_num) > 0) {
                    $itemDetail->after_sale = true;
                    $afterSale = AfterSaleItemModel::query()->from('tbl_after_sale_item')
                        ->leftJoin('tbl_after_sale', 'tbl_after_sale_item.after_sale_id', '=', 'tbl_after_sale.id')
                        ->where('tbl_after_sale_item.order_item_id', $itemId)
                        ->where('tbl_after_sale_item.site_id', $this->getSiteId())
                        ->orderBy('tbl_after_sale_item.id', 'desc')
                        ->select('tbl_after_sale.status', 'tbl_after_sale.id', 'tbl_after_sale.type')
                        ->first();

                    if ($afterSale) {
                        $itemDetail->after_sale_status = intval($afterSale->status);
                        $itemDetail->after_sale_id = $afterSale->id;
                        $itemDetail->after_sale_type = $afterSale->type;
                    }
                }
                $discount = OrderItemDiscountModel::query()->where('item_id', $itemDetail->id)->first();
                $itemDetail->discount_price = $discount->discount_price;
                // 常规数据处理
                $this->calculation($itemDetail);
            }

            // 会员信息
            $member = new Member($model->member_id, $model->site_id);
            if ($member->checkExist()) {
                $model->member_mobile = $member->getModel()->mobile;
                $model->member_name = $member->getModel()->name;
                $model->member_nickname = $member->getModel()->nickname;
                $model->member_headurl = $member->getModel()->headurl;
            }

            //供应商名称
            $model->supplier_name = '';
            if($model->supplier_member_id){
                $model->supplier_name = $model->item_list[0]->supplier_name;
            }

            //供应商结算信息
            $supplierSettle = SupplierSettleModel::query()->where('order_id',$model->id)->first();
            $model->supplier_settle = $supplierSettle;

            //最后更改订单金额的时间
            $moneyChangeLog = OpLogModel::query()->where(['target' => $model->id,'type' => Constants::OpLogType_OrderMoneyChange])->orderByDesc('id')->first();
            $model->money_changed_at = $moneyChangeLog ? $moneyChangeLog->created_at . '' : '';

            //最后更改订单金额的时间
            $freightChangeLog = OpLogModel::query()->where(['target' => $model->id,'type' => Constants::OpLogType_OrderFreightChange])->orderByDesc('id')->first();
            $model->freight_changed_at = $freightChangeLog ? $freightChangeLog->created_at . '' : '';

            $model->address = self::getAddressText($model);

            return $model;
        } else {
            return false;
        }
    }

    /**
     * 返回商品明细
     * @return array|Collection
     */
    public function getItems()
    {
        return $this->orderItemList;
    }

    /**
     * 发货
     * @param $delivery 物流信息
     * @param array $itemIds 订单商品ids
     * @param bool $isSync  是否是自动同步时触发的
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function deliver($delivery, $itemIds = [], $isSync = false)
    {
        // 待发货状态才能发货
        if (!$this->checkExist() || $this->getModel()->status != Constants::OrderStatus_OrderPay) {
            return false;
        }

        if ($itemIds && is_numeric($itemIds)) {
            $itemIds = [$itemIds];
        }
        $remain = true; // 是否把剩余的可发货的都发货
        // 未发货的，且数量 >（成功售后 + 售后中）
        $notDelivery = OrderItemModel::query()
            ->where('site_id', $this->getSiteId())
            ->where('order_id', $this->getOrderId())
            ->where('delivery_status', Constants::OrderProductDeliveryStatus_No)
            ->whereRaw('num > after_sale_num + after_sale_over_num')
            ->pluck('id')->toArray();
        if (count($notDelivery) == 0) {
            return false;
        }
        // 处理数据，计算出最终要发货的可发货的订单明细id
        if (count($itemIds) > 0) {
            // 检查数据，抽取相同部分
            $itemIds = array_intersect($notDelivery, $itemIds);
            if (empty($itemIds)) {
                return false;
            } else if (count($itemIds) != count($notDelivery)) {
                // 如果分批发和剩余的不一致，认为是部分发货
                $remain = false;
            }
        } else {
            $itemIds = $notDelivery;
        }
        // 生成物流数据
        $delivery['site_id'] = $this->siteId;
        $delivery['member_id'] = $this->getModel()->member_id;
        $delivery['order_id'] = $this->getModel()->id;
        $logistics = new Logistics($this->siteId);
        $logisticsID = $logistics->add($delivery);
        if (!$logisticsID) return false;
        // 更新订单产品数据的发货信息
        $updateQuery = OrderItemModel::query()
            ->where('site_id', $this->siteId)
            ->where('order_id', $this->getModel()->id)
            ->where('delivery_status', Constants::OrderProductDeliveryStatus_No)
            ->whereIn('id', $itemIds);
        $resultNum = $updateQuery->update([
            'delivery_status' => Constants::OrderProductDeliveryStatus_Yes,
            'logistics_id' => $logisticsID
        ]);
        if ($resultNum > 0) {
            $orderHelper = new OrderHelper($this->getSiteId());
            $orderHelper->updateStatusForSendReceive($this->getOrderId());
            // 重新加载数据
            $this->findById($this->getOrderId());
            if ($remain) {
                $logisticsCount = LogisticsModel::query()
                    ->where('site_id', $this->getSiteId())
                    ->where('order_id', $this->getOrderId())
                    ->where('member_id', $this->getMemberId())
                    ->count();
                // 统一发货
                if ($logisticsCount == 1) {
                    $this->getModel()->logistics_id = $logisticsID;
                    $this->getModel()->save();
                }
            }
            // 发货通知
            $logistics = Logistics::find($logisticsID);
            if ($logistics->checkExist()) {
                MessageNoticeHelper::sendMessageOrderSend($logistics->getModel());
            }
            // 已经同步成功的 在后台发货时 要把快递100的相关订单关闭
            if (!$isSync && in_array($this->getModel()->express_sync_status, ExpressConstants::getSyncSuccessedStatus())) {
                ExpressHelper::createExpressJob($this->getModel()->id, $this->getModel()->site_id, ExpressConstants::OrderSynType_Cancel);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 修改信息(一般用于后台要修改订单信息)
     * @param array $info
     * @param bool $reload
     */
    public function edit(array $info, $reload = false)
    {
        if ($this->checkExist()) {
            foreach ($info as $key => $val) {
                $this->getModel()->$key = $val;
            }
            $this->getModel()->save();
            if ($reload) {
                $this->findById($this->getModel()->id);
            }
        }
    }

    /**
     * 根据订单id查找
     * @param $orderId
     */
    private function findById($orderId)
    {
        $query = OrderModel::query()->where('id', $orderId);
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
        $this->orderModal = $model;
        if ($this->checkExist()) {
            // 获取明细，按发货码
            $this->orderItemList = OrderItemModel::query()
                ->from('tbl_order_item as item')
                ->leftJoin('tbl_logistics as logistics', 'item.logistics_id', '=', 'logistics.id')
                ->leftJoin('tbl_supplier as supplier', 'item.supplier_member_id', 'supplier.member_id')
                ->where('item.site_id', $model->site_id)
                ->where('item.order_id', $model->id)
                ->select('item.*', 'logistics.logistics_name', 'logistics.logistics_no', 'logistics.logistics_company','supplier.name as supplier_name')
                ->get();
        }
    }

    /**
     * 简单计算一些数值
     * @param $productData
     */
    private function calculation($productData)
    {
        $productData->discount = $productData->total_discount; // 总优惠金额
        $productData->sub_total = $productData->total_money; // 总实付金额
        $productData->profit = $productData->total_money - ($productData->cost * $productData->num); // 利润
    }

    /**
     * 获取订单关闭原因文案
     * @param $reason
     * @return string
     */
    public static function getOrderCancelReasonText($reason)
    {
        switch ($reason) {
            case Constants::OrderCancelReason_TimeOut:
                return '超时关闭';
            case Constants::OrderCancelReason_NotLike:
                return '不喜欢/不想要';
            case Constants::OrderCancelReason_BuyError:
                return '拍错了';
            case Constants::OrderCancelReason_OtherBuyType:
                return '有其他优惠购买方式';
            default:
                return '未知原因';
        }
    }

    public static function getAddressText(OrderModel $model)
    {
        if ($model instanceof OrderModel && intval($model->virtual_flag) !== 1) {
            $snapshot = json_decode($model->snapshot, true);
            if ($snapshot['address']) {
                $country = $snapshot['address']['country'] == 'CN' ? '中国' : '国外';
                $districtIds = [$snapshot['address']['prov'], $snapshot['address']['city'], $snapshot['address']['area']];
                $district = DB::table('tbl_district')
                    ->whereIn('id', $districtIds)
                    ->pluck('name', 'id');
                $address = [
                    'country_text' => $country,
                    'prov_text' => $district[$snapshot['address']['prov']],
                    'city_text' => $district[$snapshot['address']['city']],
                    'area_text' => $district[$snapshot['address']['area']],
                    'address' => $snapshot['address']['address']];
            } else {
                $address = [
                    'country_text' => '中国',
                    'prov_text' => '',
                    'city_text' => '',
                    'area_text' => '',
                    'address' => ''
                ];
            }
            return $address;
        } else {
            return false;
        }
    }


    /**
     * 代客人确认收货
     * @param $orderId
     * @return array
     */
    public function orderConfirmReceipt($orderId)
    {
        $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId, false);
        $order = $shopOrder->getOrderModel();
        if (in_array($order->status,[Constants::OrderStatus_OrderSuccess,Constants::OrderStatus_OrderReceive])) {
            return makeApiResponse(501,'客户已确认收货');
        }
        $result = $shopOrder->receipt();
        if ($result) {
            return makeServiceResultSuccess(trans('shop-front.common.action_ok'));
        } else {
            return makeServiceResultFail(trans('shop-front.shop.order_status_error'));
        }
    }

    /**
     * 手动修改订单金额，实际上是通过增加手动优惠的方式来实现，这样可以跟踪到一些原始数据
     * @param $orderMoney 希望修改的订单的最终金额
     */
    public function editProductMoney($orderMoney){
        if($this->orderModal->status != Constants::OrderStatus_NoPay){
            throw new \Exception('只有未交费的订单才能修改订单金额');
        }
        DB::beginTransaction();
        try {
            if($orderMoney < $this->orderModal->freight){
                throw new \Exception('设置的订单金额过小，订单金额不能少于运费');
            }
            $oldData = $this->orderModal->manual_discount;
            //订单最终金额是由现价+手动优惠组成，这里实际上是通过调整手动优惠来达到目的
            $oriMoney = $this->orderModal->money + $this->orderModal->manual_discount - $this->orderModal->freight + $this->orderModal->ori_freight;
            $discount = $oriMoney - $orderMoney - $this->orderModal->freight_manual_discount; //因为运费的优惠是单独计算的，这里要减掉已经优惠的运费的额度
            $productMoney = $oriMoney - $this->orderModal->ori_freight;
            if($orderMoney >= $productMoney){ //当最终订单金额大于商品金额时，说明商品没有优惠，这时应该调整运费优惠
                $discount = 0;
                //如果算discount的过程中有减去当前的运费折扣，当discount小于0时，说明原来有设置了运费折扣，这补回去
                $freight = $orderMoney - $productMoney;
                $this->editFreightMoney($freight);
            }
            $this->orderModal->manual_discount = $discount;
            $this->orderModal->money = $orderMoney;
            $this->orderModal->save();
            //对子商品记录进行手工优惠分配
            $items = $this->orderModal->items;
            $allocMoney = 0;
            foreach ($items as $item) {
                $item->manual_discount = floor($discount * ($item->total_money / $oriMoney));
                $allocMoney += $item->manual_discount;
            }
            //通过比例进行分配，可能会存在分配不完的问题，这时默认将剩余的加到第一个商品那里
            if ($allocMoney < $discount) $items[0]->manual_discount += $discount - $allocMoney;
            foreach ($items as $item) {
                $item->save();
            }
            OpLog::Log(Constants::OpLogType_OrderMoneyChange,$this->orderModal->id,$oldData,$this->orderModal->manual_discount);
            DB::commit();
        }catch(\Exception $ex){
            DB::rollBack();
            throw $ex;
        }
    }

    /**
     * 手动修改订单金额，实际上是通过增加手动优惠的方式来实现，这样可以跟踪到一些原始数据
     * @param $orderMoney 希望修改的订单的最终金额
     */
    public function editFreightMoney($freight){
        if($this->orderModal->status != Constants::OrderStatus_NoPay){
            throw new \Exception('只有未交费的订单才能修改订单金额');
        }
        $supplierFreight = json_decode($this->orderModal->supplier_freight,true);
        if(!is_array($supplierFreight)) $supplierFreight = ['supplier_0' => $this->orderModal->freight]; //兼容旧数据
        if(count($supplierFreight) > 1){
            throw new \Exception('有多个供应商的订单不支持改运费');
        }
        $oldData = $this->orderModal->freight_manual_discount;
        if(!$this->orderModal->ori_freight) $this->orderModal->ori_freight = $this->orderModal->freight;
        $oldFreight = $this->orderModal->freight;
        $this->orderModal->freight_manual_discount = $this->orderModal->ori_freight - $freight;
        $this->orderModal->freight = $freight;
        foreach ($supplierFreight as $key => $val) {
            $supplierFreight[$key] = $freight;
        }
        $this->orderModal->supplier_freight = json_encode($supplierFreight);
        $this->orderModal->money = $this->orderModal->money - $oldFreight + $this->orderModal->ori_freight - $this->orderModal->freight_manual_discount;
        $this->orderModal->save();
        OpLog::Log(Constants::OpLogType_OrderFreightChange,$this->orderModal->id,$oldData,$this->orderModal->freight_manual_discount);
    }

    /**
     * 修改订单收货地址
     * @param $prov 省ID
     * @param $city 市ID
     * @param $area 区ID
     * @param $address 详情地址
     * @param $name 收货人姓名
     * @param $phone 收货人电话
     * @throws \Exception
     */
    public function editAddress($prov,$city,$area,$address,$name,$phone){
        if($this->orderModal->delivery_status != Constants::OrderDeliveryStatus_No){
            throw new \Exception('只有未发货的订单才能修改收货地址');
        }
        $snapshot = json_decode($this->orderModal->snapshot,true);
        $oldData = $snapshot['address'];
        if($prov) $snapshot['address']['prov'] = $prov;
        if($city) $snapshot['address']['city'] = $city;
        if($area) $snapshot['address']['area'] = $area;
        if($address) $snapshot['address']['address'] = $address;
        if($name) $snapshot['address']['name'] = $name;
        if($phone) $snapshot['address']['phone'] = $phone;
        $this->orderModal->snapshot = json_encode($snapshot);
        if($address) $this->orderModal->receiver_address = $address;
        if($name) $this->orderModal->receiver_name = $name;
        if($phone) $this->orderModal->receiver_tel = $phone;
        $this->orderModal->save();
        OpLog::Log(Constants::OpLogType_OrderAddressChange,$this->orderModal->id,json_encode($oldData),json_encode($snapshot['address']));
        $this->editAddressAfter();
    }

    /**
     * 修改地址后的处理
     */
    public function editAddressAfter()
    {
        $order = $this->orderModal;

        //区域代理重新分佣
        if(intval($order->area_agent_commission_status) === 1){
            //先删除旧的
            AreaAgentCommission::deleteCommissionByOrder($order->id);
            //生成新的
            $shopOrder = ShopOrderFactory::createOrderByOrderId($order->id);
            $shopOrder->doAreaAgentCommission();
        }

        // 更新地址到快递100
        // 未发货 并且已同步的订单
        if (
            $order->delivery_status == Constants::OrderDeliveryStatus_No &&
            $order->status == Constants::OrderStatus_OrderPay &&
            in_array(
                $order->express_sync_status,
                [ExpressConstants::OrderSynStatus_SyncSuccessed,
                ExpressConstants::OrderSynStatus_UpdateFail])
        ) {
            ExpressHelper::createExpressJob($order->id, $order->site_id, ExpressConstants::OrderSynType_Update);
        }
    }

    /**
     * 同步待发货订单
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function syncOrder($params)
    {
        // 先检测配置状态
        $setting = new ExpressSetting();
        $setting->checkStatus();
        if ($params['ids']) {
            if (!is_array($params['ids'])) {
                $params['ids'] = explode(',', trim($params['ids']));
            }
        }
        $params['get_ids'] = 1;
        $params['status'] = Constants::OrderStatus_OrderPay;
        // 如果没有传入同步状态 则默认查找可以同步的
        if (!isset($params['express_sync_status'])) {
            $params['express_sync_status'] = 5; // 只查找可以同步的
        }
        $params['is_supplier'] = 0; // 自营订单
        $params['virtual_flag'] = 0; // 没有虚拟商品的订单
        $params['delivery_status'] = Constants::OrderDeliveryStatus_No; // 没有发货的订单
        $orderIds = $this->getList($params);
        if ($orderIds) {
            // 更新状态为同步中
            OrderModel::query()
                ->where('site_id', $this->siteId)
                ->whereIn('id', $orderIds)
                ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_InSync]);
            dispatch(new OrderExpressSyncJob($orderIds, $this->siteId, ExpressConstants::OrderSynType_Send));
            return true;
        } else {
            return false;
        }
    }
}