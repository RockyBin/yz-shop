<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Model\AgentPerformanceRewardSettingModel;
use YZ\Core\Site\Site;

/**
 * 团队代理推荐奖设置
 */
class AgentPerformanceRewardSetting
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
            $this->_model = new AgentPerformanceRewardSettingModel();
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
        return AgentPerformanceRewardSettingModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->first();
    }

    /**
     * 返回设置
     * @return null
     */
    public static function getCurrentSiteSetting()
    {
        $setting = new AgentPerformanceRewardSetting();
        $model = $setting->getModel();
        $baseSetting = AgentBaseSetting::getCurrentSiteSetting();
        //分佣层级等于0的时候代表关闭了功能
        $model->baseSetting = $baseSetting->level == 0 ? false : true;
        return $model;
    }
}
