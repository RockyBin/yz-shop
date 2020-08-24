<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Jobs\ResetAgentParentsJob;
use App\Modules\ModuleShop\Libs\Crm\Member;
use App\Modules\ModuleShop\Libs\Model\AgentParentsModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\BaseModel;
use YZ\Core\Task\TaskHelper;

/**
 * 团队代理的工具类
 * Class Agent
 * @package App\Modules\ModuleShop\Libs\Agent
 */
class AgentHelper
{
    /**
     * 获取某会员的上级代理列表，主要用在分佣时
     *
     * @param int $memberId 会员的ID
     * @param int $includeSelf 当 $memberId 也是代理时，在返回的代理列表里，是否包含 $memberId 本身
     * @return array [
     * 'normal' => [],//普通上级代理
     * 'samelevel' => [], //平级代理
     * 'lowlevel' =>[], //越级代理
     * ]
     */
    public static function getParentAgents($memberId, $includeSelf = 1)
    {
        $setting = (new AgentBaseSetting())->getSettingModel();
        $maxLevel = $setting->level;
        if ($maxLevel < 1) return [];
        $sql = "select * from tbl_member where id in (select parent_id from tbl_member_parents where member_id = :memberId) or id = :memberId2";
        $list = BaseModel::runSql($sql, [':memberId' => $memberId, ':memberId2' => $memberId]);
        $members = [];
        foreach ($list as $m) {
            $members[$m->id] = (array)$m;
        }
        $parentlist = MemberParentsModel::where('member_id', $memberId)->orderby('level', 'asc')->get();
        $normal = [];
        $samelevel = [];
        $lowlevel = [];
        $list = [];
        if (
            $includeSelf
            && $members[$memberId]['agent_level'] > 0
            && $members[$memberId]['agent_level'] <= $maxLevel
        ) {
            $list[] = ['member_id' => $memberId, 'parent_id' => $memberId];
        }
        if ($parentlist) {
            $parentlist = $parentlist->toArray();
            $list = array_merge($list, $parentlist);
        }
        foreach ($list as $m) {
            $m = $members[$m['parent_id']];
            if ($m['agent_level'] > 0 && $m['agent_level'] <= $maxLevel) {
                if (count($normal) == 0) {
                    $normal[] = $m;
                } elseif ($normal[count($normal) - 1]['agent_level'] > $m['agent_level']) {
                    $normal[] = $m;
                }
            }
        }
        // 获取销售奖配置
        $setting = AgentSaleRewardSetting::getCurrentSiteSetting();
        // 分一个
        $num = 1;
        // 最多分3个
        if ($setting->commision_people_num == 1) {
            $num = 3;
        }
        $currNum = 0;// 当前的匹配个数
        // 只分给直推的
        if ($setting->commision_relations == 0) {
            // 因为normal里面的代理是每级的第一个 所以直接去查找对应的直属上级即可
            foreach ($normal as $item) {
                if ($currNum >= $num) break; // 超出数量 则不再查找
                $parent = $members[$item['invite1']];
                if (
                    $parent['agent_level'] == $item['agent_level']
                    && $parent['agent_level'] > 0
                    && $parent['agent_level'] <= $maxLevel
                ) {
                    $samelevel[] = $parent;
                    $currNum++;
                }

                if (
                    $parent['agent_level'] > $item['agent_level']
                    && $parent['agent_level'] > 0
                    && $parent['agent_level'] <= $maxLevel
                ) {
                    $lowlevel[] = $parent;
                    $currNum++;
                }
                if ($num == 1) break; // 直推的 只分一个 查找不到的话就不往下找了
            }
        } else {
            // 分给直推或间推
            foreach ($normal as $item) {
                if ($currNum >= $num) break; // 超出数量 则不再查找
                $parent = $members[$item['invite1']];
                $existIds = [];
                $currId = $item['invite1'];
                // 执行过的就不能再进入了 防止死循环
                while ($parent && !in_array($currId, $existIds)) {
                    $existIds[] = $currId;
                    if (
                        $parent['agent_level'] == $item['agent_level']
                        && $parent['agent_level'] > 0
                        && $parent['agent_level'] <= $maxLevel
                    ) {
                        $samelevel[] = $parent;
                        $currNum++;
                        break;
                    }

                    if (
                        $parent['agent_level'] > $item['agent_level']
                        && $parent['agent_level'] > 0
                        && $parent['agent_level'] <= $maxLevel
                    ) {
                        $lowlevel[] = $parent;
                        $currNum++;
                        break;
                    }
                    // 如果查找不到 接着往上查找
                    $parent = $members[$parent['invite1']];
                    $currId = $parent['invite1'];
                }
            }
        }

        return ['normal' => $normal, 'samelevel' => $samelevel, 'lowlevel' => $lowlevel];
    }

