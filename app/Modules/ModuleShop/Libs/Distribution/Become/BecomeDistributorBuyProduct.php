<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Distribution\DistributionConfig;
use Illuminate\Support\Facades\Session;
use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use YZ\Core\Logger\Log;

/**
 * 购买指定商品成为分销商
 * Class BecomeDistributorBuyProduct
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
class BecomeDistributorBuyProduct extends AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_BuyProduct;
    private $productId = 0;

    /**
     * 实例化
     * BecomeDistributorBuyProduct constructor.
     * @param $memberModal
     * @param DistributionSetting|null $distributionSetting
     */
    public function __construct($memberModal, DistributionSetting $distributionSetting = null)
    {
        parent::__construct($memberModal, $distributionSetting);
        $this->productId = $this->setting->buy_product;
        $this->periodFlag = Constants::Period_OrderFinish; // 维权期后
    }

    /**
     * 覆写申请过程
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function apply()
    {
        // 写入session，表示用户已经申请过
        if ($this->productId) {
            Session::put(CodeConstants::SessionKey_DistributorApply_ProductID, $this->productId);
        }
        $result = parent::apply();
        return $result;
    }

    /**
     * 覆写自动审核过程
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function autoCheck()
    {
        if (Session::get(CodeConstants::SessionKey_DistributorApply_ProductID)) {
            $result = parent::autoCheck();
            Session::remove(CodeConstants::SessionKey_DistributorApply_ProductID);
            return $result;
        } else {
            $this->errorMsg = 'no apply';
            return false;
        }
    }

    /**
     * 自定义规则
     * @return bool|mixed
     * @throws \Exception
     */
    protected function customRule()
    {
        $distributionSetting = DistributionSetting::getCurrentSiteSetting();
        $order_status = $distributionSetting->calc_apply_valid_condition == 0 ?  Constants::getPaymentOrderStatus() : [Constants::OrderStatus_OrderFinished];
        $productArr = explode(',', $this->productId);
        // 检查是否有已购买过的数据
        $buyNum = OrderItemModel::query()
            ->from('tbl_order_item as order_item')
            ->leftJoin('tbl_order as order', 'order_item.order_id', '=', 'order.id')
            ->where('order.site_id', $this->member->getSiteID())
            ->where('order.member_id', $this->member->getMemberId())
            ->whereIn('order_item.product_id', $productArr)
            ->whereIn('order.status', $order_status)
            ->count(\DB::raw("distinct(order_item.product_id)"));
        $result = $buyNum > 0;
        if (!$result) {
            $this->errorMsg = trans('shop-front.distributor.buy_product_no');
            if ($this->productId) {
                $productData = Product::getList(['product_ids' => myToArray($this->productId)]);
                if ($productData) {
                    $this->setExtendData([
                        'product' => $productData['list'],
                    ]);
                }
            }
        }
        return $result;
    }
}