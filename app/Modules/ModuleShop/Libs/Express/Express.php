<?php
/**
 * 快递逻辑类
 * User: liyaohui
 * Date: 2020/7/8
 * Time: 16:57
 */

namespace App\Modules\ModuleShop\Libs\Express;


use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;

class Express
{
    protected $expressSetting;
    protected $siteId;

    /**
     * Express constructor.
     * @param ExpressSetting $setting 配置类
     * @param int $siteId 站点id
     */
    public function __construct(ExpressSetting $setting = null, $siteId = 0)
    {
        $this->siteId = $siteId ?: getCurrentSiteId();
        if ($setting instanceof ExpressSetting) {
            $this->expressSetting = $setting;
        } else {
            $this->expressSetting = new ExpressSetting($this->siteId);
        }
    }

    /**
     * 获取设置
     * @return ExpressSetting
     */
    public function getSetting()
    {
        return $this->expressSetting;
    }

    /**
     * 生成订单数据
     * @param $order
     * @param $orderItems
     * @return array
     * @throws \Exception
     */
    public function createOrderData($order, $orderItems)
    {
        $address = $this->getOrderAddressText($order);
        if (!$address) {
            throw new \Exception('找不到地址', 601);
        }
        $settingModel = $this->expressSetting->getModel();
        $itemsData = [];
        $productNameArr = [];
        $proCount = 0;
        foreach ($orderItems as $item) {
            $goods = [
                'name' => $item['name'],
                'count' => $item['num']
            ];
            $proCount += $item['num'];
            $skuName = json_decode($item['sku_names'], true);
            if ($skuName) {
                $goods['spec'] = implode(',', $skuName);
            }
            $itemsData[] = $goods;
            if (!isset($productNameArr[$item['product_id']])) {
                $productNameArr[$item['product_id']] = [];
            }
            $productNameArr[$item['product_id']][] = $goods;
        }
        // 拼接快递单上显示的商品信息
        $productNameStr = '';
        foreach ($productNameArr as $pro) {
            $productNameStr .= $pro[0]['name'];
            if (count($pro) > 1) {
                $productNameStr .= ':';
                foreach ($pro as $key => $sku) {
                    if ($key > 0) {
                        $productNameStr .= "\t";
                    }
                    $productNameStr .= $sku['spec'] . ' x' . $sku['count'] . "件\n";
                }
            } else {
                if ($pro[0]['spec']) {
                    $productNameStr .= ':' . $pro[0]['spec'];
                }
                $productNameStr .= ' x' . $pro[0]['count'] . "件\n";
            }
        }
        if (mb_strlen($productNameStr) > 255) {
            $productNameStr = '订单号：' . $order['id'] . "\n" . "数量 x" . $proCount . '件';
        }
        $orderData = [
            'receiver' => [
                'mobile' => $order['receiver_tel'],
                'phone' => $order['receiver_tel'],
                'name' => $order['receiver_name'],
                'addr' => $address['address_text'],
                'country' => $address['country_text'],
            ],
            'sender' => [
                'mobile' => $settingModel['sender_tel'],
                'phone' => $settingModel['sender_tel'],
                'name' => $settingModel['sender_name'],
                'addr' => $settingModel['address_text'],
            ],
            'order_number' => $order['id'],
            'cargo_name' => $productNameStr,
            'goods_list' => $itemsData,
            'cargo_count' => $proCount,
            'weight' => 0,
        ];
        return $orderData;
    }

    /**
     * 获取订单地址
     * @param $order
     * @return array
     */
    public function getOrderAddressText($order)
    {
        $snapshot = json_decode($order['snapshot'], true);
        $address = [];
        if ($snapshot['address']) {
            $country = $snapshot['address']['country'] == 'CN' ? '中国' : '国外';
            $districtIds = [$snapshot['address']['prov'], $snapshot['address']['city'], $snapshot['address']['area']];
            $district = DB::table('tbl_district')
                ->whereIn('id', $districtIds)
                ->pluck('name', 'id');
            $addressText = $district[$snapshot['address']['prov']] .
                $district[$snapshot['address']['city']] .
                $district[$snapshot['address']['area']] . ' ' .
                $snapshot['address']['address'];
            $address = [
                'country_text' => $country,
                'address_text' => $addressText
            ];
        }
        return $address;
    }