    /**
     * 重置团队关系队列任务
     * @param $memberId
     * @param int $orderId 在订单支付成功后再绑定上下级关系时需要，用来在绑定关系后分佣
     */
    public static function dispatchResetAgentParentsJob($memberId, $orderId = 0)
    {
        $memInfo = MemberModel::find($memberId);
        ResetAgentParentsJob::dispatch($memberId, TaskHelper::createChangeAgentParentTaskGroupId($memInfo->site_id), $orderId);
    }

    /**
     * 重新设置某会员下所有会员以及会员本身的团队关系
     * @param $rootMemberId
     * @throws \Exception
     */
    /**
     * 重新设置某会员下所有会员以及会员本身的团队关系
     * @param $rootMemberId
     * @throws \Exception
     */
    public static function resetAgentParentRelationTree($rootMemberId)
    {
        Log::writeLog('resetAgentParentRelationTree', 'member_id:' . $rootMemberId);
        $rootMemberId = intval($rootMemberId);
        if ($rootMemberId <= 0) return;
        try {
            DB::beginTransaction();
            $siteId = Site::getCurrentSite()->getSiteId();
            // 获取所有子节点
            $subMemberIds = MemberParentsModel::query()->where('site_id', $siteId)->where('parent_id', $rootMemberId)->select('member_id')->distinct()->get()->pluck('member_id')->toArray();
            $bottomMemberIds = []; // 底部节点数据
            if (count($subMemberIds) == 0) {
                // 如果没有子节点，本身就是底部节点
                $bottomMemberIds[] = $rootMemberId;
            } else {
                // 查找该节点的所有底部节点
                $sql = "select distinct(member_id) from tbl_member_parents where site_id = :site_id and parent_id = :parent_id having (select count(1) from tbl_member_parents as tmp where tmp.parent_id = tbl_member_parents.member_id) = 0";
                $bottomMemberList = BaseModel::runSql($sql, [':site_id' => $siteId, ':parent_id' => $rootMemberId]);
                foreach ($bottomMemberList as $bottomMemberItem) {
                    $bottomMemberIds[] = $bottomMemberItem->member_id;
                }
            }
            // 获取所有底部节点的路径并构造数据
            $memberParentsList = MemberParentsModel::query()->from('tbl_member_parents as mp')
                ->leftJoin('tbl_member as m', 'mp.parent_id', '=', 'm.id')
                ->where('mp.site_id', $siteId)
                ->whereIn('mp.member_id', $bottomMemberIds)
                ->orderBy('mp.member_id')
                ->orderBy('mp.level')
                ->select('mp.member_id as id', 'm.id as member_id', 'm.agent_level', 'mp.level')
                ->get();
            $memberRoadList = [];
            foreach ($memberParentsList as $memberParentsItem) {
                $memberRoadList[$memberParentsItem->id][] = $memberParentsItem;
            }
            // 如果只有自己且没有上级
            if (count($memberRoadList) == 0) {
                $memberRoadList[$rootMemberId] = [];
            }
            // 获取所有底部节点代理等级
            $bottomMemberAgentLevels = MemberModel::query()->where('site_id', $siteId)->whereIn('id', $bottomMemberIds)->select('id', 'agent_level')->get()->pluck('agent_level', 'id')->toArray();
            $processedMemberIds = []; // 已经处理过的会员id
            $insertDataList = []; // 要插入的数据
            $agentParentIdList = []; // 直属上级团队领导数据
            // !!! 强调：不建议在循环里操作数据库，否则会大大增大时间 !!!
            foreach ($memberRoadList as $id => $memberParentRoad) {
                // 把最底元素压进路径头部
                array_unshift($memberParentRoad, [
                    'member_id' => $id,
                    'agent_level' => intval($bottomMemberAgentLevels[$id]),
                    'level' => 0,
                ]);
                // 只有自己就不处理了
                if (count($memberParentRoad) <= 1) {
                    $agentParentIdList[$id] = 0;
                    continue;
                }
                self::buildAgentParentByRoad($memberParentRoad, $processedMemberIds, $insertDataList, $agentParentIdList, $rootMemberId);
            }
            // 处理直属上级领导id
            $updateDataList = [];
            if (count($agentParentIdList) > 0) {
                foreach ($agentParentIdList as $tmpMemberId => $tmpAgentParentId) {
                    $updateDataList[] = [
                        'id' => $tmpMemberId,
                        'agent_parent_id' => $tmpAgentParentId,
                    ];
                }
            }
            // 清理旧数据，放到插入数据之前，尽量避免多线程时因时间差带来的数据错乱
            AgentParentsModel::query()->where('site_id', $siteId)
                ->whereIn('member_id', array_merge($subMemberIds, [$rootMemberId]))
                ->delete();
            // 批量插入数据
            if (count($insertDataList) > 0) {
                DB::table('tbl_agent_parents')->insert($insertDataList);
            }
            // 批量更新直属上级领导id
            if (count($updateDataList) > 0) {
                (new MemberModel())->updateBatch($updateDataList, 'id');
            }
            DB::commit();
        } catch (\Exception $e) {
            Log::writeLog('ResetAgentParentRelationTree_Error', $e->getMessage());
            DB::rollBack();
        }

    }

