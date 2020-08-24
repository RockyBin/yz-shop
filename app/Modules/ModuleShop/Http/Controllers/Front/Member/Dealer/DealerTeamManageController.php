<?php
/**
 * 经销商申请相关接口
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Dealer\Dealer;
use Illuminate\Http\Request;

class DealerTeamManageController extends BaseController
{
    public function getList()
    {
        $dealer = Dealer::getDealerTeam($this->memberId);
        $data = [
            'cross_level_dealer' => $dealer['crossLevelDealer'] ? $dealer['crossLevelDealer']->count() : 0,
            'same_level_dealer' => $dealer['sameLevelDealer'] ? $dealer['sameLevelDealer']->count() : 0,
            'team_dealer' => $dealer['teamDealer'] ? $dealer['teamDealer']->count() : 0,
            'deal_level_team_member' => $dealer['dealLevelTeamMember']
        ];
        return makeApiResponse(200, '', $data);
    }

    public function getInfo(Request $request)
    {
        $type = $request->type;
        if ($type) {
            makeApiResponseFail('请输入正确Type值');
        }
        if ($type == 4 && !$request->level) {
            makeApiResponseFail('请输入正确等级');
        }
        $dealer = Dealer::getDealerTeam($this->memberId, false, ['level' => $request->level]);
        switch ($type) {
            case $type == 1 :
                $data = $dealer['crossLevelDealer'];
                break;
            case $type == 2 :
                $data = $dealer['sameLevelDealer'];
                break;
            case $type == 3 :
                $data = $dealer['teamDealer'];
                break;
            case $type == 4 :
                $data = $dealer['dealLevelTeamMember'];
                break;
        }
        foreach ($data as &$item) {
            $item->performance_reward_count = $item->performance_reward_count ? moneyCent2Yuan($item->performance_reward_count) : 0;
        }
        return makeApiResponse(200, '', $data);
    }
}