<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig;

use App\Modules\ModuleShop\Libs\CloudStock\CloudStockWithdrawConfig;
use App\Modules\ModuleShop\Libs\Dealer\DealerWithdrawConfig;
use YZ\Core\Constants;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\WithdrawConfigModel;

/**
 * 提现设置类
 * Class WithdrawConfig
 * @package App\Modules\ModuleShop\Libs\WithdrawConfig
 */
class WithdrawConfig
{

    /**
     * 添加设置
     * @param array $info，设置信息，对应 WithdrawConfigModel 的字段信息
     */
    public function add()
    {
        $model = new WithdrawConfigModel();
        $model->balance_type = '{"wxpay":0,"alipay":0,"wx_qrcode":0,"alipay_qrcode":0,"alipay_account":0,"bank_account":0}';
        $model->commission_type = '{"balance":1,"wxpay":0,"alipay":0,"wx_qrcode":0,"alipay_qrcode":0,"alipay_account":0,"bank_account":0}';
        $model->withdraw_date = '{"date":0}'; // 提现时间 默认任意时间
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->save();
    }

    /**
     * 编辑设置
     * @param array $info，设置信息，对应 WithdrawConfigModel 的字段信息
     */
    public function edit(array $info)
    {
        $model = new WithdrawConfigModel();
        $model->fill($info);
        $model->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update($info);
    }

    /**
     * 查找指定网站的提现设置
     */
    private function findInfo()
    {
        return WithdrawConfigModel::where(['site_id' => Site::getCurrentSite()->getSiteId()])->first();
    }

    /**
     * 获取指定网站的提现设置
     * @return mixed
     */
    public function getInfo($type = 0, $transform = false)
    {
        // 如果是云仓的 返回云仓的提现设置信息
        if ($type == Constants::FinanceType_CloudStock) {
            $config = (new DealerWithdrawConfig())->getInfo();
            // 和其他提现配置统一
            $config->balance_type = "{}";
            $config->commission_type = array_merge(
                json_decode($config->online_type, true),
                json_decode($config->offline_type, true),
                json_decode($config->platform_type, true)
            );
            return $config;
        }
        $data = $this->findInfo();
        if (!$data) {
            $this->add();
        }
        $data = $this->findInfo();
        if ($data && $transform) {
            if ($data['balance_type']) {
                $data['balance_type'] = json_decode($data['balance_type'], true);
            } else {
                $data['balance_type'] = [];
            }
            if ($data['commission_type']) {
                $data['commission_type'] = json_decode($data['commission_type'], true);
            } else {
                $data['commission_type'] = [];
            }
        }
        return $data;
    }

    /**
     * 兼容旧的提现设置
     */
    public function updateWithdrawConfig()
    {
        $allData = WithdrawConfigModel::get();
        $updateData = [];
        $arr = ['wx_qrcode' => 0, "alipay_qrcode" => 0, 'alipay_account' => 0, 'bank_account' => 0];
        foreach ($allData as &$v) {
            $balance_type = json_decode($v->balance_type, true);
            $commission_type = json_decode($v->commission_type, true);
            $merge = array_merge($balance_type, $arr);
            $commission_merge = array_merge($commission_type, $arr);
            $v->balance_type = json_encode($merge);
            $v->commission_type = json_encode($commission_merge);
            $updateData[] = ['site_id' => $v->site_id, 'balance_type' => json_encode($merge), 'commission_type' => json_encode($commission_merge)];
        }
        (new WithdrawConfigModel())->updateBatch($updateData);
    }
}