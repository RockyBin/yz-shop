<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Constants;
use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use YZ\Core\Site\Site;

/**
 * 团队指定商品升级条件
 */
class UpgradeConditionTeamBuyDesignatedProduct extends abstractCondition
{
    protected $value = '';
    protected $name = "团队指定商品";
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
            //level>=1 是order_members_history购买者与该表member_id的层级关系，团队=间推+直推+自购
            $sql = 'select 
                    sum(
                    '. $select .'
                    ) as count 
                    FROM 
                    `tbl_order_members_history` AS omh 
                    JOIN `tbl_order` AS `o` ON `o`.`id` = `omh`.`order_id` AND `o`.`status` IN (' . implode(',', $orderStatue) . ') 
                    JOIN `tbl_order_item` AS oi ON oi.order_id = o.id AND `oi`.`product_id` IN (' . $this->productid . ') 
                    WHERE 
                    omh.site_id='. $siteId .' 
                    AND omh.`level` >=0
                    AND omh.member_id = ' . $memberId . '
                    AND omh.type = 1';

            $count = \DB::select($sql);
            return $count[0]->count >= $this->value;
        }
        return $flag;
    }
}
