<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Admin;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Model\BaseModel;

/**
 * 列出分销订单
 * @package App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Admin
 */
class DistributionOrderController extends BaseController
{
    public function getList(Request $request)
    {
        try {
            $page = intval($request->page) ? intval($request->page) : 1;
            $pagesize = intval($request->page_size) ? intval($request->page_size) : 20;
            // 获取数据
            $param['member_id'] = intval($request->member_id);
            // 查询条件
            $select = "select tbl_order.*,tbl_member.nickname as member_nickname,tbl_member.mobile as member_mobile";
            $from = " from tbl_order left join tbl_member on tbl_member.id = tbl_order.member_id ";
            $where = " where tbl_order.id in (select order_id from tbl_order_members_history where tbl_order_members_history.member_id = :member_id)";
            $where .= " and tbl_order.status in (1,2,3,4,5,7) ";

            // 关键词搜索
            if (trim($request->get('keyword'))) {
                $keyword = "%" . trim($request->get('keyword')) . "%";
                $where .= " and (tbl_order.id like :keyword1 or tbl_member.nickname like :keyword2 or tbl_member.mobile like :keyword3)";
                $param['keyword1'] = $keyword;
                $param['keyword2'] = $keyword;
                $param['keyword3'] = $keyword;
            }
            // 开始时间
            if ($request->get("created_start")) {
                $where .= " and tbl_order.created_at >= :created_start";
                $param['created_start'] = $request->get("created_start");
            }
            // 结束时间
            if ($request->get("created_end")) {
                $where .= " and tbl_order.created_at <= :created_end";
                $param['created_end'] = $request->get("created_end");
            }

            // 读取订单总数
            $totalInfo = BaseModel::runSql("select count(tbl_order.id) as total_record " . $from . $where, $param);
            $last_page = ceil($totalInfo[0]->total_record / $pagesize);

            // 金额统计
            $totalMoneyResult = BaseModel::runSql("select sum(tbl_order.money - tbl_order.freight) as total_money" . $from . $where, $param);

            // 列表
            $param['pagesize'] = $pagesize;
            $param['offset'] = ($page - 1) * $pagesize;
            $order = " order by tbl_order.created_at desc limit :offset, :pagesize";
            $orderList = BaseModel::runSql($select . $from . $where . $order, $param);

            $orderIds = [];
            foreach ($orderList as &$item) {
                $item->money = moneyCent2Yuan($item->money - $item->freight);
                $orderIds[] = $item->id;
            }
            unset($item);
            // 列出订单商品
            if (count($orderIds)) {
                $sql = "select * from tbl_order_item where order_id in (" . implode(',', $orderIds) . ")";
                $goodsList = BaseModel::runSql($sql);
                foreach ($goodsList as &$good) {
                    $good->sku_names = json_decode($good->sku_names, true);
                    $good->price = moneyCent2Yuan($good->price);
                }
                unset($good);
                $itemList = new Collection($goodsList);
                foreach ($orderList as &$item) {
                    $item->item_list = $itemList->where('order_id', $item->id)->values();
                }
                unset($item);
            }
            $data = [
                'list' => $orderList,
                'total' => $totalInfo[0]->total_record,
                'last_page' => $last_page,
                'current' => $page,
                'page_size' => $pagesize,
                'total_money' => moneyCent2Yuan($totalMoneyResult[0]->total_money),
            ];
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}