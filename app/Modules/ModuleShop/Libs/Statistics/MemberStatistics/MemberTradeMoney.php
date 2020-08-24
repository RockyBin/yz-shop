<?php

namespace App\Modules\ModuleShop\Libs\Statistics\MemberStatistics;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Statistics\StatisticsInterface;
use App\Modules\ModuleShop\Libs\Model\StatisticsModel;
use \YZ\Core\Site\Site;

class MemberTradeMoney implements StatisticsInterface
{
    private $_OrderModel;
    private $_StatisticsModel;
    private $time;
    private $siteId;
    private $_AfterSaleModel; //是否是售后操作

    function __construct($_OrderModel, $AfterSaleModel, $type)
    {
        $this->_OrderModel = $_OrderModel;
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->_AfterSaleModel = $AfterSaleModel;
        $this->setTime();
        $this->getModel($type);

    }

    public function setTime()
    {
        $this->time = 0;
    }

    public function getModel($type)
    {
        if (!$type) {
            throw new \Exception("Type不能为空");
        }
        if ($this->_OrderModel && !($this->_OrderModel instanceof OrderModel) && !$this->_OrderModel->member_id) {
            throw new \Exception("不合法OrderModel");
        }
        $member_id = $this->_OrderModel->member_id;
        if(!$member_id) $member_id = $this->_AfterSaleModel->member_id;
        $model = new StatisticsModel();
        $statistics = $model
            ->where(['type' => $type, 'member_id' => $member_id, 'site_id' => $this->siteId, 'time' => $this->time])
            ->first();
        if ($statistics) {
            $this->_StatisticsModel = $statistics;
        } else {
            $data['site_id'] = $this->siteId;
            $data['type'] = $type;
            $data['member_id'] = $member_id;
            $data['time'] = $this->time;
            $model->fill($data);
            $model->save();
            $this->_StatisticsModel = $model::query()->where(['id' => $model->id])->first();
        }
    }

    public function calc()
    {
        // 如果_AfterSaleModel这个模型存在证明，是退款操作，需要减去退款金额
        if ($this->_AfterSaleModel) {
            if ($this->_AfterSaleModel->status == Constants::RefundStatus_Over) {
                $value = $this->_StatisticsModel->value - $this->_AfterSaleModel->real_money;
            }
        } else {
            $value = $this->_OrderModel->money + $this->_StatisticsModel->value;
        }

        return intval($value);
    }

    public function save()
    {
        $this->_StatisticsModel->value = $this->calc();
        $this->_StatisticsModel->save();
    }
}