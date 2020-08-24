<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSku;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuLog;
use App\Modules\ModuleShop\Libs\Dealer\Dealer;
use App\Modules\ModuleShop\Libs\Dealer\DealerAccount;
use App\Modules\ModuleShop\Libs\Dealer\DealerApplySetting;
use App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\MemberInfo;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use YZ\Core\Site\Site;

/**
 * 我的云仓
 * Class CloudStockCenterController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock
 */
class CloudStockCenterController extends BaseMemberController
{
    private $_cloudStock = null;
    private $_levels = null;

    function __construct()
    {
        parent::__construct();
        $this->_levels = DealerLevel::getLevelList(['status' => 1], true);

    }

    function index()
    {
        //读取经销商基本信息
        $dealer = DealerModel::query()
            ->leftJoin('tbl_dealer_level as dl', 'dl.id', '=', 'tbl_dealer.dealer_apply_level')
            ->leftJoin('tbl_member as invite', 'tbl_dealer.invite_review_member', '=', 'invite.id')
            ->leftJoin('tbl_member as parent', 'tbl_dealer.parent_review_member', '=', 'parent.id')
            ->selectRaw('tbl_dealer.*,dl.name as level_name,invite.nickname as invite_nickname,parent.nickname as parent_nickname')
            ->find($this->memberId);
        if ($dealer->apply_condition) $dealer->apply_condition = json_decode($dealer->apply_condition, true);
        if (!$dealer) {
            return makeApiResponse(404, '用户没有申请经销商');
        }

        if ($dealer->status == 0) {
            return makeApiResponse(405, '经销商审核中', $dealer);
        }
        if ($dealer->status == -1) {
            return makeApiResponse(406, '经销商审核不通过', $dealer);
        }
        if ($dealer->status == -3) {
            return makeApiResponse(408, '等待支付经销商加盟费', $dealer);
        }

        //初始化云仓信息
        $this->initCloudStock();
        if (!$this->checkCloudStockOpen()) {
            return makeApiResponse(501, '云仓功能没有开启');
        }
        $cloudStockModel = $this->_cloudStock->getModel();
        if (($cloudStockModel->status == 0 && $dealer->status == 1) && !$this->checkMemberCloudStockDataExist()) {
            return makeApiResponse(502, '用户没有开通云仓');
        }
        $data['dealer'] = $dealer;
        $data['member'] = $this->getMemberInfo();
//        if ($dealer->status == -2) {
//            return makeApiResponse(407, '已经取消经销商资格', $data);
//        }

        $data['cloud_stock_total_inventory'] = $this->_cloudStock->getTotalInventory();
        $cloudStockBalance = $this->_cloudStock->getMoneyCount('balance');
        $data['cloud_stock_balance'] = moneyCent2Yuan($cloudStockBalance);
        //开启等级
        $maxWeight = $this->_levels->sortByDesc('weight')->first()->weight;
        $data['max_level_weight'] = $maxWeight; //开启的最大等级权重
        //工作台中订单管理，显示小红点
        $data['order'] = $this->getOrderCount();
        // 审核管理红点
        $data['has_need_verify'] = $this->_cloudStock->hasNeedVerify();
        // 是否需要补货
        $data['has_replenish'] = $this->_cloudStock->hasReplenish();
        // 是否需要显示海报
        $data['show_share_paper'] = CloudStock::isShowSharePaper();
        // 是否有需要操作的奖金
        $data['has_reward'] = DealerReward::hasNeedVerifyOrExchangeReward($this->memberId);

        if (($cloudStockModel->status == 0 && $this->checkMemberCloudStockDataExist())) {
            return makeApiResponse(503, '被取消资格，但用户有订单数据', $data);
        }
        return makeApiResponseSuccess('', $data);
    }

    public function initCloudStock()
    {
        $this->_cloudStock = new CloudStock($this->memberId, 1);
    }

    /**
     * 获取云仓工作台中订单管理的各种订单的数量
     * return array
     */
    private function getOrderCount()
    {
        $order['purchase_order_count'] = $this->_cloudStock->getPurchaseOrderCount(['status' => [Constants::CloudStockPurchaseOrderStatus_NoPay, Constants::CloudStockPurchaseOrderStatus_Pay, Constants::CloudStockPurchaseOrderStatus_Reviewed]])['count'];
        $order['takedelivery_order_count'] = $this->_cloudStock->getTakeDeliveryOrderCount(['status' => [Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver, Constants::CloudStockTakeDeliveryOrderStatus_Delivered, Constants::CloudStockTakeDeliveryOrderStatus_Nopay]])['count'];
        $order['under_purchase_order'] = $this->_cloudStock->getUnderPurchaseOrder(['status' => [Constants::CloudStockPurchaseOrderStatus_NoPay, Constants::CloudStockPurchaseOrderStatus_Pay, Constants::CloudStockPurchaseOrderStatus_Reviewed]])['count'];
        return $order;
    }

