<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;

/**
 * 成为分销商赠送积分
 * Class PointGiveForDistributionBecome
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForDistributionBecome extends AbstractPointGive
{
    protected $statusColumnName = 'in_distribution_become_status';
    protected $pointColumnName = 'in_distribution_become_point';

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 检查是否分销商
        if ($this->member->checkExist() && !$this->member->getModel()->is_distributor) {
            return false;
        }

        // 是否已经赠送
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_DistributionBecome
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 成为分销商赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        return $this->point->add([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_DistributionBecome,
            'point' => $this->getPoint(),
            'about' => '申请成为分销商',
            'terminal_type' => $this->getTerminalType(),
            'status' => Constants::PointStatus_Active,
        ]);
    }
}