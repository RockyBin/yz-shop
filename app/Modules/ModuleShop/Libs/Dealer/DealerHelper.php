<?php

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Jobs\ResetDealerParentsJob;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\Upgrade\UpgradeConditionHelper;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\BaseModel;
use YZ\Core\Task\TaskHelper;
use Illuminate\Foundation\Bus\DispatchesJobs;
use YZ\Core\Constants as CoreConstants;

/**
 * 经销商的工具类
 * Class DealerHelper
 * @package App\Modules\ModuleShop\Libs\Dealer
 */
class DealerHelper
{
    use DispatchesJobs;

    /**
     * 获取某会员的上级经销商列表，主要用在分佣时
     *
     * @param int $memberId 会员的ID
     * @param int $includeSelf 当 $memberId 也是经销商时，在返回的经销商列表里，是否包含 $memberId 本身
     * @return array [
     * 'normal' => [],//普通上级经销商
     * 'samelevel' => [], //平级经销商
     * 'lowlevel' =>[], //越级经销商(等级比当前会员低的)
     * ]
     */
    public static function getParentDealers($memberId, $includeSelf = 1)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $levels = DealerLevelModel::query()->where(['site_id' => $siteId, 'status' => 1])->orderBy('weight', 'desc')->get();
        if (count($levels) < 1) return [];
        //获取上级推荐者
        $sql = "select * from tbl_member where id in (select parent_id from tbl_member_parents where member_id = :memberId) or id = :memberId2";
        $list = BaseModel::runSql($sql, [':memberId' => $memberId, ':memberId2' => $memberId]);
        $members = [];
        foreach ($list as $m) {
            $members[$m->id] = (array)$m;
        }
        //获取当前会员的经销商等级权重
        $currentMember = $members[$memberId];
        $weight = $levels->where('id', $currentMember['dealer_level'])->pluck('weight')->first();
        $parentList = MemberParentsModel::where('member_id', $memberId)->orderby('level', 'asc')->get();
        $normal = [];
        $sameLevel = [];
        $lowLevel = [];
        $list = [];
        if ($includeSelf && $currentMember['dealer_level'] > 0) {
            $normal[] = $members[$memberId];
        }
        if ($parentList) {
            $parentList = $parentList->toArray();
            $list = array_merge($list, $parentList);
        }
        $lastWeight = 0;