    public function getMemberInfo()
    {
        $memberId = $this->memberId;
        $member = new Member($memberId);
        if (!$member->checkExist()) {
            return makeServiceResultFail("不是会员");
        }
        $memberModel = $member->getModel();
        $dealerLevel = intval($memberModel->dealer_level);
        $levelName = $this->_levels->where('id', $dealerLevel)->first()->name;
        $weight = $this->_levels->where('id', $dealerLevel)->first()->weight;
        $memberInfo = [
            'id' => $memberModel->id,
            'name' => $memberModel->name,
            'nickname' => $memberModel->nickname,
            'headurl' => $memberModel->headurl,
            'mobile' => $memberModel->mobile,
            'dealer_level' => $dealerLevel,
            'level_name' => $levelName,
            'level_weight' => $weight,
        ];
        return $memberInfo;
    }

    /**
     * 检测会员的云仓订单数据和库存\是否存在
     * return Boolean true 存在 false 不存在
     */
    function checkMemberCloudStockDataExist()
    {
        $PurchaseOrderCount = $this->_cloudStock->getPurchaseOrderCount()['count'];
        $TakeDeliveryOrderCount = $this->_cloudStock->getTakeDeliveryOrderCount()['count'];
        $Inventory = $this->_cloudStock->getTotalInventory();
        $UnderPurchaseOrder = $this->_cloudStock->getUnderPurchaseOrder()['count'];
        if ($PurchaseOrderCount == 0 && $TakeDeliveryOrderCount == 0 && $Inventory == 0 && $UnderPurchaseOrder == 0) {
            return false;
        }
        return true;
    }

    /**
     * 检测云仓功能是否有开启
     * return
     */
    function checkCloudStockOpen()
    {
        $site = Site::getCurrentSite();
        return $site->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK);
    }

    /**
     * 云仓出库入库的记录
     * return list
     */
    function getSkuLog(Request $request)
    {
        try {
            $request->member_id = $this->memberId;
            $param = $request->toArray();
            $param['member_id'] = $this->memberId;
            $list = CloudStockSkuLog::getList($param);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 直属供货商
     * return array
     */
    function getDirectlyUnderSupplier()
    {
        try {
            $data = CloudStock::getDirectlyUnderSupplier($this->memberId);
            if ($data) {
                return makeApiResponseSuccess('ok', $data);
            } else {
                return makeServiceResultFail("无直属供货商");
            }

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取某会员的云仓团队
     * return array
     */
    function getCloudStockTeam()
    {
        try {
            $list = CloudStock::getCloudStockTeam($this->memberId);
            foreach ($list as $item) {
                $item->level_text = $item->level == 1 ? '直接拿货' : '间接拿货';
                $item->agent_level_text = Constants::getAgentLevelTextForFront($item->agent_level);
            }
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 云仓库存转移
     * return array
     */
    function editCloudstockSkuProduct(Request $request)
    {
        if (!$request->transfer_member_id) {
            return makeApiResponseFail('请输入正确的转移对象');
        }
        $member = new Member($this->memberId);
        // 检查支付密码 402 的时候需要走设置流程
        if ($member->payPasswordIsNull()) {
            return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
        }
        if (!$member->payPasswordCheck($request->password)) {
            return makeApiResponse(406, trans('shop-front.shop.pay_password_error'));
        }
        if (!$request->product_list) {
            return makeApiResponseFail('转移产品列表不能为空');
        }
        if ($member->getModel()->dealer_level == 0) {
            return makeApiResponse(503, '用户被取消资格，不能转移库存');
        }
        $res = CloudStockSku::addCloudstockSkuProduct($request->transfer_member_id, 1, $request->product_list, $this->memberId);
        if ($res['code'] != 200) {
            return makeApiResponse(502, '存在库存不足够的商品', $res['data']);
        }
        return makeApiResponseSuccess('ok');
    }


    public function getDealerSubList(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $data = (new MemberInfo($memberId))->getDealerSubList($request->all());
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取经销商中心充值余额配置
     * return array
     */
    public function getBalanceConfig()
    {
        try {
            $config = Finance::getPayConfig(0);
            $config['recharge_balance_target'] = (DealerBaseSetting::getCurrentSiteSetting())->recharge_balance_target;
            $member = (new Member($this->memberId))->getInfo();
            if ($member->dealer_parent_id && $config['recharge_balance_target'] == 1) {
                $config['parent_pay_config'] = DealerAccount::getDealerPayConfig($member->dealer_parent_id,true);
            }
            if ($member->dealer_parent_id > 0) {
                $dealerParent = (new Member($member->dealer_parent_id))->getModel();
                $config['dealer_parent_id'] = $dealerParent->id;
                $config['dealer_parent_nickname'] = $dealerParent->nickname;
            }

            return makeApiResponseSuccess('ok', $config);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


}