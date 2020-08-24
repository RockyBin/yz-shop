<?php
/**
 * User: liyaohui
 * Date: 2019/10/22
 * Time: 15:52
 */

namespace App\Modules\ModuleShop\Libs\Distribution;


use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;

class UpgradeConditionDirectlyAllUnderMember extends abstractCondition
{
    protected $name = "直推成员数量";

    public function __construct($value)
    {
        $this->value = [
            'member_count' => $value['member_count'],
            'member_level_id' => explode(',', $value['member_level_id'])
        ];
    }

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getDesc()
    {
        $levelText = MemberLevel::getLevelName($this->value['member_level_id']);
        return "直推 ".$levelText. " 的成员合计数量 满 " . $this->value['member_count'] . " 人";
    }

    /**
     * 判断某分销商是否满足此分销条件
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->beforeCheckUpgrade($params)) {
            return false;
        }
        $value = $this->value['member_level_id'];
        $count = MemberModel::query()->where('invite1', $memberId)->where('status', 1)->whereIn('level', $value)->count();
        return $count >= $this->value['member_count'];
    }
}