<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Constants;

/**
 * 推荐新分销商送积分，实例化时的会员是下级
 * Class PointGiveForDistributionRecommend
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForDistributionRecommend extends AbstractPointGive
{
    protected $statusColumnName = 'in_distribution_recommend_status';
    protected $pointColumnName = 'in_distribution_recommend_point';
    private $inviteId = 0; // 推荐人id

    /**
     * 初始化
     * PointGiveForDistributionRecommend constructor.
     * @param $memberModal
     */
    public function __construct($memberModal)
    {
        parent::__construct($memberModal);
        if ($this->member->checkExist() && $this->member->getModel()->is_distributor && $this->member->getModel()->invite1) {
            $invite = new Member($this->member->getModel()->invite1);
            if ($invite->checkExist()) {
                $this->inviteId = $invite->getMemberId();
            }
        }
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 数值验证
        if (!$this->inviteId) return false;
        // 验证是否已经赠送过
        $total = $this->point->count([
            'member_id' => $this->inviteId,
            'in_out_type' => Constants::PointInOutType_DistributionRecommend,
            'in_id' => $this->member->getMemberId()
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 推荐分销商赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        return $this->point->add([
            'member_id' => $this->inviteId,
            'in_out_type' => Constants::PointInOutType_DistributionRecommend,
            'in_out_id' => $this->member->getMemberId(),
            'point' => $this->getPoint(),
            'about' => '推荐新分销商，新分销商的会员ID：' . $this->member->getMemberId(),
            'terminal_type' => $this->getTerminalType(),
            'status' => Constants::PointStatus_Active,
        ]);
    }
}