        foreach ($list as $m) {
            $m = $members[$m['parent_id']];
            $currentWeight = $levels->where('id', $m['dealer_level'])->pluck('weight')->first();
            if ($currentWeight > $lastWeight && $currentWeight > 0 && $currentWeight > $weight) {
                $normal[] = $m;
                $lastWeight = $currentWeight;
            }
            if ($lastWeight > $levels->first()->weight) break; //已经到了最大级
        }
        //分析是否有平级或越级经销商(平级或越级是否原始推荐关系链中第一个经销商的直接上级)
        $firstDealerParent = $members[$normal[0]['invite1']]; //第一个经销商的直属上级
        $firstParentWeight = $levels->where('id', $firstDealerParent['dealer_level'])->pluck('weight')->first(); //第一个经销商的直属上级的权重
        $firstDealerWeight = $levels->where('id', $normal[0]['dealer_level'])->pluck('weight')->first(); //第一个经销商的权重
        if ($firstDealerParent['dealer_level'] == $normal[0]['dealer_level'] && $firstDealerParent['dealer_level'] > 0) $sameLevel[] = $firstDealerParent;
        if ($firstParentWeight < $firstDealerWeight && $firstDealerParent['dealer_level'] > 0) $lowLevel[] = $firstDealerParent;
        return ['normal' => $normal, 'samelevel' => $sameLevel, 'lowlevel' => $lowLevel];
    }

    /**
     * 获取某会员的直接上级管理经销商或推荐人，主要用在申请经销商时
     *
     * @param int $memberId 会员的ID
     * @param int $level 会员的经销商等级
     * @return array ['manage_parent' => 管理上级的会员信息,'invite_parent' => 推荐上级的会员信息];
     */
    public static function findDealerParent($memberId, $level)
    {
        $data = ['manage_parent' => 0, 'invite_parent' => 0];
        $siteId = Site::getCurrentSite()->getSiteId();
        $levels = DealerLevelModel::query()->where(['site_id' => $siteId, 'status' => 1])->orderBy('weight', 'desc')->get();
        if (count($levels) < 1) return $data;
        $weight = $levels->where('id', $level)->pluck('weight')->first();
        //获取上级推荐者
        $sql = "select * from tbl_member where id in (select parent_id from tbl_member_parents where member_id = :memberId)";
        $list = BaseModel::runSql($sql, [':memberId' => $memberId]);
        $data = [];
        $index = 0;
        foreach ($list as $m) {
            $currentWeight = $levels->where('id', $m->dealer_level)->pluck('weight')->first();
            if ($m->dealer_level > 0 && $index === 0 && !$data['invite_parent']) $data['invite_parent'] = $m;
            if ($m->dealer_level > 0 && !$data['manage_parent'] && $currentWeight > $weight) $data['manage_parent'] = $m;
            $index += 1;
        }
        return $data;
    }

    /**
     * 重置团队关系队列任务
     * @param $memberId
     */
    public static function dispatchResetDealerParentsJob($memberId, $oldDealerLevel = 0, $dealerLevel = 0)
    {
        $memInfo = MemberModel::find($memberId);
        ResetDealerParentsJob::dispatch($memberId, TaskHelper::createChangeDealerParentTaskGroupId($memInfo->site_id), $oldDealerLevel, $dealerLevel);
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
    public static function resetDealerParentRelationTree($rootMemberId)
    {
        Log::writeLog('resetDealerParentRelationTree', 'member_id:' . $rootMemberId);
        $rootMemberId = intval($rootMemberId);
        if ($rootMemberId <= 0) return;
        try {
            DB::beginTransaction();
            $siteId = Site::getCurrentSite()->getSiteId();
            $levels = DealerLevelModel::query()->where(['site_id' => $siteId, 'status' => 1])->orderBy('weight', 'desc')->get();
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
                ->select('mp.member_id as id', 'm.id as member_id', 'm.dealer_level', 'mp.level')
                ->get();
            $memberRoadList = [];
            foreach ($memberParentsList as $memberParentsItem) {
                $memberRoadList[$memberParentsItem->id][] = $memberParentsItem;
            }
            // 如果只有自己且没有上级
            if (count($memberRoadList) == 0) {
                $memberRoadList[$rootMemberId] = [];
            }
            // 获取所有底部节点经销商等级
            $bottomMemberDealerLevels = MemberModel::query()->where('site_id', $siteId)->whereIn('id', $bottomMemberIds)->select('id', 'dealer_level')->get()->pluck('dealer_level', 'id')->toArray();
            $processedMemberIds = []; // 已经处理过的会员id
            $insertDataList = []; // 要插入的数据
            $DealerParentIdList = []; // 直属上级团队领导数据
            // !!! 强调：不建议在循环里操作数据库，否则会大大增大时间 !!!
            foreach ($memberRoadList as $id => $memberParentRoad) {
                // 把最底元素压进路径头部
                array_unshift($memberParentRoad, [
                    'member_id' => $id,
                    'dealer_level' => intval($bottomMemberDealerLevels[$id]),
                    'level' => 0,
                ]);
                // 只有自己就不处理了
                if (count($memberParentRoad) <= 1) {
                    $DealerParentIdList[$id] = 0;
                    continue;
                }
                self::buildDealerParentByRoad($levels, $memberParentRoad, $processedMemberIds, $insertDataList, $DealerParentIdList, $rootMemberId);
            }
            // 处理直属上级领导id
            $updateDataList = [];
            if (count($DealerParentIdList) > 0) {
                foreach ($DealerParentIdList as $tmpMemberId => $tmpDealerParentId) {
                    $updateDataList[] = [
                        'id' => $tmpMemberId,
                        'dealer_parent_id' => $tmpDealerParentId,
                    ];
                }
            }
            // 清理旧数据，放到插入数据之前，尽量避免多线程时因时间差带来的数据错乱
            DealerParentsModel::query()->where('site_id', $siteId)
                ->whereIn('member_id', array_merge($subMemberIds, [$rootMemberId]))
                ->delete();
            // 批量插入数据
            if (count($insertDataList) > 0) {
                DB::table('tbl_dealer_parents')->insert($insertDataList);
            }
            // 批量更新直属上级领导id
            if (count($updateDataList) > 0) {
                (new MemberModel())->updateBatch($updateDataList, 'id');
            }
            DB::commit();
        } catch (\Exception $e) {
            Log::writeLog('ResetDealerParentRelationTree_Error', $e->getMessage());
            DB::rollBack();
        }
    }

    /**
     * 根据会员的父亲路径重新建立整条路里所有会员的团队关系
     * !!! 此方法因为会循环调用，因此请不要执行数据库操作 !!!
     * 该方法没有清理旧数据，请调用前清理旧数据
     * @param Collection $levels 经销商等级列表，要求按权限从大到小排序
     * @param array $road
     * @param array $processedMemberIds 已经处理的会员ID，避免重复处理
     * @param array $insertDataList 新的关系数据
     * @param array $DealerParentIdList 新的关系数据
     * @param int $topMemberId 如果有设，去到该节点则会停止往上计算
     */
    private static function buildDealerParentByRoad($levels, array $road, array &$processedMemberIds, array &$insertDataList, array &$DealerParentIdList, $topMemberId = 0)
    {
        $roadLength = count($road);
        $siteId = Site::getCurrentSite()->getSiteId();
        foreach ($road as $index => $roadItem) {
            $memberId = intval($roadItem['member_id']);
            if (!$memberId || in_array($memberId, $processedMemberIds)) break; // 处理过就不出理了
            $DealerParentIdList[$memberId] = 0; // 先设置会员的团队上级领导为总店
            if ($index >= $roadLength - 1) break; // 链条顶部节点无需处理
            $memberDealerLevel = intval($roadItem['dealer_level']);
            $DealerParentList = [];
            $currentWeight = $levels->where('id', $memberDealerLevel)->pluck('weight')->first();
            for ($i = $index + 1; $i < $roadLength; $i++) {
                $parentWeight = $levels->where('id', $road[$i]['dealer_level'])->pluck('weight')->first();
                if ($parentWeight > 0 && $parentWeight > $currentWeight) {
                    $DealerParentList[] = $road[$i];
                    $currentWeight = $parentWeight;
                }
                // 遇到最大等级，退出
                if ($currentWeight >= $levels->sortByDesc('weight')->first()->weight) {
                    break;
                }
            }
            $processedMemberIds[] = $memberId; // 记录下来，不再处理
            if (count($DealerParentList) > 0) {
                // 设置会员团队上级领导为第一个关系人
                $DealerParentIdList[$memberId] = $DealerParentList[0]['member_id'];
                // 构造要插入的数据
                foreach ($DealerParentList as $DealerParentIndex => $DealerParentItem) {
                    $insertDataList[] = [
                        'site_id' => $siteId,
                        'member_id' => $memberId,
                        'dealer_level' => $memberDealerLevel,
                        'parent_id' => $DealerParentItem['member_id'],
                        'level' => $DealerParentIndex + 1,
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
     * 经销商升级
     * @param int $memberId
     * @param array $params
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function dealerUpgrade($memberId, $params = [])
    {
        $siteId = getCurrentSiteId();
        $member = new Member($memberId);
        $memberModel = $member->getModel();
        // 会员不存在
        if (!$memberModel) {
            return false;
        }
        $parentLevelWeight = 0; // 主等级权重
        $hideLevelWeight = 0; // 隐藏等级权重
        // 还不是经销商的 暂时不能升级 如果需要会员自动升级 注释掉该段代码

        // 获取当前的等级权重
        $currentParentLevel = DealerLevelModel::query()
            ->where('site_id', $siteId)
            ->where('id', $memberModel->dealer_level)
            ->first();
        $parentLevelWeight = $currentParentLevel->weight;
        if ($memberModel->dealer_hide_level) {
            $currentHideLevel = DealerLevelModel::query()
                ->where('site_id', $siteId)
                ->where('id', $memberModel->dealer_hide_level)
                ->first();
        }
        if ($currentHideLevel) {
            $hideLevelWeight = $currentHideLevel->weight;
            $currentLevel = $currentHideLevel;
        } else {
            $currentLevel = $currentParentLevel;
        }

        $dealerModel = DealerModel::query()->where('site_id', $siteId)
            ->where('member_id', $memberId)
            ->first();
        // 取消资格 拒绝申请的无法自动升级
        if (
            $dealerModel && (
                $dealerModel->status == Constants::DealerStatus_RejectReview
                || $dealerModel->status == Constants::DealerStatus_Cancel)
        ) {
            return false;
        }
        // 从权重大的开始匹配
        $levels = DealerLevelModel::query()->where('site_id', $siteId)
            ->where('status', Constants::DealerLevelStatus_Active)
            ->orderByDesc('weight')
            ->get();
        // 没有开启中的等级
        if ($levels->count() < 1) {
            return false;
        }

        $tempParents = [];
        foreach ($levels as $hide) {
            // 如果有父级 压入到父级的列表
            if ($hide['parent_id']) {
                if (!isset($tempParents['p-' . $hide['parent_id']])) {
                    $tempParents['p-' . $hide['parent_id']] = [];
                }
                $tempParents['p-' . $hide['parent_id']][] = $hide;
            }
        }
        // 主等级 隐藏等级分组到相应主等级
        $parentLevels = [];
        foreach ($levels as $item) {
            // 父id为空的 为主等级
            if (!$item['parent_id']) {
                $item['hide_levels'] = $tempParents['p-' . $item['id']] ?: [];
                $parentLevels['p-' . $item['id']] = $item;
            }
        }
        $newHideLevel = null; // 最终升级的隐藏等级
        $newParentLevel = null; // 升级的主等级
        // 处理升级
        foreach ($parentLevels as $level) {
            // 可以升级的 或者是当前等级的 因为当前等级有可能有隐藏等级可以升级
            if (
                ($parentLevelWeight < $level->weight && DealerLevel::canUpgrade($level, $memberId, $params))
                || $parentLevelWeight == $level->weight
            ) {
                $newParentLevel = $level;
                // 如果开启了隐藏等级 并且有隐藏等级 则去检测隐藏等级是否可以升级
                if ($level->has_hide && $level['hide_levels']) {
                    foreach ($level['hide_levels'] as $h) {
                        if ($hideLevelWeight > $h->weight || !DealerLevel::canUpgrade($h, $memberId, $params)) {
                            continue;
                        }
                        $newHideLevel = $h;
                        break;
                    }
                }
                // 因为是按权重排倒序 所以只要有匹配到的 后面的就不用再去匹配了
                break;
            }
        }
        // 有新的主等级 或者新的隐藏等级 则是升级成功
        if (
            $newParentLevel
            &&
            ($newParentLevel['weight'] > $parentLevelWeight || $newHideLevel)
        ) {
            $time = Carbon::now();
            // 会员自动升级
            if (!$memberModel->dealer_level) {
                // 有可能存在申请记录，因为会员有可能先在前台申请再去走自动升级
                if ($dealerModel) {
                    $dealerModel->delete();
                }
                $dealerModel = new DealerModel();
                $dealerModel->site_id = $siteId;
                $dealerModel->member_id = $memberId;
                $dealerModel->apply_condition = json_encode(['dealer_level' => $newParentLevel->id, 'dealer_apply_level_name' => $newParentLevel->name]);
                $dealerModel->auto_upgrade_data = static::saveAutoUpgradeDealer($newParentLevel);
                $dealerModel->status = Constants::DealerStatus_Active;
                $dealerModel->dealer_apply_level = $newParentLevel->id;
                $dealerModel->created_at = $time;
                $dealerModel->passed_at = $time;
                // 寻找上级
                $parents = DealerHelper::findDealerParent($memberId, $newParentLevel->id);
                $dealerModel->invite_review_member = $parents['invite_parent'] ? $parents['invite_parent']->id : 0;
                $dealerModel->invite_review_status = Constants::DealerStatus_Active;
                $dealerModel->invite_review_passed_at = Carbon::now();
                $dealerModel->parent_review_member = $parents['manage_parent'] ? $parents['manage_parent']->id : 0;
                $dealerModel->parent_review_status = Constants::DealerStatus_Active;
                $dealerModel->parent_review_passed_at = Carbon::now();
                // 处理异常情况:先申请，有了上级审核记录，需要同步删除审核记录
                VerifyLogModel::query()
                    ->where('site_id', $siteId)
                    ->where('from_member_id', $memberId)
                    ->where('type', Constants::VerifyLogType_DealerVerify)
                    ->delete();
                $logText = 'member_id[' . $memberId . '] auto upgrade to ' . $newParentLevel->name . '[' . $newParentLevel->id . ']';
            } else {
                $newLevelName = $newHideLevel ? $newHideLevel->name : $newParentLevel->name;
                $newLevelId = $newHideLevel ? $newHideLevel->id : $newParentLevel->id;
                $logText = 'member_id[' . $memberId . '] from ' . $currentLevel->name .
                    '[' . $currentLevel->id . '] upgrade to ' . $newLevelName . '[' . $newLevelId . ']';
            }
            $dealerModel->upgrade_at = $time;
            $dealerModel->save();
            // 说明是升级到了隐藏等级
            $memberData = [];
            if ($newHideLevel) {
                $memberData['dealer_level'] = $newParentLevel->id;
                $memberData['dealer_hide_level'] = $newHideLevel->id;
            } else {
                // 升级到主等级 把隐藏等级设为0
                $memberData['dealer_level'] = $newParentLevel->id;
                $memberData['dealer_hide_level'] = 0;
            }
            Log::writeLog('dealerLevelUpgrade', $logText);
            $member->edit($memberData);
            //消息通知
            MessageNotice::dispatch(CoreConstants::MessageType_Dealer_LevelUpgrade, $member->getModel());
            return true;
        }
        return false;
    }

    /**
     * 升级会员的经销商等级 同时检测上级是否可以升级
     * @param $memberId
     * @param $params
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function upgradeRelationDealerLevel($memberId, $params = [])
    {
        // 获取所有需要检测的会员id
        //$memberIds = self::getParentDealers($memberId);
        //$memberIds = $memberIds['normal'];
        $memberIds = self::getMemberParensDealer($memberId);
        $memberIds[] = ['id' => $memberId];// 当前会员也需要压入处理一下
        foreach ($memberIds as $member) {
            self::dealerUpgrade($member['id'], $params);
        }
    }

    /**
     * 获取推荐链条的 所有经销商
     * @param $memberId
     * @return array
     */
    public static function getMemberParensDealer($memberId)
    {
        //获取上级推荐者
        $sql = "select * from tbl_member where id in (select parent_id from tbl_member_parents where member_id = :memberId) or id = :memberId2";
        $list = BaseModel::runSql($sql, [':memberId' => $memberId, ':memberId2' => $memberId]);
        $members = [];
        foreach ($list as $m) {
            // 因为
            //  if ($m->dealer_level > 0) {
            $members[] = (array)$m;
            // }
        }
        return $members;
    }

    public static function saveAutoUpgradeDealer($level)
    {
        $conditions = is_string($level->upgrade_condition) ? json_decode($level->upgrade_condition, true) : $level->upgrade_condition;
        $cache = [
            'data' => $conditions['upgrade'],
            'text' => static::getLevelConditionsTitle($conditions['upgrade'], []),
            'level_info' => ['level_id' => $level->id, 'level_name' => $level->name]
        ];
        $auto_upgrade_data = json_encode($cache, JSON_UNESCAPED_UNICODE);
        return $auto_upgrade_data;
    }

    /**
     * 获取升级条件快照的文案
     * @param $upgrade
     * @param $productId
     * @return array
     */
    public static function getLevelConditionsTitle($upgrade, $productId)
    {
        $conditions = ['and' => [], 'or' => []];
        // 按 and和or 分组
        foreach ($upgrade as $con) {
            $conIns = UpgradeConditionHelper::createInstance($con['type'], $con['value'], $productId);
            $title = $conIns->getNameText();
            // and和or条件分组
            if ($title) {
                $conditions[$con['logistic']][] = $title;
            }
        }
        return $conditions;
    }
}