<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Supplier;

use App\Modules\ModuleShop\Libs\Finance\Withdraw\WithdrawConditionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Constants;

class SupplierWithdrawConfigController extends BaseAdminController
{
    private $SupplierWithdrawConfigObj;

    public function __construct()
    {
        $this->SupplierWithdrawConfigObj = WithdrawConditionHelper::createInstance( Constants::FinanceType_Supplier);
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo()
    {
        try {
            $data = $this->SupplierWithdrawConfigObj->getConfig();
            if ($data) {
                $data['min_money'] = intval(moneyCent2Yuan($data['min_money']));
                $data['max_money'] = intval(moneyCent2Yuan($data['max_money']));
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑设置
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $param = $request->all();
            if ($param['min_money']) {
                $param['min_money'] = moneyYuan2Cent($param['min_money']);
            }
            if ($param['max_money']) {
                $param['max_money'] = intval(moneyYuan2Cent($param['max_money']));
            }
            $this->SupplierWithdrawConfigObj->editConfig($param);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

}
