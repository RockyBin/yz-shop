<?php

namespace App\Modules\ModuleShop\Libs\CloudStock;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\CloudStockWithdrawConfigModel;

/**
 * 提现设置类
 * Class WithdrawConfig
 * @package App\Modules\ModuleShop\Libs\WithdrawConfig
 */
class CloudStockWithdrawConfig
{
    /**
     * 添加设置
     * @param array $info，设置信息，对应 WithdrawConfigModel 的字段信息
     */
    public function add()
    {
        $model = new CloudStockWithdrawConfigModel();
        $model->online_type = '{"wxpay":0,"alipay":0}';
        $model->offline_type = '{"wx_qrcode":0,"alipay_qrcode":0,"alipay_account":0,"bank_account":0}';
        $model->platform_type = '{"balance":1}';
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->save();
    }

    /**
     * 编辑设置
     * @param array $info，设置信息，对应 WithdrawConfigModel 的字段信息
     */
    public function edit(array $info)
    {
        $model = new CloudStockWithdrawConfigModel();
        $model->fill($info);
        $model->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update($info);
    }

    /**
     * 查找指定网站的提现设置
     */
    private function findInfo()
    {
        return CloudStockWithdrawConfigModel::where(['site_id' => Site::getCurrentSite()->getSiteId()])->first();
    }

    /**
     * 获取指定网站的提现设置
     * @return mixed
     */
    public function getInfo()
    {
        $data = $this->findInfo();
        if (!$data) {
            $this->add();
        }
        return $this->findInfo();
    }
    /**
     * 兼容旧的提现设置
     */
    public function updateWithdrawConfig(){
      $allData=  CloudStockWithdrawConfigModel::get();
      $updateData=[];
      $arr=['wx_qrcode'=>0,"alipay_qrcode"=>0,'alipay_account'=>0,'bank_account'=>0];
      foreach ($allData as &$v){
        $balance_type=  json_decode( $v->balance_type,true);
        $commission_type= json_decode($v->commission_type,true);
        $merge=array_merge($balance_type,$arr);
        $commission_merge=array_merge($commission_type,$arr);
        $v->balance_type=json_encode($merge);
        $v->commission_type=json_encode($commission_merge);
        $updateData[]=['site_id'=>$v->site_id,'balance_type'=>json_encode($merge),'commission_type'=>json_encode($commission_merge)];
      }
        (new CloudStockWithdrawConfigModel())->updateBatch($updateData);
    }
}