    /**
     * 导入订单到快递接口
     * @param $order
     * @param $orderId
     * @throws \Exception
     */
    public function orderSend($order, $orderId)
    {
        $orderData = self::createOrderData($order, $order['items']);
        $orderData = json_encode($orderData, JSON_UNESCAPED_UNICODE);
        $settingModel = $this->expressSetting->getModel();
        $data = array(
            "app_key" => $settingModel->app_key,
            "access_token"=> $settingModel->access_token,
            'channel_source' => ExpressConstants::ExpressParam_ChannelSource,
            "data" => $orderData,
            "timestamp" => time()
        );
        $sign = $this->expressSetting->generateSign($data);
        $data['sign'] = $sign;
        $res = ExpressHelper::httpsRequest("/v7/open/api/send", $data);
        $resArr = json_decode($res, true);
        if ($resArr['status'] == 200) {
            OrderModel::query()
                ->where('id', $orderId)
                ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_SyncSuccessed]);
            Log::writeLog('expressSendOrder', "Success siteId:" . $settingModel->site_id . ' orderId:' . $orderId);
        } else {
            OrderModel::query()
                ->where('id', $orderId)
                ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_SyncFail]);
            Log::writeLog('expressSendOrder', "Fail siteId:" . $settingModel->site_id .
                ' orderId:' . $orderId .
                ' errorCode:' . $resArr['status'] .
                ' errorMsg:' . $resArr['message']
            );
        }
    }

    /**
     * 更新订单信息
     * @param $order
     * @param $orderId
     * @throws \Exception
     */
    public function orderUpdate($order, $orderId) {
        $orderData = self::createOrderData($order, $order['items']);
        $orderData = json_encode($orderData, JSON_UNESCAPED_UNICODE);
        $settingModel = $this->expressSetting->getModel();
        $data = array(
            "app_key" => $settingModel->app_key,
            "access_token"=> $settingModel->access_token,
            "data" => $orderData,
            "timestamp" => time()
        );
        $sign = $this->expressSetting->generateSign($data);
        $data['sign'] = $sign;
        $res = ExpressHelper::httpsRequest("/v7/open/api/updateSend", $data);
        $resArr = json_decode($res, true);
        dump($resArr);
        if ($resArr['status'] == 200) {
            OrderModel::query()
                ->where('id', $orderId)
                ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_SyncSuccessed]);
            Log::writeLog('expressUpdateOrder', "Success siteId:" . $settingModel->site_id . ' orderId:' . $orderId);
        } else {
            OrderModel::query()
                ->where('id', $orderId)
                ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_UpdateFail]);
            Log::writeLog('expressUpdateOrder', "Fail siteId:" . $settingModel->site_id .
                ' orderId:' . $orderId .
                ' errorCode:' . $resArr['status'] .
                ' errorMsg:' . $resArr['message']
            );
        }
    }

    /**
     * 取消订单
     * @param $orderData
     */
    public function orderCancel($orderData) {
        $orderIdsStr = $orderData['order_list'];
        $orderIdsArr = explode(',', $orderData['order_list']);
        $orderData = json_encode($orderData, JSON_UNESCAPED_UNICODE);
        $settingModel = $this->expressSetting->getModel();
        $data = array(
            "app_key" => $settingModel->app_key,
            "access_token"=> $settingModel->access_token,
            "data" => $orderData,
            "timestamp" => time()
        );
        $sign = $this->expressSetting->generateSign($data);
        $data['sign'] = $sign;
        $res = ExpressHelper::httpsRequest("/v7/open/api/cancel", $data);
        $resArr = json_decode($res, true);
        if ($resArr['status'] == 200) {
            OrderModel::query()
                ->whereIn('id', $orderIdsArr)
                ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_CancelSuccessed]);
            Log::writeLog('expressCancelOrder', "Success siteId:" . $settingModel->site_id . ' orderIds:' . $orderIdsStr);
        } else {
            OrderModel::query()
                ->whereIn('id', $orderIdsArr)
                ->update(['express_sync_status' => ExpressConstants::OrderSynStatus_CancelFail]);
            Log::writeLog('expressCancelOrder', "Fail siteId:" . $settingModel->site_id .
                ' orderId:' . $orderIdsStr .
                ' errorCode:' . $resArr['status'] .
                ' errorMsg:' . $resArr['message']
            );
        }
    }
}