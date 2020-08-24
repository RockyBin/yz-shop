<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use  App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use  App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Site\Site;

/**
 * 自购指定商品升级条件
 */
class UpgradeConditionSelfBuyDesignatedProduct extends abstractCondition
{
    protected $value = '';
    protected $name = "自购指定商品";
    public $unit = '件';

    public function __construct($value, $productid)
    {
        $this->value = $value;
        $this->productid = $productid;
    }

    /**
     * 判断是否满足此代理升级条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->checkIsAgent($params)) {
            return false;
        }
        $flag = false;
        if ($this->productid) {
            $agentUpgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
            $orderStatue = $agentUpgradeSetting->order_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
            $siteId = Site::getCurrentSite()->getSiteId();
            //如果代理升级是在维权期后的，需要把退款的产品数量减去
            $select = $agentUpgradeSetting->order_valid_condition == 1 ? 'oi.num - oi.after_sale_over_num' : 'oi.num';
//            $sql = 'select
//                    sum(
//                    ' . $select . '
//                    ) as count,
//                    omh.order_id,
//                    oi.num,
//                    oi.after_sale_num,
//                    omh.member_id
//                    FROM
//                    `tbl_order_members_history` AS omh
//                    LEFT JOIN `tbl_order` AS `o` ON `o`.`id` = `omh`.`order_id`
//                    LEFT JOIN `tbl_order_item` AS oi ON oi.order_id = o.id
//                    WHERE
//                    `oi`.`product_id` IN (' . $this->productid . ')
//                    AND `o`.`status` IN (' . implode(',', $orderStatue) . ')
//                    AND omh.`level` = 0
//                    AND omh.member_id = ' . $memberId . '
//                    AND omh.type = 1
//                    GROUP BY
//                    omh.member_id';
            $sql = 'SELECT
                        SUM( '. $select .' ) AS count 
                    FROM
                        tbl_order AS o
                        JOIN tbl_order_item AS oi ON oi.order_id = o.id 
                        AND oi.product_id IN (' . $this->productid . ') 
                    WHERE
                        o.member_id = ' . $memberId . ' 
                        AND o.site_id = '. $siteId .'  
                        AND o.`status` IN (' . implode(',', $orderStatue) . ')';
            $count = \DB::select($sql);
            return $count[0]->count >= $this->value;
        }
        return $flag;
    }
}
