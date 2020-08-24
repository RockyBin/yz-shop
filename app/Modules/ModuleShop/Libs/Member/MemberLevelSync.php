<?php

namespace App\Modules\ModuleShop\Libs\Member;

use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

/**
 * 会员等级同步类，当某个会员的等级、分销等级、代理等级、区代等级、经销商等级改变时，自动同步其它等级
 * 要注意，因为会员等级升级可能会导致分销升级，分销升级又可能会引起代理升级，所以同步等级只能从大到小
 * 比如代理升级时可同步分销和会员，分销升级只能同步会员，不能分销升级同步代理，因为这么引起连锁反应，
 * 并且同步等级，不能采用原来的升级操作，而是直接改数据库字段，保证不会引起连锁反应
 * Class MemberLevelSync
 * @package App\Modules\ModuleShop\Libs\Member
 */
class MemberLevelSync
{
    private $siteId = 0; // 站点ID

    /**
     * 初始化
     * MemberConfig constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        if ($siteId) {
            $this->siteId = $siteId;
        } else {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 获取当前站点的等级同步规则
     * @return bool
     */
    public function getConfig()
    {

    }

    /**
     * 保存当前站点的等级同步规则
     * @param array $data
     * @return bool
     */
    public function save(array $data)
    {

    }

    /**
     * 同步会员分销等级
     * @param $memberId
     * @param $memberLevel
     */
    public function syncMemberLevel($memberId, $memberLevel){
        $member = new Member($memberId,$this->siteId,false);
        // 会员冻结，不能升级
        if (!$member->checkExist() || !$member->getModel()->status) {
            return;
        }
        $targetLevel = MemberLevelModel::find($memberLevel);
        if(!$targetLevel) return;
        if($memberLevel == $member->getModel()->level) return;
        if ($member->getModel()->level) {
            $currentLevel = MemberLevelModel::find($member->getModel()->level);
            if ($currentLevel) {
                $curLevelId = $member->getModel()->level;
                $curLevelName = $currentLevel->name;
                // 如果当前的等级权重高于目标等级权重，不进行同步升级处理
                if($currentLevel->weight > $targetLevel->weight) {
                    return;
                }
            }
        }
        $model = $member->getModel();
        $model->level = $memberLevel;
        $model->save();
        // 日志
        Log::writeLog('memberLevelSync', 'member_id[' . $member->getMemberId() . '] from ' . $curLevelName . '[' . $curLevelId . '] upgrade to ' . $targetLevel->name . '[' . $memberLevel . ']');
    }

    /**
     * 同步升级分销等级
     * @param $memberId
     * @param $distributionLevel
     */
    public function syncDistributionLevel($memberId, $distributionLevel){

    }

    /**
     * 同步升级代理等级
     * @param $memberId
     * @param $agentLevel
     */
    public function syncAgentLevel($memberId, $agentLevel){

    }

    /**
     * 同步升级区代等级
     * @param $memberId
     * @param $agentLevel
     */
    public function syncAreaAgentLevel($memberId, $areaAgentLevel){

    }

    /**
     * 同步升级经销没等级
     * @param $memberId
     * @param $dealerLevel
     */
    public function syncDealerLevel($memberId, $dealerLevel){

    }
}