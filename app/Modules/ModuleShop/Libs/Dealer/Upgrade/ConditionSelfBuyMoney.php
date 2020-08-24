<?php
/**
 * 自购云仓订单金额
 * User: liyaohui
 * Date: 2019/11/30
 * Time: 11:27
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;

class ConditionSelfBuyMoney extends abstractCondition
{
    protected $name = '自购云仓订单金额满';
    protected $unit = '元';

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 判断某经销商是否满足此条件
     * @param int $memberId 经销商会员id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $count = CloudStockPurchaseOrderModel::query()->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->whereIn('status', Constants::getCloudStockPurchaseOrderPayStatus())
            ->selectRaw('sum(total_money) as order_money')
            ->value('order_money');
        return $count >= moneyYuan2Cent($this->value);
    }
}