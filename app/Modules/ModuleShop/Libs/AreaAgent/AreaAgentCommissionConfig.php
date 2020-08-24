<?php

namespace App\Modules\ModuleShop\Libs\AreaAgent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentBaseSettingModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Model\FinanceModel;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use YZ\Core\Site\Site;

/**
 * 区域代理分红设置
 */
class AreaAgentCommissionConfig
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
     * 分佣模式，0=级差模式，1=每级固定模式
     * @var int
     */
    public $commissionMode = 0;

    /**
     * @var array 各个区域等级的分润额度，以等级ID为键，相应的省/市/区的英文单词作为子键，额度为值，数据结构如下
     * [
     *    levelId1 => [
     *      'province' => 20,
     *      'city' => 15,
     *      'district' => 10
     *    ],
     *    levelId2 => [
     *      'province' => 20,
     *      'city' => 15,
     *      'district' => 10
     *    ],
     * ]
     */
    public $levels = [];

    /**
     *
     * @param type $amountType 分销金额类型，0=按金额的百分比算，1=固定金额
     * @param type $type 分润类型，0=按利润分，1=按售价分
     */
    public function __construct($amountType = 0)
    {
        $this->amountType = $amountType;
        $setting = AreaAgentBaseSettingModel::query()->where('site_id',getCurrentSiteId())->first();
        $this->type = $setting->commision_type;
        $this->internalPurchase = $setting->internal_purchase;
    }

    /**
     * 添加等级配置到内存中
     * @param $levelId 等级ID，一般为后台等级配置表中的主键
     * @param $commission 相应等级的区域分佣配置比例
     * @param $amountType 佣金类型 按比例还是固定值
     * @param int $isDefault 是否为默认等级
     */
    public function addLevel($levelId, $commission, $amountType, $isDefault = 0)
    {
        if (is_string($commission)) $commission = json_decode($commission, true);
        //下标为0固定为默认等级
        if ($isDefault) $this->levels[0] = ['commission' => $commission, 'amountType' => $amountType];
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
    public static function getGlobalCommissionConfig()
    {
        $levels = BaseModel::runSql('select * from tbl_area_agent_level where status = 1 and site_id = '.getCurrentSiteId());
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
    public static function getProductCommissionConfig($skuId)
    {
        $rule = ProductSkusModel::query()->where('id', $skuId)->value('area_agent_rule');
        if ($rule > 0) {
            $PriceRuleModel = new ProductPriceRuleModel();
            //寻找该SKU的分销自定义规则
            $commissionRule = $PriceRuleModel->where(['rule_for' => $skuId,'type' => Constants::ProductPriceRuleType_AreaAgent])->first();
            if ($commissionRule) {
                $levels = BaseModel::runSql('select * from tbl_area_agent_level where status = 1 and site_id = '.getCurrentSiteId());
                $ruleInfo = json_decode($commissionRule->rule_info,true);
                $instance = new static($ruleInfo['amountType']);
                $rule = $ruleInfo['rule'];
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
                    $globalConfig = self::getGlobalCommissionConfig();
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

        return self::getGlobalCommissionConfig();
    }
}