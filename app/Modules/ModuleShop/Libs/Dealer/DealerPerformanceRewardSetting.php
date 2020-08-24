<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardSettingModel;
use YZ\Core\Site\Site;

/**
 * 经销商推荐奖设置
 */
class DealerPerformanceRewardSetting
{
    private $_model = null;

    /**
     * 初始化
     * AgentPerformanceRewardSetting constructor.
     */
    public function __construct()
    {
        $this->_model = $this->find();
        if (!$this->_model) {
            $this->_model = new DealerPerformanceRewardSettingModel();
            $this->_model->fill([
                'site_id' => Site::getCurrentSite()->getSiteId(),
            ]);
            $this->_model->save();
            $this->_model = $this->find();
        }
    }

    /**
     * 获取模型
     * @return AgentPerformanceRewardSetting|\Illuminate\Database\Eloquent\Model|null|object
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 保存数据
     * @param array $info
     */
    public function save(array $info)
    {
        $this->_model->fill($info);
        $this->_model->save();
    }

    /**
     * 查询数据
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function find()
    {
        return DealerPerformanceRewardSettingModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->first();
    }

    /**
     * 返回设置
     * @return null
     */
    public static function getCurrentSiteSetting()
    {
        $setting = new DealerPerformanceRewardSetting();
        $model = $setting->getModel();
        return $model;
    }
}
