<?php
/**
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Entities\DealerLevelEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardSettingEntity;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerOrderRewardSettingModel;
use Exception;
use YZ\Core\Services\BaseService;
use YZ\Core\Site\Site;
use YZ\Core\Traits\InjectTrait;

class DealerOrderRewardSettingService extends BaseService
{
    use InjectTrait;

    /**
     * @var DealerOrderRewardSettingModel
     */
    private $dealerOrderRewardSettingModel;
    /**
     * @var DealerLevelModel
     */
    private $dealerLevelModel;

    /**
     * DealerOrderRewardSettingService constructor.
     * @param DealerOrderRewardSettingModel $dealerOrderRewardSettingModel
     * @param DealerLevelModel $dealerLevelModel
     * @throws Exception
     */
    public function __construct(DealerOrderRewardSettingModel $dealerOrderRewardSettingModel, DealerLevelModel $dealerLevelModel)
    {
        parent::__construct();
        $this->initialize();
        $this->inject(get_defined_vars());
    }

    /**
     * Service初始化工作封装
     */
    protected function initialize()
    {
    }

    /**
     * @param DealerOrderRewardSettingEntity $siteSetting
     * @throws Exception
     */
    public function setRules(DealerOrderRewardSettingEntity $siteSetting)
    {
        $dealerLevelCollection = $this->dealerLevelModel->getListBySiteId($siteSetting->site_id);
        $originalRules = $siteSetting->reward_rule;
        $originalRules = is_null($originalRules) ? [] : $originalRules;
        $rules = [];

        $getLevels = function (int $id) use ($dealerLevelCollection) {
            return $dealerLevelCollection->where(DealerLevelEntity::PARENT_ID, '=', $id);
        };

        $addRule = function (DealerLevelEntity $dealerLevelEntity) use ($originalRules, &$rules) {
            foreach ($originalRules as $rule) {
                if ($rule->id === $dealerLevelEntity->id) {
                    $rules[] = (object)['id' => $dealerLevelEntity->id,
                        'parent_id' => $dealerLevelEntity->parent_id,
                        'has_hide' => $dealerLevelEntity->has_hide,
                        'name' => $dealerLevelEntity->name,
                        'is_hide' => $rule->is_hide,
                        'first_rate' => $rule->first_rate,
                        'rate' => $rule->rate];
                    return;
                }
            }
            $rules[] = (object)['id' => $dealerLevelEntity->id,
                'parent_id' => $dealerLevelEntity->parent_id,
                'has_hide' => $dealerLevelEntity->has_hide,
                'name' => $dealerLevelEntity->name,
                'is_hide' => $dealerLevelEntity->parent_id === 0 ? false : true,
                'first_rate' => 0,
                'rate' => 0];
        };

        foreach ($getLevels(0) as $baseLevelEntity) {
            /**
             * @var DealerLevelEntity $baseLevelEntity
             */
            $addRule($baseLevelEntity);

            // 不启用隐藏等级时跳过
            if (!$baseLevelEntity->has_hide) continue;

            foreach ($getLevels($baseLevelEntity->id) as $hideLevelEntity) {
                $addRule($hideLevelEntity);
            }
        }

        $siteSetting->reward_rule = $rules;
    }

    /**
     * 获取当前站点的订货返现奖设置
     * @transaction 事务注解
     * @return DealerOrderRewardSettingEntity
     * @throws Exception
     */
    public function getSettingBySite()
    {
        $currentSiteId = Site::getCurrentSite()->getSiteId();
        $setting = null;

        if (!$this->dealerOrderRewardSettingModel->checkExistBySiteId($currentSiteId)) {
            $setting = new DealerOrderRewardSettingEntity();
            $setting->site_id = $currentSiteId;
            $setting->reward_rule = [];
            $this->dealerOrderRewardSettingModel->addSingle($setting);
        } else {
            $setting = $this->dealerOrderRewardSettingModel->getSingleBySiteId($currentSiteId);
        }

        $this->setRules($setting);

        return $setting;
    }

    /**
     * 保存当前站点的订货返现奖设置
     * @transaction 事务注解
     * @param DealerOrderRewardSettingEntity $setting
     * @throws Exception
     */
    public function saveSetting(DealerOrderRewardSettingEntity $setting)
    {
        $removeNames = function (&$rules) {
            foreach ($rules as &$rule) {
                unset($rule->name);
            }
        };
        $removeNames($setting->reward_rule);

        $this->dealerOrderRewardSettingModel->updateSingle($setting);
    }
}