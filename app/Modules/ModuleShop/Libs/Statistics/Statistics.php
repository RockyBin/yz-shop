<?php

namespace App\Modules\ModuleShop\Libs\Statistics;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Statistics\MemberStatistics\MemberStatistics;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;

/**
 *
 * @author Administrator
 */
class Statistics
{
    private $_OrderModel;

    /**
     * param 订单模型 （可以是ID或者订单模型）
     * @return string
     */
    function __construct($Order)
    {
        if($Order){
            if(is_string($Order)){
                $this->_OrderModel=OrderModel::query()->where('id',$Order)->first();
            }else{
                if($Order instanceof  OrderModel){
                    $this->_OrderModel = $Order;
                }
            }
        }
    }

    /**
     * 执行会员数据相关统计
     * param $AfterSaleModel 若此参数有传的话，说明是走了退款流程
     * param $type 需要计算的type 详细请看Constants,若要指定类型就传值，否则按照需求走
     * @return
     */
    public function calcMemberStatistics(AfterSaleModel $AfterSaleModel=null, $type = [])
    {
        if (!$type) $type = [Constants::Statistics_member_tradeMoney, Constants::Statistics_member_tradeTime];
        MemberStatistics::Statistics($this->_OrderModel, $AfterSaleModel, $type);
    }

    /**
     * 执行分销商数据相关统计
     * @return string
     */
    function calcDistributorStatistics()
    {

    }

    /**
     * 执行代理数据相关统计
     * @return string
     */
    function calcAgentStatistics()
    {

    }
}
