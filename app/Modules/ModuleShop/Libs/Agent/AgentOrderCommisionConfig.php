<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;

/**
 * 团队代理关于订单正常佣金的配置类
 */
class AgentOrderCommisionConfig
{
    /**
     * @var int 分润类型，0=按利润分，1=按售价分
     */
    public $type = 0;

    /**
     * @var int 金额类型，0=按金额的百分比算，1=固定金额
     */
    public $amountType = 0;

    /**
     * 分佣最多分几级
     *
     * @var integer
     */
    public $maxlevel = 0;

    /**
     * 分红模式 分红模式，0=固定模式，1=逐级模式
     *
     * @var integer
     */
    public $bonusMode = 0;

    /**
     * @var array 各个代理等级的分润额度，以等级ID为键，相应的额度为值，数据结构如下
     * [
     *    1 => 3, //一级代理拿的佣金比例或金额
     *    2 => 5, //二级代理拿的佣金比例或金额
     *    3 => 8, //三级代理拿的佣金比例或金额
     * ]
     */
    public $levels = [];

    /**
     * AgentOrderCommisionConfig constructor.
     * @param $maxLevel
     * @param int $bonusMode 分红模式 分红模式，0=固定模式，1=逐级模式
     * @param int $amountType 分销金额类型，0=按金额的百分比算，1=固定金额
     * @param int $type 分润类型，0=按利润分，1=按售价分
     * @param array $commision 佣金设置，格式如：[等级ID1 => 等级佣金1,等级ID2 => 等级佣金2,...] 目前等级ID固定为 1,2,3 分别表示一级代理,二级代理,三级代理
     */
    public function __construct($maxLevel, $bonusMode = 0,$amountType = 0, $type = 0, $commision = array())
    {
        $this->maxlevel = $maxLevel;
        $this->bonusMode = $bonusMode;
        $this->amountType = $amountType;
        $this->type = $type;
        if (is_array($commision)) {
            foreach ($commision as $lid => $val) {
                $this->levels[intval($lid)] = $val;
            }
        }
    }

    /**
     * 获取内存中指定ID的等级分佣比例
     * @param int $levelId
     * @return int
     */
    public function getLevelCommision($levelId)
    {
        return $this->levels[intval($levelId)];
    }

    /**
     * 获取当前网站的代理分佣配置信息
     */
    public static function getGlobalCommisionConfig()
    {
        $setting = AgentBaseSetting::getCurrentSiteSetting();
        $instance = new static($setting->level, $setting->bonus_mode, $setting->amount_type, $setting->commision_type, json_decode($setting->commision, true));
        return $instance;
    }

    /**
     * 获取指点定产品的代理分佣配置信息，暂时不支持，以后做，先返回总的配置
     * @param $skuId
     * @return AgentOrderCommisionConfig|static
     */
    public static function getProductCommisionConfig($skuId)
    {
        $rule = ProductSkusModel::query()->where('id', $skuId)->value('agent_order_commission_rule');
        $config = self::getGlobalCommisionConfig();
        // 寻找该SKU的自定义规则
        if ($rule > 0) {
            $priceRule = ProductPriceRuleModel::query()
                ->where('rule_for', intval($skuId))
                ->where('type', Constants::ProductPriceRuleType_AgentOrderCommision)
                ->first();
            if ($priceRule) {
                $ruleInfo = json_decode($priceRule->rule_info, true);
                // 结算类型 暂时走通用的
                $type = $config->type;
                return new static(intval($config->maxlevel), $config->bonusMode, intval($ruleInfo['amountType']), intval($type), $ruleInfo['rule']['commission']);
            }
        }
        return $config;
    }
}