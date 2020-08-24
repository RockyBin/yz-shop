<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Front;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Model\BaseModel;

/**
 * 列出分销订单
 * @package App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Front
 */
class DistributionOrderController extends BaseController
{
    /**
     * 获取此会员的下级会员的订单汇总数据
     * @param Request $request
     * @return array
     */
    public function getCountInfo(Request $request)
    {
        try {
            $data = ['total_money' => 0];
            $sql = "select sum(money - freight) as total_money from tbl_order where id in (select order_id from tbl_order_members_history where tbl_order_members_history.member_id = :member_id)";
            $sql .= " and tbl_order.status in (1,2,3,4,5,7) ";
            $result = BaseModel::runSql($sql,['member_id' => $this->memberId]);
            $data['total_money'] = moneyCent2Yuan($result[0]->total_money);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getList(Request $request){
        try {
            $page = intval($request->page) ? intval($request->page) : 1;
            $pagesize = intval($request->page_size) ? intval($request->page_size) : 20;
            // 获取数据
            $param['member_id'] = $this->memberId;
            // 查询条件
            $select = "select tbl_order.*,tbl_member.nickname,tbl_member.mobile ";
            $from = " from tbl_order left join tbl_member on tbl_member.id = tbl_order.member_id ";
            $where = " where tbl_order.id in (select order_id from tbl_order_members_history where tbl_order_members_history.member_id = :member_id)";
            $where .= " and tbl_order.status in (1,2,3,4,5,7) ";
            // 读取订单总数
            $totalInfo = BaseModel::runSql("select count(tbl_order.id) as total_record ".$from.$where,$param);
            $last_page = ceil($totalInfo[0]->total_record/$pagesize);
            // 列表
            $param['pagesize'] = $pagesize;
            $param['offset'] = ($page - 1) * $pagesize;
            $order = " order by tbl_order.created_at desc limit :offset, :pagesize";
            $orderList = BaseModel::runSql($select.$from.$where.$order,$param);
            $orderIds = [];    
            foreach($orderList as &$item){
                $item->money = moneyCent2Yuan($item->money - $item->freight);
                $orderIds[] = $item->id;
            }
            unset($item);
            // 列出订单商品
            if (count($orderIds)) {
                $sql = "select * from tbl_order_item where order_id in (".implode(',', $orderIds).")";
                $goodsList = BaseModel::runSql($sql);
                foreach($goodsList as &$good){
                    $good->sku_names = json_decode($good->sku_names,true);
                }
                unset($good);
                $itemList = new Collection($goodsList);
                foreach($orderList as &$item){
                    $item->items = $itemList->where('order_id',$item->id)->values();
                }
                unset($item);
            }
            $data = [
                'list' => $orderList,
                'total' => $totalInfo[0]->total_record,
                'last_page' => $last_page,
                'current' => $page,
                'page_size' => $pagesize,
            ];
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}