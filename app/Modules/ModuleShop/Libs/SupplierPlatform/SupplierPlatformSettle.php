<?php

namespace App\Modules\ModuleShop\Libs\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierSettleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Model\FinanceModel;

/**
 * 后台用的供应商结算业务类
 * Class SupplierSettleAdmin
 * @package App\Modules\ModuleShop\Libs\Supplier
 */
class SupplierPlatformSettle
{
    private $siteId = 0; // 站点ID
    private $supplierId = 0; // 供应商ID

    /**
     * 初始化
     * Order constructor.
     * @param int $siteId 站点ID
     * @param int $supplierId 供应商会员ID
     */
    public function __construct($siteId, $supplierId)
    {
        $this->siteId = $siteId;
        $this->supplierId = $supplierId;
        if ($this->siteId < 1 || $this->supplierId < 1) {
            throw new \Exception("数据错误，站点ID或供应商ID不对");
        }
    }

    /**
     * 获取供应商结算列表
     * @param $param
     * @return array
     */
    public function getList($param)
    {
        $page = $param['page'] ? intval($param['page']) : 1; // 查询页码
        $pageSize = $param['page_size'] ? intval($param['page_size']) : 20; // 每页数据量
        $isShowAll = $param['show_all'] || ($param['ids'] && strlen($param['ids'] > 0)) ? true : false; // 是否显示全部数据（不分页），主要用于导出

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

        // 订单列表
        $query->addSelect('order.id', 'order.status', 'order.member_id', 'order.freight', 'order.money', 'order.created_at', 'order.supplier_member_id','order.terminal_type');
        $query->addSelect('member.mobile as member_mobile', 'member.nickname as member_nickname', 'member.name as member_name');
        $query->addSelect('supplier.name as supplier_name', 'settle.status as settle_status', 'settle.money as settle_money');
        $query->addSelect('settle.freight as settle_freight', 'settle.after_sale_money as settle_after_sale_money', 'settle.after_sale_freight as settle_after_sale_freight');

        $list = $query->orderBy('created_at', 'desc')->get();
        $orderIds = [];
        $memberIds = [];
        foreach ($list as $item) {
            $orderIds[] = $item->id;
            $memberIds[] = $item->member_id;
        }
        if ($total > 0) {
            // 相关会员的基本信息
            $listMembers = DB::table('tbl_member')
                ->where('site_id', $this->siteId)
                ->whereIn('id', $memberIds)
                ->select(['id', 'nickname', 'mobile', 'name'])->get();
            $memberInfos = [];
            foreach ($listMembers as $d) {
                $memberInfos[$d->id] = (array)$d;
            }

            // 订单产品列表
            $itemQuery = OrderItemModel::query()
                ->from('tbl_order_item as item')
                ->leftJoin('tbl_order_item_discount as discount', 'item.id', 'discount.item_id')
                ->whereIn('item.order_id', $orderIds)
                ->addSelect(['item.order_id', 'item.name', 'item.image', 'item.sku_names', 'item.price', 'item.num', 'item.point_money', 'item.coupon_money', 'item.after_sale_num', 'item.after_sale_over_num', 'item.supplier_price', 'discount.discount_price']);

            $itemList = $itemQuery->get();

            // 把产品列表合并到订单列表 并处理数据
            foreach ($list as &$item) {
                $orderId = $item->id;
                $item->real_settle = $item->settle_money + $item->settle_freight + $item->settle_after_sale_money + $item->settle_after_sale_freight;
                $item->all_after_sale = $item->settle_after_sale_money + $item->settle_after_sale_freight;
                $item->item_list = new Collection();
                // 文字描述
                $item->status_text = Constants::getOrderStatusText($item->status);
                $item->settle_status_text = Constants::getSupplierSettleStatusText($item->settle_status);
                $orderItemList = $itemList->where('order_id', '=', $orderId)->values();
                foreach ($orderItemList as $subItem) {
                    // 计算
                    $subItem->sku_names = json_decode($subItem->sku_names, true);
                    $this->calculation($subItem);
                    $item->item_list->push($subItem);
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
     * 简单计算一些数值
     * @param $productData
     */
    private function calculation($productData)
    {
        $productData->all_discount = $productData->point_money + $productData->coupon_money + $productData->discount_price; // 优惠
        $productData->real_price = $productData->price - $productData->all_discount; // 实付
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
            ->leftJoin('tbl_supplier_settle as settle', 'settle.order_id', '=', 'order.id')
            ->leftJoin('tbl_supplier as supplier', 'supplier.member_id', '=', 'order.supplier_member_id')
            ->where('order.site_id', $this->siteId)->where('order.supplier_member_id', $this->supplierId)->where('order.supplier_member_id', '>', 0)->where('settle.id', '>', 0);
        // 结算状态
        if ($param['settle_status'] > -1) {
            $query->where('settle.status', $param['settle_status']);
        }
        // 指定订单号列表（通常用于导出）
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
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            $searchType = intval($param['search_type']);
            $query->where(function ($query) use ($searchType, $keyword) {
                //搜供应商名称
                if ($searchType === 0) {
                    $query->where('supplier.name', 'like', '%' . trim($keyword) . '%');
                }
                //搜索会员名称 手机 等
                if ($searchType === 1) {
                    $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                    $query->orWhere('member.nickname', 'like', '%' . trim($keyword) . '%');
                    if (preg_match('/^\w+$/i', $keyword)) {
                        $query->orWhere('member.mobile', 'like', '%' . trim($keyword) . '%');
                    }
                }
                //搜订单号
                if ($searchType === 2) {
                    $keyword = preg_replace('/[^\d_]/i', '', $keyword);
                    $query->where('order.id', 'like', '%' . trim($keyword) . '%');
                }
            });
        }
        // 下单时间开始
        if (trim($param['created_start'])) {
            $query->where('order.created_at', '>=', trim($param['created_start']));
        }
        // 下单时间结束
        if (trim($param['created_end'])) {
            $query->where('order.created_at', '<=', trim($param['created_end']));
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
}