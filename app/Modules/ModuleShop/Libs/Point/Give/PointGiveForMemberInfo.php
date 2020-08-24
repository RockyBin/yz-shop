<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;

/**
 * 完善资料送积分
 * Class PointGiveForMemberInfo
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForMemberInfo extends AbstractPointGive
{
    protected $statusColumnName = 'in_member_info_status';
    protected $pointColumnName = 'in_member_info_point';

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_MemberInfo
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 完善会员信息赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        return $this->point->add([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_MemberInfo,
            'point' => $this->getPoint(),
            'about' => '完善个人资料',
            'terminal_type' => $this->getTerminalType(),
            'status' => Constants::PointStatus_Active,
        ]);
    }
}