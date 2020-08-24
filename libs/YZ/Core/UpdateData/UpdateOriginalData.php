<?php
//phpcodelock
/**
 * 用来更新升级系统后的旧数据
 * User: liyaohui
 * Date: 2019/9/30
 * Time: 16:38
 */

namespace YZ\Core\UpdateData;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Model\AgentLevelModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Product\ProductPriceRule;
use App\Modules\ModuleShop\Libs\Statistics\MemberStatistics\MemberCloudStockPerformance;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\SiteModel;
use YZ\Core\Site\Site;

class UpdateOriginalData
{
    /**
     * 用来更新代理升级的5 6条件数据
     * @return int
     * @throws \Exception
     */
    public static function updateAgentConditionV23()
    {
        $siteIdList = self::getSiteIdList();
        $updateCount = 0;
        foreach ($siteIdList as $site) {
            // 获取该站点所有分销等级
            $disLevels = DistributionLevelModel::query()
                ->where('site_id', $site)
                ->pluck('id')->implode(',');
            if (!$disLevels) {
                continue;
            }
            // 获取所有代理等级的升级条件
            $agentLevelList = AgentLevelModel::query()
                ->where('site_id', $site)
                ->select(['id', 'upgrade_condition'])
                ->get();
            if ($agentLevelList->count() == 0) {
                continue;
            }
            $updateData = [];
            foreach ($agentLevelList as $agent) {
                $update = false;
                if ($agent->upgrade_condition) {
                    $condition = json_decode($agent->upgrade_condition, true);
                    if (isset($condition['upgrade'])) {
                        $has3AgentNum = false;
                        foreach ($condition['upgrade'] as &$item) {
                            if (
                                ($item['type'] == Constants::AgentLevelUpgradeCondition_DirectlyDistributionMember
                                    || $item['type'] == Constants::AgentLevelUpgradeCondition_IndirectDistributionMember)
                                && !is_array($item['value'])
                            ) {
                                $item['value'] = [
                                    'distribution_level_id' => $disLevels,
                                    'member_count' => $item['value']
                                ];
                                $updateCount++;
                                $update = true;
                            } elseif ($item['type'] == Constants::AgentLevelUpgradeCondition_RecommendThreeLevelAgentNum) {
                                $has3AgentNum = true;
                            }
                        }
                        // 新加的三个条件 也要加上
                        if (count($condition['upgrade']) < 18) {
                            $condition['upgrade'][] = [
                                'type' => Constants::AgentLevelUpgradeCondition_RecommendOneLevelAgentNum,
                                'value' => null,
                                'logistic' => 'or'
                            ];
                            $condition['upgrade'][] = [
                                'type' => Constants::AgentLevelUpgradeCondition_RecommendTwoLevelAgentNum,
                                'value' => null,
                                'logistic' => 'or'
                            ];
                            $condition['upgrade'][] = [
                                'type' => Constants::AgentLevelUpgradeCondition_SelfBuyAllDesignatedProduct,
                                'value' => 0,
                                'logistic' => 'or'
                            ];
                            $updateCount++;
                            $update = true;
                        }
                        // 没有直推三级代理的条件 要加上
                        if (!$has3AgentNum) {
                            $condition['upgrade'][] = [
                                'type' => Constants::AgentLevelUpgradeCondition_RecommendThreeLevelAgentNum,
                                'value' => null,
                                'logistic' => 'or'
                            ];
                            $updateCount++;
                            $update = true;
                        }
                    }
                }
                if ($update) {
                    $updateData[] = [
                        'id' => $agent->id,
                        'upgrade_condition' => json_encode($condition)
                    ];
                }
            }
            if ($updateData) {
                (new AgentLevelModel())->updateBatch($updateData);
            }
        }
        return $updateCount;
    }

    /**
     * 更新2.5版本的分销商等级数据
     * @return int
     * @throws \Exception
     */
    public static function updateDistributionConditionV25()
    {
        $siteIdList = self::getSiteIdList();
        $updateCount = 0; // 更新数据条数
        foreach ($siteIdList as $siteId) {
            // 获取站点的分销等级
            $levelListObj = DistributionLevelModel::query()
                ->where('site_id', $siteId)
                ->select(['condition', 'id', 'status'])
                ->get();
            if (!$levelListObj->count()) continue;
            $levelIds = $levelListObj->where('status', 1)->pluck('id')->toArray();
            $levelIds = implode(',', $levelIds);
            $levelList = $levelListObj->toArray();
            $updateData = [];
            foreach ($levelList as $level) {
                if ($level['condition']) {
                    $condArr = json_decode($level['condition'], true);
//                    $needUpdate = false;
//                    if (!$condArr['upgrade']) continue;
//                    foreach ($condArr['upgrade'] as &$con) {
//                        if (in_array($con['type'], [9,11,20]) && !isset($con['value']['distribution_level_id'])) {
//                            $con['value'] = [
//                                'member_count' => $con['value'],
//                                'distribution_level_id' => $levelIds
//                            ];
//                            $needUpdate = true;
//                        }
//                    }
//                    if ($needUpdate) {
//                        $updateData[] = [
//                            'id' => $level['id'],
//                            'condition' => json_encode($condArr)
//                        ];
//                        $updateCount++;
//                    }
                    // 没有upgrade说明是旧的数据 需要刷新一下
                    if ($condArr[0] && !array_key_exists('upgrade', $condArr)) {
                        $originCond = $condArr[0];
                        $originCond['logistic'] = 'or';
                        // 需要分销商等级的 特殊处理
                        if (in_array($originCond['type'], [9, 11, 20])) {
                            $originCond['value'] = [
                                'member_count' => $originCond['value'],
                                'distribution_level_id' => $levelIds
                            ];
                        }
                        $newData = [
                            'product_id' => [],
                            'upgrade' => [$originCond]
                        ];
                        $updateData[] = [
                            'id' => $level['id'],
                            'condition' => json_encode($newData)
                        ];
                        $updateCount++;
                    }
                }
            }
            if ($updateData) {
                (new DistributionLevelModel())->updateBatch($updateData);
            }
        }
        return $updateCount;
    }

