<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;

/**
 * 每日首次分销赠送积分
 * Class PointGiveForShare
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForShare extends AbstractPointGive
{
    protected $statusColumnName = 'in_share_status';
    protected $pointColumnName = 'in_share_point';

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_Share,
            'created_at_start' => date('Y-m-d') . ' 00:00:00',
            'created_at_end' => date('Y-m-d') . ' 23:59:59',
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 分享赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        return $this->point->add([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_Share,
            'point' => $this->getPoint(),
            'about' => '首次分享',
            'terminal_type' => $this->getTerminalType(),
            'status' => Constants::PointStatus_Active,
        ]);
    }
}