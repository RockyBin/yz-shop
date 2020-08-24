<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;

/**
 * 首次登录送积分
 * Class PointGiveForLogin
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForLogin extends AbstractPointGive
{
    protected $statusColumnName = 'in_member_login_status';
    protected $pointColumnName = 'in_member_login_point';

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_MemberLogin,
            'created_at_start' => date('Y-m-d') . ' 00:00:00',
            'created_at_end' => date('Y-m-d') . ' 23:59:59',
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 登录赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        return $this->point->add([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_MemberLogin,
            'point' => $this->getPoint(),
            'about' => '会员登录',
            'terminal_type' => $this->getTerminalType(),
            'status' => Constants::PointStatus_Active,
        ]);
    }
}