<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Member\Member;
use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use  App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Site\Site;

/**
 * 间推订单金额升级条件
 */
class UpgradeConditionIndirectOrderMoney extends abstractCondition
{
    protected $value = '';
    protected $name = "间推订单金额满";
    protected $onlyAgent = true;
    public $unit = '元';

    public function __construct($value)
    {
        $this->value = $value;
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
        $agentUpgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
        $orderStatue = $agentUpgradeSetting->order_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
        $siteId = Site::getCurrentSite()->getSiteId();
        $select = $agentUpgradeSetting->order_valid_condition == 1 ? 'sum( o.money + o.after_sale_money ) AS total' : 'sum(o.money) as total';
        //以order_members_history为主表连接order表来判断订单状态是否符合
        //level>1 是order_members_history购买者与该表member_id的层级关系，0是自己 1 是直接 >1是间推。
//        $sql = 'select
//                    ' . $select . ',
//                    omh.order_id,
//                    o.money,
//                    o.after_sale_money,
//                    omh.member_id
//                    FROM
//                    `tbl_order_members_history` AS omh
//                    LEFT JOIN `tbl_order` AS `o` ON `o`.`id` = `omh`.`order_id`
//                    WHERE
//                    `o`.`status` IN (' . implode(',', $orderStatue) . ')
//                    AND omh.`level` > 1
//                    AND omh.member_id = ' . $memberId . '
//                    AND omh.type = 1
//                    GROUP BY
//                    omh.member_id';
        $sql = 'SELECT
                    '. $select .'  
                FROM
                    tbl_order o 
                    JOIN tbl_order_members_history as h1 on h1.order_id=o.id and h1.member_id=' . $memberId . ' and h1.`level`>1 and h1.type=0 
                    JOIN tbl_order_members_history as h2 on h2.order_id=o.id and h2.member_id=' . $memberId . ' and h2.type=1
                WHERE
                    o.site_id = '. $siteId .' 
                    AND o.`status` IN (' . implode(',', $orderStatue) . ')';

        $count = \DB::select($sql);
        return $count[0]->total >= $this->value;
    }
}
