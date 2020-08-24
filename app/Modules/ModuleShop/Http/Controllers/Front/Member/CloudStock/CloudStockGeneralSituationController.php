<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuLog;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuSettle;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceReward;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * 云仓概况
 * Class CloudStockGeneralSituationController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock
 */
class CloudStockGeneralSituationController extends BaseMemberController
{
    private $_cloudStock = null;

    function __construct()
    {
        parent::__construct();
    }

    function index()
    {
        $this->initCloudStock();

        $data['member'] = $this->getMemberInfo();
        $data['purchase_order_situation']=$this->getMyPurchaseOrderSituation();
        $data['takedelivery_order_situation']=$this->getMyTakedeliveryOrderSituation();
        $data['under_purchase_order_situation']=$this->getMyUnderPurchaseOrderSituation();
        $data['total_incoming']=$this->getSettle()['allStatus1'];
        $data['reward'] = DealerReward::getMemberReward($this->memberId);
        $data['out_reward'] = DealerReward::getMemberOutReward($this->memberId);
        return makeApiResponseSuccess('', $data);
    }

    public function initCloudStock()
    {
        $this->_cloudStock = new CloudStock($this->memberId, 0);
    }


    public function getMemberInfo()
    {
        $memberId = $this->memberId;
        $member = new Member($memberId);
        if (!$member->checkExist()) {
            return makeServiceResultFail("不是会员");
        }
        $memberModel = $member->getModel();
        $levelName = DealerLevelModel::query()->where('site_id', $memberModel->site_id)
            ->where('id', $memberModel->dealer_level)
            ->value('name');
        $memberInfo = [
            'id' => $memberModel->id,
            'name' => $memberModel->name,
            'nickname' => $memberModel->nickname,
            'headurl' => $memberModel->headurl,
            'mobile' => $memberModel->mobile,
            'level_name' => $levelName,
        ];
        return $memberInfo;
    }

    /**
     * 获取我的进货情况
     * return array
     */
    private function getMyPurchaseOrderSituation()
    {
        $data=$this->_cloudStock->getPurchaseOrderCount(['status' => Constants::getCloudStockPurchaseOrderPayStatus()]);
        $data['total_money']=moneyCent2Yuan($data['total_money']);
        return $data;
    }

    /**
     * 获取我的提货情况
     * return array
     */
    private function getMyTakedeliveryOrderSituation()
    {
        return $this->_cloudStock->getTakeDeliveryOrderCount(['status' => [Constants::CloudStockTakeDeliveryOrderStatus_Finished]]);
    }

    /**
     * 获取我的下级进货情况
     * return array
     */
    private function getMyUnderPurchaseOrderSituation()
    {
        $count = $this->_cloudStock->getUnderPurchaseOrder(['status' => Constants::getCloudStockPurchaseOrderPayStatus()]);
        $purchaseOrder['count'] = $count['count'];
        $purchaseOrder['under_purchaseorder_incoming'] = moneyCent2Yuan($count['money']);
        return $purchaseOrder;
    }

    private function getSettle()
    {
        $settle = CloudStock::getSettleCount($this->memberId);
        $data= array_map(function ($item) {
            return moneyCent2Yuan($item);
        }, $settle);
        return $data;
    }
}