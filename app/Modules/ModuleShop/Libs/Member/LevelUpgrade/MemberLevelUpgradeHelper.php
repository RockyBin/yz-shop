<?php

namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

use App\Modules\ModuleShop\Libs\Member\MemberConfig;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use Illuminate\Mail\Message;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;

/**
 * 会员升级助手类
 * Class MemberLevelUpgradeHelper
 */
class MemberLevelUpgradeHelper
{
    /**
     * 会员等级升级
     * @param $member
     * @param $params 额外的参数,根据不同的升级条件传不同的参数
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function levelUpgrade($member, $params = [])
    {
        $member = new Member($member,0,false);
        // 会员冻结，不能升级
        if (!$member->checkExist() || !$member->getModel()->status) {
            return false;
        }
        $memberLevel = new MemberLevel();
        // 当前会员等级权重
        $curWeight = -1;
        $curLevelId = 0;
        $curLevelName = '无';
        if ($member->getModel()->level) {
            $memberLevelModel = $memberLevel->detail($member->getModel()->level);
            if ($memberLevelModel) {
                $curWeight = intval($memberLevelModel->weight);
                $curLevelId = $member->getModel()->level;
                $curLevelName = $memberLevelModel->name;
            }
        }
        $data = $memberLevel->getList([
            'status' => Constants::CommonStatus_Active,
            'for_newmember' => Constants::MemberLevelForNew_Yes,
            'weight_min' => $curWeight,
            'weight_order_desc' => true,
        ]);
        if (!$data['list'] || intval($data['total']) <= 0) {
            return false;
        }

        // 按权重从大到小
        $levels = MemberLevelModel::query()->where([
            'site_id' => getCurrentSiteId(),
            'status' => Constants::CommonStatus_Active,
            'for_newmember' => Constants::MemberLevelForNew_Yes])
            ->where('weight','>',$curWeight)
            ->orderBy('weight','desc')->get();

        $upgradeToLevelId = 0;
        $upgradeToLevelName = '';
        $upgradeCondition = '';
        foreach ($levels as $level) {
            // 找到一个可升级的，就退出
            $res = MemberLevel::canUpgrade($level,$member->getMemberId(),$params);
            if($res){
                $upgradeToLevelId = $level['id'];
                $upgradeToLevelName = $level['name'];
                $upgradeCondition = $res['condition'];
                break;
            }
        }
        // 会员升级
        if ($upgradeToLevelId) {
            $member->edit([
                'level' => $upgradeToLevelId
            ]);
            // 日志
            Log::writeLog('memberLevelUpgrade', 'member_id[' . $member->getMemberId() . '] from ' . $curLevelName . '[' . $curLevelId . '] upgrade to ' . $upgradeToLevelName . '[' . $upgradeToLevelId . '] 升级条件 ['.$upgradeCondition.']');
        }
    }
}