<?php

namespace App\Modules\ModuleShop\Libs\Dealer;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\DealerWithdrawConfigModel;

/**
 * 提现设置类
 * Class WithdrawConfig
 * @package App\Modules\ModuleShop\Libs\WithdrawConfig
 */
class DealerWithdrawConfig
{
    /**
     * 添加设置
     * @param array $info，设置信息，对应 WithdrawConfigModel 的字段信息
     */
    public function add()
    {
        $model = new DealerWithdrawConfigModel();
        $model->online_type = '{"wxpay":0,"alipay":0}';
        $model->offline_type = '{"wx_qrcode":0,"alipay_qrcode":0,"alipay_account":0,"bank_account":0}';
        $model->platform_type = '{"balance":1}';
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
        $model = new DealerWithdrawConfigModel();
        $model->fill($info);
        $model->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update($info);
    }

    /**
     * 查找指定网站的提现设置
     */
    private function findInfo()
    {
        return DealerWithdrawConfigModel::where(['site_id' => Site::getCurrentSite()->getSiteId()])->first();
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
     * 检测是否可以提现
     * @param $config 配置信息
     * @return array|bool|mixed
     */
    public function checkWithdrawDate($config)
    {
//        $config = DealerWithdrawConfigModel::where(['site_id' => Site::getCurrentSite()->getSiteId()])->first();
        if ($config) {
            // 查找不到提现时间设置 认为是默认的任意时间
            $originData = $withdrawDate = $config->withdraw_date ? json_decode($config->withdraw_date, true) : ['date' => 0];
            // 任意时间
            if ($withdrawDate['date'] == 0) {
                return true;
            }
            // 特定时间
            // 每周
            if ($withdrawDate['date_type'] == 0) {
                $day = date('w'); // 当前星期几 周日为0
            } else {
                // 每月
                $day = date('j'); // 当前是这个月的第几天
                // 如果有选择每月最后一天 把当前月的最后一天插入到最后
                if (in_array(-1, $withdrawDate['date_days'])) {
                    array_push($withdrawDate['date_days'], date('t'));
                }
            }
            if (in_array($day, $withdrawDate['date_days'])) {
                return true;
            } else {
                return ['withdraw_date' => $originData];
            }
        } else {
            // 查找不到 认为是任意时间
            return true;
        }
    }

    /**
     * 兼容旧的提现设置
     */
    public function updateWithdrawConfig(){
      $allData=  DealerWithdrawConfigModel::get();
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
        (new DealerWithdrawConfigModel())->updateBatch($updateData);
    }
}