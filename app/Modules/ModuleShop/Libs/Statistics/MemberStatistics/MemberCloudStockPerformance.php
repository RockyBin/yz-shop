<?php

namespace App\Modules\ModuleShop\Libs\Statistics\MemberStatistics;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\UniqueLogModel;
use App\Modules\ModuleShop\Libs\Statistics\StatisticsInterface;
use App\Modules\ModuleShop\Libs\Model\StatisticsModel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use \YZ\Core\Site\Site;

class MemberCloudStockPerformance implements StatisticsInterface
{
    private $orderModel;
    private $siteId;
    private $type;

    /**
     * MemberCloudStockPerformance constructor.
     * @param $orderIdOrModel 进货单ID或数据模型
     * @param $type 类型，是付款后数据还是订单完成后的数据
     */
    function __construct($orderIdOrModel, $type)
    {
        if(is_numeric($orderIdOrModel)) $this->orderModel = CloudStockPurchaseOrderModel::find($orderIdOrModel);
        else $this->orderModel = $orderIdOrModel;
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->type = $type;
    }

    public function setTime()
    {
        $this->time = 0;
    }

    public function getModel($type)
    {

    }

    public function calc()
    {
        $key = 'MemberCloudStockPerformance_'.$this->type.'_'.$this->orderModel->id;
        if(!UniqueLogModel::newLog($key)){ //如果已经执行过，就不再执行
            Log::writeLog('MemberCloudStockPerformance',$key.' 之前已经执行过了');
            return;
        }
        $time = strtotime($this->orderModel->created_at);
        if($this->orderModel->pay_at) $time = strtotime($this->orderModel->pay_at);
        if($this->orderModel->finished_at && $this->type == Constants::Statistics_MemberCloudStockPerformanceFinished ) $time = strtotime($this->orderModel->finished_at);
        // 当记录季度业绩时，时间标志为该季度第一个月的1号
        $m = intval(date('m',$time));
        if(in_array($m,[1,2,3])) $qm = '01';
        if(in_array($m,[4,5,6])) $qm = '02';
        if(in_array($m,[7,8,9])) $qm = '03';
        if(in_array($m,[10,11,12])) $qm = '04';
        $quarter = date('Y',$time).$qm;
        //分别是月业绩、季业绩、年业绩、总业绩的时间标志，月业绩的时间格式是yyyyMM，季度业绩的时候格式是 yyyy(01|02|03|04) 01|02|03|04分别表示第几季度，年的业绩是 yyyy，总业绩的时间格式是0
        $times = [date('Ym',$time),$quarter,date('Y',$time),'0'];
        // 获取当前经销商上级
//        $dealerParentId = MemberModel::query()->where('site_id', $this->siteId)
//            ->where('id', $this->orderModel->member_id)
//            ->value('dealer_parent_id');
        // 这里不能拿当前的上级，因为在创建订单的时候，已经确定了配仓人
        $dealerParentId =  CloudStockModel::query()->where('site_id', $this->siteId)
                ->where('id', $this->orderModel->cloudstock_id)
                ->value('member_id');
        $parents = [-1, $dealerParentId];
        foreach ($parents as $p) {
            foreach ($times as $index => $item){
                //分析数据统计类型
                if($this->type == Constants::Statistics_MemberCloudStockPerformanceFinished){
                    if($index === 0) $subType = Constants::Statistics_MemberCloudStockPerformanceFinishedMonth;
                    if($index === 1) $subType = Constants::Statistics_MemberCloudStockPerformanceFinishedQuarter;
                    if($index === 2) $subType = Constants::Statistics_MemberCloudStockPerformanceFinishedYear;
                    if($index === 3) $subType = Constants::Statistics_MemberCloudStockPerformanceFinished;
                }
                if($this->type == Constants::Statistics_MemberCloudStockPerformancePaid){
                    if($index === 0) $subType = Constants::Statistics_MemberCloudStockPerformancePaidMonth;
                    if($index === 1) $subType = Constants::Statistics_MemberCloudStockPerformancePaidQuarter;
                    if($index === 2) $subType = Constants::Statistics_MemberCloudStockPerformancePaidYear;
                    if($index === 3) $subType = Constants::Statistics_MemberCloudStockPerformancePaid;
                }
                //更新数据
                $model = StatisticsModel::query()->where([
                    'member_id' => $this->orderModel->member_id,
                    'type' => $subType,
                    'dealer_parent_id' => $p,
                    'time' => $item
                ])->first();
                if($model){
                    $model->value += $this->orderModel->total_money;
                    $model->updated_at = date('Y-m-d H:i:s');
                }else{
                    $model = new StatisticsModel();
                    $model->member_id = $this->orderModel->member_id;
                    $model->dealer_parent_id = $p;
                    $model->site_id = $this->siteId;
                    $model->type = $subType;
                    $model->time = $item;
                    $model->value = $this->orderModel->total_money;
                    $model->created_at = date('Y-m-d H:i:s');
                    $model->updated_at = date('Y-m-d H:i:s');
                }
                $model->save();
            }
        }
    }

    public function save()
    {

    }
}