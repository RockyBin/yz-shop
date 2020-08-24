<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Plugin\PluginHelper;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\AgentLevelModel;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use  App\Modules\ModuleShop\Libs\Agent\Condition\UpgradeConditionHelper;
use YZ\Core\Logger\Log;

/**
 * 代理基础设置
 */
class AgentLevel
{
    private $_siteId = 0;
    private $levelConfig = 3;

    /**
     * 初始化代理设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $baseSetting = new AgentBaseSetting();
        //获取配置最大等级数
        $this->levelConfig = $baseSetting->getSettingModel()->level;
        //获取已有等级总数
        $level_count = AgentLevelModel::where(['site_id' => $this->_siteId])->count();
        //不管什么情况，暂时最少需要生成3个等级
        if ($level_count < 3) $this->levelConfig = 3;
        //如果最大等级大于已有等级总数，说明已有等级少了，需要添加新等级
        if ($this->levelConfig > $level_count) {
            for ($i = 1; $i <= $this->levelConfig; $i++) {
                $model = AgentLevelModel::where(['site_id' => $this->_siteId, 'level' => $i])->first();
                if (!$model) {
                    $level_model = new AgentLevelModel();
                    $level_model->site_id = $this->_siteId;
                    $level_model->level = $i;
                    $level_model->save();
                }
            }
        }
    }

    /**
     * 获取所有等级模型或者某一个等级的模型
     * @param array $orderBy 排序规则
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getLevelModel($orderBy = ['level', 'desc'])
    {
        return AgentLevelModel::query()->where(['site_id' => $this->_siteId])->orderBy(...$orderBy)->get();
    }

    /**
     * 检测某分销商是否符合此等级的升级条件
     * @param int $memberId 会员id
     * @param string $upgrade 升级条件数据
     * @param array|int $productId 商品id
     * @param int $currentAgentLevel 当前会员的代理等级
     * @return bool
     */
    public function canUpgrade($memberId, $upgrade, $productId, $currentAgentLevel, $params)
    {
        if (!is_array($upgrade)) $upgrade = json_decode($upgrade, true);
        if (is_array($upgrade)) {
            $conditions = ['and' => [], 'or' => []];

            $necessaryConditions = []; // 必要条件
            $conditionCount = 0; // 其他开启的条件数量
            // 先按 and和or 分组 并把前提条件取出来
            foreach ($upgrade as $con) {
                $conIns = UpgradeConditionHelper::createInstance($con->type, $con->value, $productId);
                if (!$conIns->enabled()) continue;

                // 自身代理和自身分销等级条件为必要条件
                if (
                    $con->type == Constants::AgentLevelUpgradeCondition_SelfAgentLevel
                    || $con->type == Constants::AgentLevelUpgradeCondition_SelfDistributionLevel
                ) {
                    $necessaryConditions[] = $conIns;
                    continue;
                }
                $conditionCount++;
                // and和or条件分组
                $conditions[$con->logistic][] = $conIns;
            }
            // 只有必要条件 没有其他条件时 不可以升级
            if (!$conditionCount) return false;
            // 先满足前提条件 不满足的不可以升级
            foreach ($necessaryConditions as $item) {
                if (!$item->canUpgrade($memberId, ['currentAgentLevel' => $currentAgentLevel])) return false;
            }
            $andFlag = true;
            // 加载站点定制的and条件
            $pluginConditions = PluginHelper::loadUpgradeConditionPlugins(
                'AgentUpgradeCondition',
                $params['agent_level'],
                'and'
            );
            if ($pluginConditions) {
                $conditions['and'] = array_merge($conditions['and'], $pluginConditions);
            }
            // 执行and条件
            foreach ($conditions['and'] as $and) {
                // 只要有一个and条件不满足 则整个都不会满足 直接返回false
                if (!$andFlag) return false;
                $params['currentAgentLevel'] = $currentAgentLevel;
                $andFlag = $andFlag && $and->canUpgrade($memberId, $params);
            }
            // 执行or条件
            // 加载站点定制的or条件
            $pluginConditions = PluginHelper::loadUpgradeConditionPlugins(
                'AgentUpgradeCondition',
                $params['agent_level'],
                'or'
            );
            if ($pluginConditions) {
                $conditions['or'] = array_merge($conditions['or'], $pluginConditions);
            }
            // 没有or条件的时候 or的计算结果默认为true 有的时候默认为false
            $orFlag = count($conditions['or']) === 0;
            foreach ($conditions['or'] as $or) {
                // 当or条件有一个满足时即可
                if ($orFlag) break;
                $params['currentAgentLevel'] = $currentAgentLevel;
                $orFlag = $orFlag || $or->canUpgrade($memberId, $params);
            }
            // 如果没有or条件 and条件自己成立即可
            return $orFlag && $andFlag;
        } else {
            return false;
        }
    }

    /**
     * 获取升级条件快照的文案
     * @param $upgrade
     * @param $productId
     * @return array
     */
    public static function getLevelConditionsTitle($upgrade, $productId, $params)
    {
        $conditions = ['and' => [], 'or' => []];
        // 按 and和or 分组
        foreach ($upgrade as $con) {
            $conIns = UpgradeConditionHelper::createInstance($con->type, $con->value, $productId);
            if (!$conIns->enabled()) continue;
            $title = $conIns->getTitle();
            // and和or条件分组
            if ($title) {
                $conditions[$con->logistic][] = $title;
            }
        }
        // 加载站点定制的or条件
        $pluginConditionsOr = PluginHelper::loadUpgradeConditionPlugins(
            'AgentUpgradeCondition',
            $params['agent_level'],
            'or'
        );
        // 加载站点定制的and条件
        $pluginConditionsAnd = PluginHelper::loadUpgradeConditionPlugins(
            'AgentUpgradeCondition',
            $params['agent_level'],
            'and'
        );
        if ($pluginConditionsOr) {
            foreach ($pluginConditionsOr as $orItem) {
                $conditions['or'][] = $orItem->getTitle();
            }

        }
        if ($pluginConditionsAnd) {
            foreach ($pluginConditionsAnd as $andItem) {
                $conditions['and'][] = $andItem->getTitle();
            }
        }
        return $conditions;
    }

    /**
     * 保存某个等级的内容
     * @param array $info 设置内容
     */
    public function save(array $info)
    {
        (new AgentLevelModel())->updateBatch($info);
    }

    public static function getAgentList($params = [])
    {
        if ($params['member_id']) {
            $member = (new Member($params['member_id']))->getModel();
            $agentLevel = $member->agent_level;
        }
        $agentLevelList = Constants::getAgentLevelList();
        if ($agentLevel) {
            foreach ($agentLevelList as &$item) {
                if ($agentLevel == $item['id']) $item['is_check'] = 1;
                else $item['is_check'] = 0;
            }
        }

        return $agentLevelList;
    }

}
