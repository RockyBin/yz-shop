<?php
/**
 * 代理升级条件 所有推荐下级人数
 * User: liyaohui
 * Date: 2020/5/7
 * Time: 16:03
 */

namespace App\Modules\ModuleShop\Libs\Custom\Site1696;


use App\Modules\ModuleShop\Libs\Agent\Condition\abstractCondition;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Plugin\IPlugin;

class AgentUpgradeConditionAllSubAgentNum extends abstractCondition implements IPlugin
{
    protected $name = "所有推荐下级代理满";
    private $lines = 1; // 几条线

    /**
     * 初始化
     * @param array $params
     * @return mixed|void
     */
    public function init(array $params)
    {
        $this->value = [
            'agent_level' => $params['agent_level'],
            'member_count' => $params['member_count']
        ];
        $this->lines = intval($params['lines']);
        $this->textValue = $this->value['member_count'];
    }

    /**
     * @return bool
     */
    public function enabled()
    {
        if (!$this->value['agent_level'] || !$this->value['member_count']) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取条件文案
     * @return string
     */
    public function getTitle()
    {
        return "所有推荐下级 " . implode(',', $this->value['agent_level'])
            . "级代理满 " . $this->value['member_count'] . ' 人'
            . "," . $this->lines . '条不同的线';
    }

    /**
     * 插件执行方法
     * @param null $runTimeParams
     * @return bool|mixed
     */
    public function execute($runTimeParams = null)
    {
        return $this->canUpgrade($runTimeParams['member_id']);
    }

    /**
     * 是否满足升级
     * @param int $memberId
     * @param array $params
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $query = MemberParentsModel::query()
            ->from('tbl_member_parents as mp')
            ->join('tbl_member as m', function($join) {
                $join->on('m.id', 'mp.member_id')
                    ->whereIn('m.agent_level', $this->value['agent_level']);
            })
            ->where('mp.parent_id', $memberId);
        // 只有一条线 直接统计数据返回即可
        if ($this->lines == 1) {
            $count = $query->count();
            return $count >= $this->value['member_count'];
        } elseif ($this->lines > 1) {
            // 大于一条线
            $members = $query->select('mp.*')->get();
            // 数量不足 直接返回
            if ($members->count() < $this->value['member_count']) {
                return false;
            }
            // 数量满足 判断是否满足线
            // 直属的是否满足
            $directCount = $members->where('level', 1)->count();
            // 直属的已经满足
            if ($directCount >= $this->lines) return true;
            // 直属的不满足 去查找间推的上级 查找到属于当前会员的直属下级 去重就是线的数量
            $indirectMember = $members->where('level', '>', 1)->pluck('member_id')->toArray();
            if ($indirectMember) {
                // 当前会员的所有直属下级
                $directMembers = MemberParentsModel::query()
                    ->where('parent_id', $memberId)
                    ->where('level', 1)
                    ->pluck('member_id')->toArray();
                if ($directMembers) {
                    // 查找出来当前所有满足的直属下级id 去重后就是当前的线数量
                    $count = MemberParentsModel::query()
                        ->whereIn('parent_id', $directMembers)
                        ->whereIn('member_id', $indirectMember)
                        ->pluck('parent_id')->unique()->count();
                    return $count >= $this->lines;
                }
            }
        }
        return false;
    }


}