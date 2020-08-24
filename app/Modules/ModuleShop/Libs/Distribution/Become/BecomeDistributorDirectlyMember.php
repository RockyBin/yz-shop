<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Model\MemberParentsModel;

/**
 * 提交申请成为分销商
 * Class BecomeDistributorFormApply
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
class BecomeDistributorDirectlyMember extends AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_DirectlyMember;

    /**
     * 实例化
     * BecomeDistributorFormApply constructor.
     * @param $memberModal
     * @param DistributionSetting|null $distributionSetting
     */
    public function __construct($memberModal, DistributionSetting $distributionSetting = null)
    {
        parent::__construct($memberModal, $distributionSetting);
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 读取数据
        $memberModel = $this->member->getInfo(true);
        $total_consume_member = MemberParentsModel::query()
            ->where('site_id',$memberModel->site_id)
            ->where('parent_id',$memberModel->id)
            ->where('level',1)
            ->count();
        $config_consume_member = intval($this->setting->directly_member);
        // 计算结果
        $result = $total_consume_member >= $config_consume_member;
        // 还需要多少次数
        $remain = $total_consume_member >= $config_consume_member ? 0 : $config_consume_member - $total_consume_member;
        $this->setExtendData([
            'directly_member_remain' => $remain,
            'directly_member_need' => $config_consume_member,
        ]);
        if (!$result) {
            $this->errorMsg = str_replace('#people#', $config_consume_member, trans('shop-front.distributor.distributor_directly_not_enough'));
        }
        return $result;
    }
}