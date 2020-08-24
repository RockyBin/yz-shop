<?php

namespace App\Modules\ModuleShop\Libs\Statistics;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\StatisticsModel;
use YZ\Core\Site\Site;

/**
 *
 * @author Administrator
 */
class GetStatistics
{

    private $member_id;

    function __construct($member_id)
    {
        $this->member_id = $member_id;
    }

    /**
     * 查询此会员相关统计
     * $type
     * @return
     */
    public function getMemberStatistics($type = [])
    {
        if (!$type) $type = [Constants::Statistics_member_tradeMoney, Constants::Statistics_member_tradeTime];
        //这里的time，后期如果有需要的话，可以进行改进，因为现在具体需求还没有
        return  StatisticsModel::query()->where(['member_id'=>$this->member_id,'site_id'=>Site::getCurrentSite()->getSiteId(),'time'=>0])->whereIn('type',$type)->get();

    }

    /**
     * 查询此分销商数据相关统计
     * @return string
     */
    function getDistributorStatistics()
    {

    }

    /**
     * 查询此代理数据相关统计
     * @return string
     */
    function calcAgentStatistics()
    {

    }
}
