<?php
/**
 * 快递工具类
 * User: liyaohui
 * Date: 2020/7/9
 * Time: 10:43
 */

namespace App\Modules\ModuleShop\Libs\Express;


use App\Modules\ModuleShop\Jobs\OrderExpressSyncJob;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\Order;
use YZ\Core\Logger\Log;

class ExpressHelper
{
    /**
     * 快递接口发送HTTP请求
     * @param string $url   接口地址
     * @param array $data   相关参数
     * @return mixed
     */
    public static function httpsRequest($url, $data = null)
    {
        $headers = [
            "Content-type: application/x-www-form-urlencoded",
            "Accept: application/json",
            "Cache-Control: no-cache", "Pragma: no-cache"
        ];
        $domain = 'https://' . config('app.EXPRESS_DOMAIN');
        $url = $domain . $url;
        $fields = (is_array($data)) ? http_build_query($data) : $data;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        }
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * 校验回调信息的sign
     * @param $callbackData
     * @param $appSecret
     * @return bool
     */
    public static function callbackVerify($callbackData, $appSecret) {
        ksort($callbackData);
        $str = '';
        foreach($callbackData AS $key=>$val) {
            if ($key != 'sign') {
                if (is_array($val)) {
                    $str .= $key . json_encode($val,JSON_UNESCAPED_UNICODE);
                } else {
                    $str .= $key . $val;
                }
            }
        }
        $str = $appSecret . $str . $appSecret;
        $sign = strtoupper(md5($str));
        return $sign == $callbackData['sign'];
    }

    /**
     * 处理回调
     * @param $data
     * @param $setting
     * @return array
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function callbackHandle($data, $setting)
    {
        // 授权 同步回调
        if ($data['code'] && !isset($data['type'])) {
            // 校验一下sign
            if (!self::callbackVerify($data, $setting['app_secret'])) {
                Log::writeLog('expressAuthorize', 'siteId: ' . $setting['site_id'] . "\r\n sign error \r\n data:" . var_export($data, true));
                return ['type' => 1, 'status' => 400, 'msg' => '签名错误'];
            }
            Log::writeLog('expressAuthorize', 'siteId: ' . $setting['site_id'] . "\r\n" . var_export($data, true));
            // 去获取token
            $expressSetting = new ExpressSetting($setting['site_id']);
            $res = $expressSetting->accessToken($data['code']);
            if (!$res) {
                return ['type' => 1, 'status' => 400, 'msg' => '授权失败'];
            } else {
                return ['type' => 1, 'status' => 200, 'msg' => '授权成功'];
            }
        }
        // 异步回调
        switch ($data['type']) {
            case 'SEND':
                $return = self::orderSendCallback($data['data']);
                break;
            case 'UPDATE_SEND':
                $return = self::orderUpdateCallback($data['data']);
                break;
            case 'CANCEL':
                $return = self::orderCancelCallback($data['data']);
                break;
            case 'FILLEXPNUM':
                $return = self::orderExpressNumCallback($data['data']);
                break;
        }
        if ($return) {
            return ['status' => '200'];
        } else {
            return ['status' => '400'];
        }
    }

    /**
     * 同步订单到快递100回调
     * @param $data
     * @return bool
     */
    public static function orderSendCallback($data)
    {
        // 先检测状态是否更新
        $order = OrderModel::query()
            ->where('id', $data['order_number'])
            ->select('express_sync_status')
            ->first();
        if ($data['status'] == 200) {
            if ($order['express_sync_status'] != ExpressConstants::OrderSynStatus_SyncSuccessed) {
                $order->update(['express_sync_status' => ExpressConstants::OrderSynStatus_SyncSuccessed]);
                Log::writeLog('expressSendOrderCallback', "Success siteId:" . $order['site_id'] .
                    ' orderId:' . $data['order_number']);
            }
            return true;
        } else {
            if ($order['express_sync_status'] != ExpressConstants::OrderSynStatus_SyncFail) {
                $order->update(['express_sync_status' => ExpressConstants::OrderSynStatus_SyncFail]);
                Log::writeLog('expressSendOrderCallback', "Fail siteId:" . $order['site_id'] .
                    ' orderId:' . $data['order_number'] .
                    ' errorCode' . $data['status'] .
                    ' errorMsg' . $data['message']);
            }
            return false;
        }
    }

    /**
     * 更新订单到快递100回调
     * @param $data
     * @return bool
     */
    public static function orderUpdateCallback($data)
    {
        // 先检测状态是否更新
        $order = OrderModel::query()
            ->where('id', $data['order_number'])
            ->select('express_sync_status')
            ->first();
        Log::writeLog('callback', var_export($data, true));
        if ($data['status'] == 200) {
            if ($order['express_sync_status'] != ExpressConstants::OrderSynStatus_UpdateSuccessed) {
                $order->update(['express_sync_status' => ExpressConstants::OrderSynStatus_UpdateSuccessed]);
                Log::writeLog('expressUpdateOrderCallback', "Success siteId:" . $order['site_id'] .
                    ' orderId:' . $data['order_number']);
            }
            return true;
        } else {
            if ($order['express_sync_status'] != ExpressConstants::OrderSynStatus_UpdateFail) {
                $order->update(['express_sync_status' => ExpressConstants::OrderSynStatus_UpdateFail]);
                Log::writeLog('expressUpdateOrderCallback', "Fail siteId:" . $order['site_id'] .
                    ' orderId:' . $data['order_number'] .
                    ' errorCode' . $data['status'] .
                    ' errorMsg' . $data['message']);
            }
            return false;
        }
    }

