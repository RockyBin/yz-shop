<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;

/**
 * 团队代理关于订单销售奖(平级奖/越级奖)佣金的配置类
 */
class AgentSaleRewardCommisionConfig
{

    /**
     * @var int 是否启用销售奖
     */
    public $enable = 0;

    /**
     * @var int 分润类型，0=按利润分，1=按售价分
     */
    public $type = 0;

    /**
     * @var int 金额类型，0=按金额的百分比算，1=固定金额
     */
    public $amountType = 0;

    /**
     * @var array 平级奖的分润额度，以等级ID为键，相应的额度为值，数据结构如下
     * [
     *    1 => 3, //一级平级代理拿的佣金比例或金额
     *    2 => 5, //二级平级代理拿的佣金比例或金额
     *    3 => 8, //三级平级代理拿的佣金比例或金额
     * ]
     */
    public $samelevels = [];

    /**
     * @var integer 越级奖的分润额度(佣金比例或金额)
     */
    public $lowlevel = 0;

    /**
     * @var integer 启用的代理等级
     */
    public $maxlevel = 0;

    /**
     *
     * @param int $amountType 分销金额类型，0=按金额的百分比算，1=固定金额
     * @param int $type 分润类型，0=按利润分，1=按售价分
     * @param array $sameLevelsCommision 平级佣金设置，格式如：[等级ID1 => 等级佣金1,等级ID2 => 等级佣金2,...] 目前等级ID固定为 1,2,3 分别表示一级代理,二级代理,三级代理
     * @param int $lowLevelCommision 越级奖的比例或金额
     */
    public function __construct($enable, $amountType = 0, $type = 0, $sameLevelsCommision = array(), $lowLevelCommision = 0)
    {
        $baseSetting = AgentBaseSetting::getCurrentSiteSetting();
        $this->enable = $enable;
        if ($baseSetting->level < 1) $this->enable = 0;
        $this->maxlevel = $baseSetting->level;
        $this->amountType = $amountType;
        $this->type = $type;
        if (is_array($sameLevelsCommision)) {
            foreach ($sameLevelsCommision as $lid => $val) {
                $this->samelevels[intval($lid)] = $val;
            }
        }
        $this->lowlevel = $lowLevelCommision;
    }

    /**
     * 获取内存中指定ID的等级分佣比例
     * @param int $levelId
     * @return int
     */
    public function getLevelCommision($levelId)
    {
        return $this->samelevels[intval($levelId)];
    }

    /**
     * 获取越级销售奖的比例或金额
     *
     * @return int
     */
    public function getLowLevelCommision()
    {
        return $this->lowlevel;
    }

    /**
     * 获取当前网站的代理销售奖分佣配置信息
     */
    public static function getGlobalCommisionConfig()
    {
        $setting = AgentSaleRewardSetting::getCurrentSiteSetting();
        $instance = new static($setting->enable, $setting->amount_type, $setting->commision_type, json_decode($setting->commision, true), $setting->lowcommision);
        return $instance;
    }

    /**
     * 获取指点定产品的代理销售奖分佣配置信息，暂时不支持，以后做，先返回总的配置
     * @param $skuId
     * @return AgentSaleRewardCommisionConfig|static
     */
    public static function getProductCommisionConfig($skuId)
    {
        $rule = ProductSkusModel::query()->where('id', $skuId)->value('agent_sale_reward_rule');
        $config = self::getGlobalCommisionConfig();
        // 寻找该SKU的自定义规则
        if ($rule > 0) {
            $priceRule = ProductPriceRuleModel::query()
                ->where('rule_for', intval($skuId))
                ->where('type', Constants::ProductPriceRuleType_AgentSaleReward)
                ->first();
            if ($priceRule) {
                $ruleInfo = json_decode($priceRule->rule_info, true);
                // 结算类型 暂时走通用的
                $type = $config->type;
                return new static(intval($config->enable), intval($ruleInfo['amountType']), intval($type), $ruleInfo['rule']['commission'], intval($ruleInfo['rule']['low_commission']));
            }
        }
        return $config;
    }
}