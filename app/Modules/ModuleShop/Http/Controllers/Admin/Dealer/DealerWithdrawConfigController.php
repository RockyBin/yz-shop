<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerWithdrawConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;

class DealerWithdrawConfigController extends BaseAdminController
{
    private $WithdrawConfigObj;

    public function __construct()
    {
        $this->WithdrawConfigObj = new DealerWithdrawConfig();
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo()
    {
        try {
            $data = $this->WithdrawConfigObj->getInfo();
            if ($data) {
                $data->min_money = intval(moneyCent2Yuan($data->min_money));
                $data->max_money = intval(moneyCent2Yuan($data->max_money));
            }
            $payConfit = new PayConfig();
            $pay_data = $payConfit->getInfo();
            $type = json_decode($pay_data->type, true);
            $data['pay_type']=$type;
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 新增设置
     * @return Response
     */
    public function add(Request $request)
    {
        try {
            $param = $request->all();
            if ($param['min_money']) {
                $param['min_money'] = intval(moneyYuan2Cent($param['min_money']));
            } else {
                $param['min_money'] = 100;
            }
            if ($param['max_money']) {
                $param['max_money'] = intval(moneyYuan2Cent($param['max_money']));
            } else {
                $param['max_money'] = 500000;
            }
            $result = $this->WithdrawConfigObj->add($param);
            return makeApiResponseSuccess($result, 'ok');
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
            } else {
                $param['min_money'] = 0;
            }
            if ($param['max_money']) {
                $param['max_money'] = intval(moneyYuan2Cent($param['max_money']));
            } else {
                $param['max_money'] = 500000;
            }
            $this->WithdrawConfigObj->edit($param);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取支付配置信息
     * @return Response
     */
    public function checkPayConfig()
    {
        try {
            $payConfit = new PayConfig();
            $data = $payConfit->getInfo();
            $type = json_decode($data->type, true);
            if (array_search('1', $type)) {
                return makeApiResponseSuccess('ok', $type);
            } else {
                return makeApiResponseFail('请先去配置支付接口');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
    public function updateWithdrawConfig(){
        try {
            $this->WithdrawConfigObj->updateWithdrawConfig();
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
