<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;

/**
 * 注册送积分
 * Class PointGiveForRegister
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForRegister extends AbstractPointGive
{
    protected $statusColumnName = 'in_member_reg_status';
    protected $pointColumnName = 'in_member_reg_point';

    public function __construct($memberModal)
    {
        parent::__construct($memberModal);
        if ($this->member->checkExist()) {
            $this->setTerminalType(intval($this->member->getModel()->terminal_type));
        }
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_MemberReg
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 注册赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        return $this->point->add([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_MemberReg,
            'point' => $this->getPoint(),
            'about' => '会员注册',
            'terminal_type' => $this->getTerminalType(),
            'status' => Constants::PointStatus_Active,
        ]);
    }
}