<?php
/**
 * 所有推荐下级订单金额
 * User: liyaohui
 * Date: 2020/5/8
 * Time: 16:35
 */

namespace App\Modules\ModuleShop\Libs\Custom\Site1696;


use App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use App\Modules\ModuleShop\Libs\Agent\Condition\abstractCondition;
use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Plugin\IPlugin;
use YZ\Core\Site\Site;

class AgentUpgradeConditionAllSubOrderMoney extends abstractCondition implements IPlugin
{

    protected $name = "所有推荐下级订单金额满";
    public $unit = '元';

    /**
     * 初始化
     * @param array $params
     * @return mixed|void
     */
    public function init(array $params)
    {
        $this->value = $params['value'];
    }

    /**
     * 插件执行
     * @param null $runTimeParams
     * @return bool|mixed
     */
    public function execute($runTimeParams = null)
    {
        return $this->canUpgrade($runTimeParams['member_id']);
    }

    /**
     * 是否可以升级
     * @param int $memberId
     * @param array $params
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $agentUpgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
        $orderStatue = $agentUpgradeSetting->order_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
        $siteId = Site::getCurrentSite()->getSiteId();
        //如果代理升级是在维权期后的，需要把退款的钱减去
        $select = $agentUpgradeSetting->order_valid_condition == 1
            ? 'sum( item.price * (item.num - after_sale_over_num) - item.point_money - item.coupon_money - ifnull(discount.discount_price,0) ) AS total'
            : 'sum( item.price * item.num - item.point_money - item.coupon_money - ifnull(discount.discount_price,0) ) AS total';
        $sql = 'SELECT
                    '. $select .'  
                FROM
                    tbl_order o 
                    LEFT JOIN tbl_order_item as item ON item.order_id = o.id 
                    AND item.has_commission_product = 1 
                    JOIN tbl_order_members_history as h1 on h1.order_id=o.id and h1.member_id=' . $memberId . ' and h1.`level`> 0 and h1.type=0
                    LEFT JOIN tbl_order_item_discount as discount on discount.item_id = item.id
                WHERE
                    o.site_id = '. $siteId .' 
                    AND o.`status` IN (' . implode(',', $orderStatue) . ')';
        $count = \DB::select($sql);
        return $count[0]->total >= $this->value;
    }

}