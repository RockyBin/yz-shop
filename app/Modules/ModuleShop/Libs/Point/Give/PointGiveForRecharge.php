<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;

/**
 * 重置送积分
 * Class PointGiveForRecharge
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForRecharge extends AbstractPointGive
{
    protected $statusColumnName = 'in_recharge_status';
    protected $pointColumnName = 'in_recharge_point';
    private $financeId = 0; // 财务id
    private $financeModel = null; // 财务数据

    /**
     * 初始化
     * PointGiveForRecharge constructor.
     * @param $memberModal
     * @param $financeModelOrId
     */
    public function __construct($memberModal, $financeModelOrId)
    {
        parent::__construct($memberModal);
        if (is_numeric($financeModelOrId)) {
            $this->financeId = intval($financeModelOrId);
            if ($this->financeId > 0) {
                $this->financeModel = FinanceModel::find($this->financeId);
            }
        } else if ($financeModelOrId) {
            $this->financeModel = $financeModelOrId;
            $this->financeId = intval($this->financeModel->id);
        }
        if ($this->financeModel) {
            $this->setTerminalType($this->financeModel->terminal_type);
        }
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 数值正常
        if (!$this->financeId) return false;

        // 这里要验证财务数据准确性
        if (!$this->financeModel) return false;
        if (intval($this->financeModel->status) != Constants::FinanceStatus_Active) return false; // 生效的
        if (intval($this->financeModel->money) <= 0) return false; // 金额大于0
        if (intval($this->financeModel->type) != Constants::FinanceType_Normal) return false; // 余额
        if (!in_array(intval($this->financeModel->in_type), [Constants::FinanceInType_Recharge, Constants::FinanceInType_Manual])) return false; // 充值类型
        if (intval($this->financeModel->member_id) != intval($this->member->getMemberId())) return false; // 所有者归属

        // 验证是否已经赠送过
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_Recharge,
            'in_id' => $this->financeId
        ]);
        if ($total > 0) return false;

        return true;
    }

    public function calcPoint($money)
    {
        $point = 0;
        if ($money > 0) {
            $pointVal = floor($money / 100); // 每一元赠送多少积分
            $point = $pointVal * $this->getPoint();
        }
        return $point;
    }

    /**
     * 充值赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        $money = intval($this->financeModel->money); // 单位分
        $point = $this->calcPoint($money);
        // $pointVal = floor($money / 100); // 每一元赠送多少积分
        if ($point > 0) {
            return $this->point->add([
                'member_id' => $this->member->getMemberId(),
                'in_out_type' => Constants::PointInOutType_Recharge,
                'in_out_id' => $this->financeId,
                'point' => $point,
                'about' => '充值',
                'terminal_type' => $this->getTerminalType(),
                'status' => Constants::PointStatus_Active,
            ]);
        }
        return false;
    }
}