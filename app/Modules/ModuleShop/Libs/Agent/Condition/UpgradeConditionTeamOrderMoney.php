<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Member\Member;
use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use  App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Site\Site;

/**
 * 团队订单金额升级条件
 */
class UpgradeConditionTeamOrderMoney extends abstractCondition
{
    protected $value = '';
    protected $name = "团队订单金额满";
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
        //如果代理升级是在维权期后的，需要把退款的钱减去
        $select = $agentUpgradeSetting->order_valid_condition == 1 ? 'sum( o.money + o.after_sale_money ) AS total' : 'sum(o.money) as total';
        //以order_members_history为主表连接order表来判断订单状态是否符合
        //level>=0 是order_members_history购买者与该表member_id的层级关系，0是自己 1 是直接 >1是间推。
        $sql = 'SELECT  
                    '. $select .' 
                    FROM
                    `tbl_order_members_history` AS omh
                    JOIN `tbl_order` AS `o` ON `o`.`id` = `omh`.`order_id` AND `o`.`status` IN (' . implode(',', $orderStatue) . ') 
                    WHERE
                    omh.site_id='. $siteId .' 
                    AND omh.`level` >=0
                    AND omh.member_id = ' . $memberId . '
                    AND omh.type = 1';

        $count = \DB::select($sql);

        return $count[0]->total >= $this->value;
    }
}
