<?php

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuSettleModel;
use YZ\Core\Constants as CoreConstants;

/**
 * 云仓结算记录
 * Class CloudStockSkuSettle
 * @package App\Modules\ModuleShop\Libs\CloudStock
 */
class CloudStockSkuSettle
{
    /**
     * 获取云仓结算记录
     *
     * @param array $param
     * @return void
     */
    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        $query = DB::table('tbl_cloudstock_sku_settle as log')
            ->leftjoin('tbl_cloudstock_sku as cs', function ($join) {
                $join->on('cs.product_id', '=', 'log.product_id')->where('cs.sku_id', '=', 'log.sku_id');
            })
            ->leftjoin('tbl_product as p', 'p.id', '=', 'log.product_id')
            ->leftjoin('tbl_product_skus as sku', 'sku.id', '=', 'log.sku_id')
            ->where('log.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        // 排序
        $query->orderBy('log.id', 'desc');
        if ($param['noGroupBy']) {
            $query->groupBy('log.order_id');
        }
        $query->addSelect('log.*', 'cs.product_name', 'cs.sku_name', 'cs.product_image', 'p.name as proname', 'p.small_images as productimage', 'sku.sku_name as skuname');
        $query->forPage($page, $pageSize);
        $list = $query->get();

        // 合并信息
        foreach ($list as &$item) {
            if ($item->productimage) {
                $item->product_image = Site::getSiteComdataDir() . explode(',', $item->productimage)[0];
            }
            if ($item->proname) {
                $item->product_name = $item->proname;
            }
            $item->sku_name = $item->skuname ? json_decode($item->skuname, true) : [];
//            if ($item->skuname) {
//                $item->sku_name = implode(' ', json_decode($item->skuname, true));
//            }
            $item->order_type_text = $item->order_type == Constants::CloudStockOrderType_Retail ? '会员零售订单' : '下级进货单';
            $item->price = moneyCent2Yuan(floor($item->money / $item->num));
            $item->money = moneyCent2Yuan($item->money);
            unset($item->skuname, $item->proname, $item->productimage);
        }
        unset($item);

        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize
        ];
    }

    /**
     * 获取云仓结算记录(用于后台)
     * @param array $param
     * @return void
     */
    public static function getAdminList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        $isShowAll = $param['show_all'] || ($param['ids'] && strlen($param['ids'] > 0)) ? true : false; // 是否显示全部数据（不分页）
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        // 根据条件 查询该站点的所有进货单号
        $query = CloudStockPurchaseOrderModel::query()->from('tbl_cloudstock_purchase_order as purchase_order')
            ->where('purchase_order.site_id', Site::getCurrentSite()->getSiteId())
            ->where('purchase_order.status', Constants::CloudStockPurchaseOrderStatus_Finished)
            ->where(function ($query) {
                $query->orWhere(function ($query) {
                    $query->where('purchase_order.cloudstock_id', '<>', 0)
                        ->where('purchase_order.payee', '=', 0);
                });
                $query->orWhere(function ($query) {
                    $query->where('purchase_order.cloudstock_id', '<>', 0)
                        ->where('purchase_order.payee', '>', 0)
                        ->where('purchase_order.pay_type', '=', CoreConstants::PayType_Balance);
                });

            })
            ->leftJoin('tbl_member as member', 'member.id', '=', 'purchase_order.member_id')
            ->leftJoin('tbl_cloudstock as cs', 'cs.id', '=', 'purchase_order.cloudstock_id')
            ->leftJoin('tbl_member as csm', 'csm.id', '=', 'cs.member_id')
            ->orderBy('purchase_order.finished_at', 'desc')
            ->with(['items' => function ($subquery) use ($param) {
                $subquery->leftJoin('tbl_cloudstock_sku_settle as log', 'log.order_item_id', 'tbl_cloudstock_purchase_order_item.id')
                    ->leftjoin('tbl_cloudstock_sku as cs', function ($join) {
                        $join->on('cs.product_id', '=', 'log.product_id')->where('cs.sku_id', '=', 'log.sku_id');
                    })
                    ->leftjoin('tbl_product as p', 'p.id', '=', 'log.product_id')
                    ->leftjoin('tbl_product_skus as sku', 'sku.id', '=', 'log.sku_id')
                    ->leftjoin('tbl_member as member', 'log.member_id', '=', 'member.id')
                    ->addSelect('log.*', 'cs.product_name', 'cs.sku_name', 'cs.product_image', 'p.name as proname', 'p.small_images as productimage', 'sku.sku_name as skuname', 'member.nickname as cloudstock_nickname', 'tbl_cloudstock_purchase_order_item.image as image');
            }])
            ->select('purchase_order.id', 'member.nickname as buyer_nickname', 'member.name as buyer_name', 'purchase_order.finished_at', 'member.id as buyer_id', 'member.mobile as buyer_mobile', 'csm.nickname as cloudstock_nickname','csm.mobile as cloudstock_mobile');

        // 后台时间范围
        if ($param['finished_at_min']) {
            $query->where('purchase_order.finished_at', '>=', $param['finished_at_min']);
        }
        if ($param['finished_at_max']) {
            $query->where('purchase_order.finished_at', '<=', $param['finished_at_max']);
        }
        // 后台关键词
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            if ($param['keyword_type'] == 1 && preg_match('/^\w+$/i', $keyword)) {
                $query->where('purchase_order.id', 'like', '%' . $keyword . '%');
            } elseif ($param['keyword_type'] == 2) {
                $query->where(function ($query) use ($keyword){
                    $query->where('member.nickname', 'like', '%' . $keyword . '%');
                    $query->orWhere('member.mobile', 'like', '%' . $keyword . '%');
                });
            } elseif ($param['keyword_type'] == 3) {
                $query->where('csm.nickname', 'like', '%' . $keyword . '%');
                $query->orWhere('csm.mobile', 'like', '%' . $keyword . '%');
            }
        }

        $total = $query->count();
        if ($total > 0 && $isShowAll) {
            $pageSize = $total;
            $page = 1;
        }
        $last_page = ceil($total / $pageSize);
        $query->forPage($page, $pageSize);
        // 总数据量
        $purchaseOrder = $query->get();

        foreach ($purchaseOrder as &$order) {
            $order['order_id'] = $order->id;
            $totalMoney = 0;
            $orderItem = $order->items;
            foreach ($orderItem as &$item) {
                $status = $item->status;
                $totalMoney += $item->money;
                if ($item->productimage) {
                    $item->product_image = Site::getSiteComdataDir() . explode(',', $item->productimage)[0];
                }
                $item->image = Site::getSiteComdataDir() . $item->image;
                if ($item->proname) {
                    $item->product_name = $item->proname;
                }
                $item->sku_name = $item->skuname ? json_decode($item->skuname, true) : [];
                $item->order_type_text = $item->order_type == Constants::CloudStockOrderType_Retail ? '下级进货单' : '会员零售订单';
                $item->price = moneyCent2Yuan(floor($item->money / $item->num));
                $item->money = moneyCent2Yuan($item->money);
                unset($item->skuname, $item->proname, $item->productimage);
            }
            // 这个状态只能跟结算表的状态，不能跟订单表的状态，同一张订单，结算状态肯定是一样的。
            $order['status_text'] = $status == 0 ? '未结算' : ($status == 1 ? '已完成' : '无效');
            $order['status'] = $status;
            $order['total'] = moneyCent2Yuan($totalMoney);
        }

        // 返回值
        return [
            'list' => $purchaseOrder,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize
        ];
    }


    /**
     * 查询条件设置
     * @param Builder $query
     * @param array $param
     */
    private static function setQuery($query, array $param)
    {
        if ($param['ids']) {
            $query->whereIn('purchase_order.id', myToArray($param['ids']));
        }
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('log.member_id', intval($param['member_id']));
        }
        // 产品id
        if (is_numeric($param['product_id'])) {
            $query->where('log.product_id', intval($param['product_id']));
        }
        // sku id
        if (strlen($param['sku_id'])) {
            $query->where('log.sku_id', intval($param['sku_id']));
        }
        // 类型
        if (strlen($param['type'])) {
            $query->where('log.order_type', $param['type']);
        }
        // 状态
        if (is_numeric($param['status']) && intval($param['status']) != -1) {
            $query->where('cs.status', intval($param['status']));
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('log.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('log.created_at', '<=', $param['created_at_max']);
        }
        // 关键词
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            $query->where(function ($query2) use ($keyword) {
                $query2->orWhere('p.name', 'like', '%' . $keyword . '%');
                $query2->orWhere('cs.product_name', 'like', '%' . $keyword . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $query2->orWhere('log.order_id', 'like', '%' . $keyword . '%');
                }
            });
        }
        // 后台时间范围
        if ($param['finished_at_min']) {
            $query->where('purchase_order.finished_at', '>=', $param['finished_at_min']);
        }
        if ($param['finished_at_max']) {
            $query->where('purchase_order.finished_at', '<=', $param['finished_at_max']);
        }
        // 后台关键词
        if ($param['admin_keyword']) {
            $keyword = $param['admin_keyword'];
            $query->where(function ($query2) use ($keyword) {
                $query2->orWhere('member.nickname', 'like', '%' . $keyword . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $query2->orWhere('purchase_order.id', 'like', '%' . $keyword . '%');
                    $query2->orWhere('member.mobile', 'like', '%' . $keyword . '%');
                }
            });
        }
    }

    /**
     * 获取收入列表
     * @param $memberId
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getSettleList($memberId, $params = [], $page = 1, $pageSize = 20)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $query = CloudStockSkuSettleModel::query()
            ->from('tbl_cloudstock_sku_settle as settle')
            ->where('settle.site_id', $siteId)
            ->where('settle.member_id', $memberId)
            ->leftJoin('tbl_cloudstock_purchase_order as order', 'order.id', 'settle.order_id')
            ->leftJoin('tbl_member as m', 'm.id', 'order.member_id');
        if (isset($params['type'])) {
            $query->where('settle.order_type', intval($params['type']));
        }
        $list = $query->groupBy(['settle.order_id'])
            ->selectRaw('sum(settle.money) as money')
            ->addSelect(['settle.order_id', 'settle.order_type', 'm.nickname', 'm.headurl', 'settle.settled_at'])
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->orderByDesc('settle.settled_at')
            ->get();
        $hasNextPage = false;
        // 有下一页
        if ($list->count() > $pageSize) {
            $hasNextPage = true;
            $list->pop();
        }
        return [
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'list' => self::formatSettleList($list)
        ];
    }

    /**
     * 格式化收入列表数据
     * @param $list
     * @return array
     */
    public static function formatSettleList($list)
    {
        if (!$list) return [];
        foreach ($list as &$item) {
            $item->order_type_text = self::getSettleTypeText($item->order_type);
            $item->money = moneyCent2Yuan($item->money);
        }
        return $list;
    }

    /**
     * 货款提现列表
     * @param array $params
     * @return array
     */
    public static function getRewardWithdrawList($params)
    {
        $finance = new Finance();
        $params['single_member'] = true;
        $params['order_info'] = false;
        $params['types'] = CoreConstants::FinanceType_CloudStock;
        $params['out_types'] = [CoreConstants::FinanceOutType_Withdraw, CoreConstants::FinanceOutType_CloudStockGoodsToBalance];
        $params['time_order_by'] = true;
        $params['order_by'] = 'time';
        $data = $finance->getList($params);
        if ($data['list']) {
            foreach ($data['list'] as &$item) {
                for ($i = 1; $i <= 10; $i++) {
                    unset($item['from_member' . $i]);
                }
                $item->money = moneyCent2Yuan(abs($item->money));
                $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
                $item->money_real = moneyCent2Yuan(abs($item->money_real));
                if ($item->out_type == CoreConstants::FinanceInType_CloudStockGoodsToBalance) {
                    $item->out_account = '余额';
                } else {
                    $item->out_account = CoreConstants::getPayTypeWithdrawText($item->pay_type);
                }
            }
        }
        return $data;
    }

    /**
     * 获取收入订单类型文案
     * @param int $type
     * @return string
     */
    public static function getSettleTypeText(int $type)
    {
        switch ($type) {
            case Constants::CloudStockOrderType_Retail:
                return '零售订单';
            case Constants::CloudStockOrderType_Purchase:
                return '下级进货';
            default:
                return '未知类型';
        }
    }


}