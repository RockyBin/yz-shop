<?php
/**
 * Created by Wenke.
 */

namespace App\Modules\ModuleShop\Libs\Finance;

use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Constants;
use App\Modules\ModuleShop\Libs\Order\Order;
use YZ\Core\Site\Site;

class Balance
{
    private $siteId = 0; // 站点ID
    private $order = null;

    /**
     * 初始化
     * Finance constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
        $this->order = new Order($this->siteId);
    }

    /**
     * 查询列表
     * @param $params
     * @return array
     */
    public function getList($params)
    {
        $data = $this->order->getList($params);

        return $data;
    }

    /**
     * 导出
     * @param $params
     * @return array
     */
    public function convertOutputData($item)
    {
        $item->money = moneyCent2Yuan($item->money);
        foreach ($item['item_list'] as $items) {
            $items->total_money = moneyCent2Yuan($items->total_money);
            $items->cost = moneyCent2Yuan($items->cost);
            $items->price = moneyCent2Yuan($items->price);
            $items->sub_total = moneyCent2Yuan($items->sub_total);
            $items->profit = moneyCent2Yuan($items->profit);
            $items->discount = moneyCent2Yuan($items->discount);
            if ($items->commission) {
                $items_commission = json_decode($items->commission, true);
                foreach ($items_commission as &$commission_items) {
                    $commission_items['money'] = moneyCent2Yuan($commission_items['money']);
                }
                $items->commission = new Collection($items_commission);
            }
        }

        if ($item->commission) {
            $item_commission = json_decode($item->commission, true);
            foreach ($item_commission as &$commission_item) {
                $commission_item['money'] = moneyCent2Yuan($commission_item['money']);
            }
            $item->commission = new Collection($item_commission);
        }
        //结算管理状态，需要两个状态来订 1:待结算 2：结算失败 3：结算成功
        if (in_array($item->status, [Constants::OrderStatus_OrderPay, Constants::OrderStatus_OrderSend, Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess]) && $item->has_after_sale == 0) {
            //待结算
            $item->balance_status = '待结算';
        } else if ($item->has_after_sale == 1) {
            //结算失败
            $item->balance_status = '结算失败';
        } else if ($item->status == Constants::OrderStatus_OrderFinished && $item->has_after_sale == 0) {
            //已结算，结算成功
            $item->balance_status = '结算成功';
        }
        $item->inout_type_text = FinanceHelper::getFinanceInOutTypeText($item->in_type, $item->out_type);

        return $item;
    }
}