    /**
     * 更新经销商的业绩统计数据
     */
    public static function updateDealerPerformance(){
        $list = CloudStockPurchaseOrderModel::query()->get();
        foreach ($list as $item){
            Site::initSiteForCli($item->site_id);
            if($item->payment_status == 1){
                echo "process $item->id payed\r\n";
                (new MemberCloudStockPerformance($item, Constants::Statistics_MemberCloudStockPerformancePaid))->calc();
            }
            if($item->status == 3){
                echo "process $item->id finished\r\n";
                (new MemberCloudStockPerformance($item, Constants::Statistics_MemberCloudStockPerformanceFinished))->calc();
            }
        }
    }

    /**
     * 更新会员等级设置
     */
    public static function updateMemberLevel(){
        //处理默认等级
        //查找权重为0，并且discount < 10的，将这个站下面的等级权重都加1，然后添加一个默认等级，然后再将默认等级的升级条件清空
        $levels = MemberLevelModel::query()->where('weight','=',0)->get();
        foreach ($levels as $l){
            if($l->discount < 10){
                MemberLevelModel::query()->where('site_id','=',$l->site_id)->increment('weight');
                $nl = new MemberLevelModel();
                $nl->fill([
                    'site_id' => $l->site_id,
                    'weight' => 0,
                    'name' => '默认等级',
                    'discount' => '100',
                    'for_newmember' => '1',
                    'status' => '1',
                    'condition' => json_encode([])
                ]);
                $nl->save();
            }else {
                $l->upgrade_value = 0;
                $l->save();
            }
        }

        //将discount改为百分比形式
        $levels = MemberLevelModel::query()->get();
        foreach ($levels as $l){
            if($l->discount > 10 && $l->discount <= 100) continue; //在这个范围内认为已经处理过，不再处理
            if($l->discount > 100) $l->discount = $l->discount / 10;
            else $l->discount = $l->discount * 10;
            $l->save();
        }

        //处理升级条件
        //将upgrade_type和upgrade_value转为新的格式
        $levels = MemberLevelModel::query()->where('upgrade_value','>',0)->get();
        foreach ($levels as $l){
            $l->condition = '[{"type":0,"value":'.$l->upgrade_value.',"logistic":"or"}]';
            $l->save();
        }

        //处理单品会员价
        $rules = ProductPriceRuleModel::query()->where('type',1)->where('site_id',1)->get();
        foreach ($rules as $r) {
            $arr = json_decode($r->rule_info,true);
            if (is_array($arr)) {
                if ($arr['rule'] && $arr['amountType'] == 0) {
                    foreach ($arr['rule'] as &$rr) {
                        if($rr['discount'] > 10 && $rr['discount'] <= 100) continue; //在这个范围内认为已经处理过，不再处理
                        if($rr['discount'] > 100) $rr['discount'] = $rr['discount'] / 10;
                        else $rr['discount'] = $rr['discount'] * 10;
                    }
                    unset($rr);
                    $r->rule_info = json_encode($arr);
                    $r->save();
                } elseif(!$arr['rule']) {
                    foreach ($arr as &$rr) {
                        if($rr['discount'] > 10 && $rr['discount'] <= 100) continue;
                        if($rr['discount'] > 100) $rr['discount'] = $rr['discount'] / 10;
                        else $rr['discount'] = $rr['discount'] * 10;
                    }
                    unset($rr);
                    $r->rule_info = json_encode($arr);
                    $r->save();
                }
            }
        }

        echo "finished";
    }

    public static function setDefaultMemberLevel(){
        $sites = SiteModel::query()->get();
        foreach ($sites as $site){
            $siteId = $site->site_id;
            $memberLevel = MemberLevelModel::query()->where(['site_id' => $siteId, 'weight' => 0])->first();
            if (!$memberLevel) {
                $memberLevel = new MemberLevelModel();
                $memberLevel->fill([
                    'weight' => 0,
                    'name' => '默认等级',
                    'discount' => '100',
                    'for_newmember' => '1',
                    'status' => '1',
                    'site_id' => $siteId,
                    'condition' => json_encode([])
                ]);
                $memberLevel->save();
            }
            MemberModel::query()->where(['site_id' => $siteId, 'level' => 0])->update(['level' => $memberLevel->id]);
        }
    }

    /**
     * @return array
     */
    public static function getSiteIdList()
    {
        return SiteModel::query()->pluck('site_id')->toArray();
    }
}