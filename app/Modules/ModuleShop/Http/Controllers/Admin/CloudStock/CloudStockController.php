<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\Agent;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockApplySetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\Dealer;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSku;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuLog;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuSettle;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use YZ\Core\Site\Site;
use Illuminate\Foundation\Bus\DispatchesJobs;
use YZ\Core\Constants as CoreConstants;

class CloudStockController extends BaseAdminController
{
    use DispatchesJobs;

    /**
     * 获取云仓列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $params = $request->all();
            $data = CloudStock::getList($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取单个会员的云仓信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $mid = $request->get('member_id');
            $stock = new Dealer();
            $baseInfo = $stock->getDealerInfo($mid);
            return makeApiResponseSuccess('ok', $baseInfo);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取单个会员的云仓的SKU子仓信息
     * @param Request $request
     * @return array
     */
    public function getSkuList(Request $request)
    {
        try {
            $list = CloudStockSku::getList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 手工调整库存
     *
     * @param Request $request
     * @return void
     */
    public function adjustInventory(Request $request)
    {
        try {
            $data = $request->all();
            foreach ($data as $item) {
                $id = $item['id'];
                $num = $item['num'];
                $stock = new CloudStockSku($id);
                $stock->adjustInventoryManual($num);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 设置云仓状态
     *
     * @param Request $request
     * @return void
     */
    public function setStatus(Request $request)
    {
        try {
            $data = $request->all();
            $stock = new CloudStock($data['member_id']);
            $stock->setStatus($data['status']);
            if ($data['status'] == Constants::CommonStatus_Active) {
                $memberModel = (new Member($data['member_id']))->getModel();
                MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Open, $memberModel);
            }

            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 添加云仓商品
     *
     * @param Request $request
     * @return void
     */
    public function addProduct(Request $request)
    {
        try {
            $data = $request->all();
            if (!$data['member_id']) throw new \Exception("缺少会员ID参数");
            foreach ($data['data'] as $item) {
                $productId = $item['product_id'];
                $skuId = $item['sku_id'];
                $num = $item['num'];
                $stock = new CloudStockSku($data['member_id'], $productId, $skuId);
                $stock->adjustInventoryManual($num, 1);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取SKU子仓出入库记录
     * @param Request $request
     * @return array
     */
    public function getSkuLogList(Request $request)
    {
        try {
            $list = CloudStockSkuLog::getList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取云仓结算汇总信息
     * @param Request $request
     * @return array
     */
    public function getSettleSummary(Request $request)
    {
        try {
            $mid = $request->get('member_id');
            $info = CloudStock::getMemberSettleSummary($mid);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取云仓结算记录
     * @param Request $request
     * @return array
     */
    public function getSettleList(Request $request)
    {
        try {
            $request->noGroupBy = 1;
            $list = CloudStockSkuSettle::getList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新增云仓前的检测
     * @param Request $request
     * @return array
     */
    public function checkAddBerfore(Request $request)
    {
        try {
            $member_id = $request->member_id;
            $cloudStockApplySetting = (new CloudStockApplySetting())->getModel();
            if (!$request->member_id) {
                return makeApiResponseFail('会员ID不能为空');
            }
            if ($cloudStockApplySetting->first_give_stock == 0 || $cloudStockApplySetting->admin_first_give_stock == 0) {
                return makeApiResponseSuccess('ok');
            }
            $agent = (new Agent())->checkAgentExist($member_id);
            if ($agent) {
                $member = new Member($member_id);
                $memberModel = $member->getModel();
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新增云仓
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            $member_id = $request->member_id;
            if (!$request->member_id) {
                return makeApiResponseFail('会员ID不能为空');
            }
            if ($request->ignore_no_sell) {
                session('FirstGiveStock_IgnoreNoSell', $request->ignore_no_sell);
            }
            $model = CloudStock::createCloudStock($member_id);
            if ($model) {
                $this->createCloudStockAfter($model);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    /**
     * 建立新的云仓的后置事件
     * @return boolean
     */
    private function createCloudStockAfter(CloudStockModel $model)
    {
        try {
            $cloudStock = new CloudStock($model);
            $cloudStock->createCloudStockAfter($model);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取代理列表
     * @param Request $request
     * @return array
     */
    public function getAgentList(Request $request)
    {
        try {
            $params = $request->all();
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $params['show_agent_commission'] = true;
            $agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
            if ($params['level'] > $agentBaseSetting->level) {
                makeApiResponseFail('不允许查询大于设置的代理等级');
            }
            if ($params['level']) {
                $params['agent_apply_level'] = $params['level'];
            } else {
                switch (true) {
                    case  $agentBaseSetting->level == 1:
                        $params['agent_apply_level'] = [1];
                        break;
                    case  $agentBaseSetting->level == 2:
                        $params['agent_apply_level'] = [1, 2];
                        break;
                    case  $agentBaseSetting->level == 3:
                        $params['agent_apply_level'] = [1, 2, 3];
                        break;
                }
            }

            $data = Agent::getAgentList($params, $page, $pageSize);
            $hasCloudstock = Site::getCurrentSite()->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK);
            foreach ($data['list'] as $item) {
                $item->agent_commission = moneyCent2Yuan($item->agent_commission);
                if (!$hasCloudstock) $item->cloudstock_status = 0;
            }
            $data['level'] = $agentBaseSetting->level;
            $data['cloud_stock_open_status'] = 1; //云仓已经取消可以关闭的功能，后面这行可以删除
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新增某会员云仓库存商品
     * @param $memberId
     * @return mixed
     */
    public function editCloudstockSkuProduct(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请输入正确的会员ID');
            }
            if (!isset($request->decrease_type)) {
                return makeApiResponseFail('请输入正确的扣减类型');
            }
            if (!isset($request->change_type)) {
                return makeApiResponseFail('请输入正确的修改类型');
            }
            // 1 是添加 2是减少
            if ($request->change_type == 1) {
                $res = CloudStockSku::addCloudstockSkuProduct($request->member_id, $request->decrease_type, $request->product_list);
            } elseif ($request->change_type == 2) {
                $res = CloudStockSku::decreaseCloudstockSkuProduct($request->member_id, $request->product_list);
            }

            if ($res['code'] != 200) {
                return makeApiResponse(502, '存在库存不足够的商品', $res['data']);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新增某会员云仓库存商品
     * @param $memberId
     * @return mixed
     */
    public function editCloudstockSkuProductList(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请输入正确的会员ID');
            }
            if (!isset($request->decrease_type)) {
                return makeApiResponseFail('请输入正确的扣减类型');
            }
            if (!isset($request->change_type)) {
                return makeApiResponseFail('请输入正确的修改类型');
            }
            // 1 是添加 2是减少
            $data = CloudStockSku::ProductInventoryList($request->member_id,$request->decrease_type,$request->change_type,$request->product_list);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


}
