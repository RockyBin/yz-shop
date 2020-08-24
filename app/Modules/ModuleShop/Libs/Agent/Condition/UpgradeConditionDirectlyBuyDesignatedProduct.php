<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Constants;
use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use YZ\Core\Site\Site;

/**
 * 直推指定商品升级条件
 */
class UpgradeConditionDirectlyBuyDesignatedProduct extends abstractCondition
{
    protected $value = '';
    protected $name = "直推指定商品";
    protected $onlyAgent = true;
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
            //以order_members_history为主表连接order表来判断订单状态是否符合，连接order_item来判断订单是否含有该指定产品。
            //level=1 是order_members_history购买者与该表member_id的层级关系，也就是level=1的时候member_id与购买者就是直接推荐关系
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
//                    AND omh.`level` = 1
//                    AND omh.member_id = ' . $memberId . '
//                    AND omh.type = 1
//                    GROUP BY
//                    omh.member_id';
            // 查找直推的下级 应该首先满足直接推荐关系 并且 在代理关系上 代理等级不能比当前会员高
            $sql = 'SELECT
	                sum( '. $select .' ) as count 
                    FROM
                        tbl_order_item oi
                        JOIN tbl_order AS o ON oi.order_id = o.id 
                        AND o.`status` IN (' . implode(',', $orderStatue) . ') 
                    WHERE
                        oi.site_id='. $siteId .' 
                        AND order_id IN ( SELECT order_id FROM tbl_order_members_history WHERE member_id = ' . $memberId . ' AND `level` = 1 AND type = 0 ) 
                        AND order_id IN ( SELECT order_id FROM tbl_order_members_history WHERE member_id = ' . $memberId . ' AND type = 1 ) 
                        AND oi.product_id IN (' . $this->productid . ')';

            $count = \DB::select($sql);
            return $count[0]->count >= $this->value;
        }
        return $flag;
    }
}