    /**
     * 根据会员的父亲路径重新建立整条路里所有会员的团队关系
     * !!! 此方法因为会循环调用，因此请不要执行数据库操作 !!!
     * 该方法没有清理旧数据，请调用前清理旧数据
     * @param array $road
     * @param array $processedMemberIds 已经处理的会员ID，避免重复处理
     * @param array $insertDataList 新的关系数据
     * @param array $agentParentIdList 新的关系数据
     * @param int $topMemberId 如果有设，去到该节点则会停止往上计算
     */
    private static function buildAgentParentByRoad(array $road, array &$processedMemberIds, array &$insertDataList, array &$agentParentIdList, $topMemberId = 0)
    {
        $roadLength = count($road);
        $siteId = Site::getCurrentSite()->getSiteId();
        foreach ($road as $index => $roadItem) {
            $memberId = intval($roadItem['member_id']);
            if (!$memberId || in_array($memberId, $processedMemberIds)) break; // 处理过就不出理了
            $agentParentIdList[$memberId] = 0; // 先设置会员的团队上级领导为总店
            if ($index >= $roadLength - 1) break; // 链条顶部节点无需处理
            $memberAgentLevel = intval($roadItem['agent_level']);
            $curAgentLevel = $memberAgentLevel;
            $agentParentList = [];
            for ($i = $index + 1; $i < $roadLength; $i++) {
                $parentAgentLevel = intval($road[$i]['agent_level']);
                if ($parentAgentLevel > 0 && ($parentAgentLevel < $curAgentLevel || $curAgentLevel == 0)) {
                    $agentParentList[] = $road[$i];
                    $curAgentLevel = $parentAgentLevel;
                }
                // 遇到最大等级，退出
                if ($curAgentLevel == 1) {
                    break;
                }
            }
            $processedMemberIds[] = $memberId; // 记录下来，不再处理
            if (count($agentParentList) > 0) {
                // 设置会员团队上级领导为第一个关系人
                $agentParentIdList[$memberId] = $agentParentList[0]['member_id'];
                // 构造要插入的数据
                foreach ($agentParentList as $agentParentIndex => $agentParentItem) {
                    $insertDataList[] = [
                        'site_id' => $siteId,
                        'member_id' => $memberId,
                        'agent_level' => $memberAgentLevel,
                        'parent_id' => $agentParentItem['member_id'],
                        'level' => $agentParentIndex + 1,
                    ];
                }
            }
            // 处理到顶节点就不再处理了
            if ($topMemberId > 0 && $memberId == $topMemberId) {
                break;
            }
        }
    }

    /**
     * 获取某会员的1级平级代理列表，暂时只用在代理感恩奖上
     * @param int $memberId 会员的ID
     * @return array
     */
    static function getSameLevelMember($memberId)
    {
        $members = [];
        $list = [];
        while ($memberId != 0) {
            $memberParent = MemberParentsModel::query()
                ->where('tbl_member_parents.site_id', getCurrentSiteId())
                ->where('member_id', $memberId)
                ->first();
            if ($memberParent->parent_id) {
                $memberId = $memberParent->parent_id;
                $members[] = $memberParent->parent_id;
            } else {
                $memberId = 0;
            }
        }

        if ($members) {
            $mem = MemberModel::query()->whereIn('id', $members)->where('agent_level', 1)->orderByRaw("find_in_set(id,'" . implode(',', $members) . "')")->get();
            if ($mem) {
                $list = $mem->toArray();
            }
        }
        return $list;
    }
}