    /**
     * 取消订单同步到快递100回调
     * @param $data
     * @return bool
     */
    public static function orderCancelCallback($data)
    {
        if ($data['status'] == 200) {
            if ($data['succ_order_num_lis']) {
                OrderModel::query()->whereIn('id', $data['succ_order_num_lis'])
                    ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_CancelSuccessed]);
                Log::writeLog('expressCancelOrderCallback', "Success " .
                    ' orderId:' . $data['order_number']);
            }
            if ($data['fail_order_num_list']) {
                OrderModel::query()->whereIn('id', $data['fail_order_num_list'])
                    ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_CancelFail]);
                Log::writeLog('expressCancelOrderCallback', "Fail " .
                    ' orderId:' . $data['order_number']);
            }
            return true;
        } else {
            if ($data['fail_order_num_list']) {
                OrderModel::query()->whereIn('id', $data['fail_order_num_list'])
                    ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_CancelFail]);
                Log::writeLog('expressCancelOrderCallback', "Fail " .
                    ' orderId:' . $data['order_number'] .
                    ' errorCode' . $data['status'] .
                    ' errorMsg' . $data['message']);
            }
            return false;
        }
    }

    /**
     * 订单发货
     * @param $data
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function orderExpressNumCallback($data)
    {
        $allSave = true;
        foreach ($data as $item) {
            $orderIds = explode(',', $item['order_number']);
            $orderList = OrderModel::query()
                ->whereIn('id', $orderIds)
                ->with('items')
                ->get();
            foreach ($orderList as $order) {
                $delivery = [
                    'logistics_company' => self::getExpressNum($item['company_code']),
                    'logistics_name' => $item['kuadicom_name'],
                    'logistics_no' => $item['kuaidi_num']
                ];
                $itemIds = $order['items']->pluck('id')->toArray();
                $orderObj = Order::find($order['id']);
                $save = $orderObj->deliver($delivery, $itemIds, true);
                if ($save) {
                    Log::writeLog('expressNumOrderCallback', "Success " .
                        ' orderId:' . $order['id'] .
                        'data:' . var_export($item, true)
                    );
                } else {
                    $allSave = false;
                    Log::writeLog('expressNumOrderCallback', "Fail " .
                        ' orderId:' . $order['id'] .
                        'data:' . var_export($item, true)
                    );
                }
            }
        }

        return $allSave;
    }

    /**
     * 获取对应的快递公司key
     * @param $num
     * @return int|mixed
     */
    public static function getExpressNum($num)
    {
        $expressNum = [
            'yuantong' => Constants::ExpressCompanyCode_YuanTong,
            'zhongtong' => Constants::ExpressCompanyCode_ZhongTong,
            'yunda' => Constants::ExpressCompanyCode_YunDa,
            'shunfeng' => Constants::ExpressCompanyCode_ShunFeng,
            'youzhengguonei' => Constants::ExpressCompanyCode_PingYou,
            'huitongkuaidi' => Constants::ExpressCompanyCode_HuiTong,
            'youshuwuliu' => Constants::ExpressCompanyCode_YouSu,
            'tiantian' => Constants::ExpressCompanyCode_TianTian,
            'debangkuaidi' => Constants::ExpressCompanyCode_DeBang,
            'guotongkuaidi' => Constants::ExpressCompanyCode_GuoTong,
            'ems' => Constants::ExpressCompanyCode_EMS,
            'wanxiangwuliu' => Constants::ExpressCompanyCode_WanXiang,
            'jtexpress' => Constants::ExpressCompanyCode_JT
        ];
        if (isset($expressNum[$num])) {
            return $expressNum[$num];
        } else {
            return Constants::ExpressCompanyCode_Other;
        }
    }

    /**
     * 创建一个快递同步任务
     * @param $orderIds
     * @param $siteId
     * @param $type
     */
    public static function createExpressJob($orderIds, $siteId, $type)
    {
        // 先检测配置状态
        $setting = new ExpressSetting();
        // 状态开启才执行
        if ($setting->getModel()->status) {
            dispatch(new OrderExpressSyncJob($orderIds, $siteId, $type));
        }
    }

    /**
     * 订单全部退款之后的同步
     * @param $order
     */
    public static function orderCancelSync($order)
    {
        if (in_array($order['express_sync_status'], ExpressConstants::getSyncSuccessedStatus())) {
            self::createExpressJob($order['id'], $order['site_id'], ExpressConstants::OrderSynType_Cancel);
        }
    }
}