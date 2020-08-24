<?php
namespace App\Modules\ModuleShop\Libs\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierSettleModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;

/**
 * 供应商的一些统计信息
 * Class SupplierSettleAdmin
 * @package App\Modules\ModuleShop\Libs\Supplier
 */
class SupplierPlatformCount
{
    private $siteId = 0; // 站点ID
    private $supplierId = 0; // 供应商ID

    /**
     * 初始化
     * Order constructor.
     * @param int $siteId 站点ID
     * @param int $supplierId 供应商会员ID
     */
    public function __construct($siteId,$supplierId)
    {
        $this->siteId = $siteId;
        $this->supplierId = $supplierId;
        if($this->siteId < 1 || $this->supplierId < 1){
            throw new \Exception("数据错误，站点ID或供应商ID不对");
        }
    }

    /**
     * 获取经销商基础信息
     * @return mixed
     * @throws \Exception
     */

    /**
     * 获取供应商的一些统计信息
     * @param array $params 参数
     * @return mixed
     */
    public function getCountInfo($params = [])
    {
        if($params['count_order']) {
            // 订单统计
            $orderCount = OrderModel::query()->where('site_id', $this->siteId)
                ->where('supplier_member_id', $this->supplierId)->selectRaw('sum(money) as total_money, count(id) as order_num')->first();

            $data['order_count'] = intval($orderCount->order_num);
            $data['order_money'] = moneyCent2Yuan($orderCount->total_money);
        }

        if($params['count_settle']) {
            // 已结算金额
            $settled = FinanceModel::query()->where('site_id', $this->siteId)->where('member_id', $this->supplierId)->where('type', \YZ\Core\Constants::FinanceType_Supplier)
                ->where('money', '>', 0)->sum('money');

            // 未结算金额
            $unsettled = SupplierSettleModel::query()->where('site_id', $this->siteId)->where('supplier_member_id', $this->supplierId)
                ->where('status', 0)->sum(DB::raw('(money + freight + after_sale_money + after_sale_freight)'));

            $data['settled'] = moneyCent2Yuan($settled);
            $data['unsettled'] = moneyCent2Yuan($unsettled);
        }

        if($params['count_withdraw']) {
            // 提现中
            $withdrawing = FinanceModel::query()->where('site_id', $this->siteId)->where('member_id', $this->supplierId)->where('type', \YZ\Core\Constants::FinanceType_Supplier)
                ->whereIn('out_type', [\YZ\Core\Constants::FinanceOutType_SupplierToBalance, \YZ\Core\Constants::FinanceOutType_Withdraw])->where('money', '<', 0)
                ->where('status', \YZ\Core\Constants::FinanceStatus_Freeze)->sum('money');

            // 已提现
            $withdrawn = FinanceModel::query()->where('site_id', $this->siteId)->where('member_id', $this->supplierId)->where('type', \YZ\Core\Constants::FinanceType_Supplier)
                ->whereIn('out_type', [\YZ\Core\Constants::FinanceOutType_SupplierToBalance, \YZ\Core\Constants::FinanceOutType_Withdraw])->where('money', '<', 0)
                ->where('status', \YZ\Core\Constants::FinanceStatus_Active)->sum('money');

            // 可提现
            $balance = FinanceHelper::getSupplierBalance($this->supplierId);

            $data['withdrawing'] = moneyCent2Yuan(abs($withdrawing));
            $data['withdrawn'] = moneyCent2Yuan(abs($withdrawn));
            $data['balance'] = moneyCent2Yuan(abs($balance));
        }

        return $data;
    }
}