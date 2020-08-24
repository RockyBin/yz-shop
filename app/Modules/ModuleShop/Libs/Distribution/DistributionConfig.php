<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;

/**
 * 分等级的佣金配置，佣金配置从整站的分销得等级设置或产品的分销等级设置获取
 * Class DistributionConfig
 * @package App\Modules\ModuleShop\Libs\Distribution
 */
class DistributionConfig
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
     * @var int 是否开启分销内购
     */
    public $internalPurchase = 0;

    /**
     * @var int 分销最大层级
     */
    public $maxLevel = 3;

    /**
     * @var array 各个层级的分润额度，以层级ID为键，相应的额度为值，数据结构如下
     * [
     *    levelId1 => [
     *      '1' => 20,
     *      '2' => 15,
     *      '3' => 10
     *    ],
     *    levelId2 => [
     *      '1' => 20,
     *      '2' => 15,
     *      '3' => 10
     *    ],
     * ]
     */
    public $levels = [];

    /**
     *
     * @param type $amountType 分销金额类型，0=按金额的百分比算，1=固定金额
     * @param type $type 分润类型，0=按利润分，1=按售价分
     */
    public function __construct($amountType = 0, $type = 0)
    {
        $this->amountType = $amountType;
        $this->type = $type;
        $setting = (new DistributionSetting())->getSettingModel();
        $this->maxLevel = $setting->level;
        $this->internalPurchase = $setting->internal_purchase;
    }

    /**
     * 添加等级配置到内存中
     * @param $levelId 等级ID，一般为后台分销等级配置表中的主键
     * @param $commission 相应等级的层级分佣配置比例
     * @param $amountType 佣金类型 按比例还是固定值
     * @param int $isdefault 是否为默认等级
     */
    public function addLevel($levelId, $commission, $amountType, $isdefault = 0)
    {
        if (is_string($commission)) $commission = json_decode($commission, true);
        //下标为0固定为默认等级
        if ($isdefault) $this->levels[0] = ['commission' => $commission, 'amountType' => $amountType];
        $this->levels[intval($levelId)] = ['commission' => $commission, 'amountType' => $amountType];
    }

    /**
     * 获取内存中指定ID的等级分佣比例
     * @param $levelId
     * @return mixed
     */
    public function getLevelCommission($levelId)
    {
        return $this->levels[intval($levelId)];
    }

    /**
     * 获取当前网站的分佣配置信息
     */
    public static function getGlobalDistributionConfig()
    {
        $levels = DistributionLevel::getList();
        $instance = new static();
        foreach ($levels as $level) {
            if ($level->status == 1) {
                $instance->addLevel($level->id, $level->commission, 0, $level->weight == 0);
            }
        }
        return $instance;
    }


    /**
     * 获取指点定产品的分佣配置信息
     */
    public static function getProductDistributionConfig($skuId)
    {
        $rule = ProductSkusModel::query()->where('id', $skuId)->value('fenxiao_rule');
        if ($rule > 0) {
            $PriceRuleModel = new ProductPriceRuleModel();
            //寻找该SKU的分销自定义规则
            $distributionRule = $PriceRuleModel->where(['rule_for' => $skuId,'type'=>0])->first();
            if ($distributionRule) {
                $levels = DistributionLevel::getList();
                $ruleInfo = json_decode($distributionRule->rule_info,true);
                $instance = new static($ruleInfo['amountType'],$ruleInfo['type']);
                $rule=$ruleInfo['rule'];
                $noRuleLevel = []; // 自定义规则里 没有找到的等级id 这种情况一般是 后期新加了等级 但是商品自定义规则里没有同步增加
                foreach ($levels as $level) {
                    if ($level->status == 1 && $rule[$level->id]) {
                        $instance->addLevel($level->id, $rule[$level->id]['commission_rate'], $ruleInfo['amountType'], $level->weight == 0);
                    } else {
                        $noRuleLevel[] = $level;
                    }
                }
                // 如果有没有找到的自定义规则 则去拿全局的价格规则
                if ($noRuleLevel) {
                    $globalConfig = self::getGlobalDistributionConfig();
                    foreach ($noRuleLevel as $level) {
                        $config = $globalConfig->getLevelCommission($level->id);
                        $instance->addLevel(
                            $level->id,
                            $config['commission'],
                            $config['amountType'],
                            $level->weight == 0);
                    }
                }
                return $instance;
            }
        }

        return self::getGlobalDistributionConfig();

    }
}