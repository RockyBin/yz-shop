<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/9/30
 * Time: 10:01
 */

namespace App\Modules\ModuleShop\Libs\Agent\Condition;


use YZ\Core\Model\MemberParentsModel;

class UpgradeConditionRecommendOneLevelAgentNum extends abstractCondition
{
    protected $value = '';
    protected $name = "直推一级代理人数满";

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 判断是否满足此代理升级条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->checkIsAgent($params)) {
            return false;
        }
        $count = MemberParentsModel::query()
            ->from('tbl_member_parents as mp')
            ->join('tbl_member as m', function($join) {
                $join->on('m.id', 'mp.member_id')
                    ->where('m.agent_level', 1);
            })
            ->where(['mp.parent_id' => $memberId, 'mp.level' => 1])
            ->count();

        return $count >= $this->value;
    }
}