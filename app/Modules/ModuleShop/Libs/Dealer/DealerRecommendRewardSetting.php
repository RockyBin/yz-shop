<?php
/**
 * 经销商推荐奖设置业务逻辑
 */

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Model\DealerRecommendRewardSettingModel;
use YZ\Core\Site\Site;

/**
 * 经销商推荐奖设置
 */
class DealerRecommendRewardSetting
{
    private $_model = null;

    /**
     * 初始化
     * DealerRecommendRewardSetting constructor.
     */
    public function __construct()
    {
        $this->_model = $this->find();
        if (!$this->_model) {
            $this->_model = new DealerRecommendRewardSettingModel();
            $this->_model->fill([
                'site_id' => Site::getCurrentSite()->getSiteId(),
            ]);
            $this->_model->save();
            $this->_model = $this->find();
        }
    }

    /**
     * 获取模型
     * @return DealerRecommendRewardSettingModel|\Illuminate\Database\Eloquent\Model|null|object
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 保存数据
     * @param array $info
     * @throws \Exception
     */
    public function save(array $info)
    {
        $info = $this->saveBefore($info);
        $this->_model->fill($info);
        $this->_model->save();
    }

    /**
     * 保存前的数据检测处理
     * @param array $info
     * @return array
     * @throws \Exception
     */
    public function saveBefore(array $info)
    {
        if (!$info) {
            throw new \Exception('请输入设置项');
        }
        if (!$info['reward_rule'] || !is_array($info['reward_rule'])) {
            $info['reward_rule'] = [];
        }
        foreach ($info['reward_rule'] as &$item) {
            $item['money'] = moneyYuan2Cent($item['money']);
        }
        $info['reward_rule'] = json_encode($info['reward_rule']);
        return $info;
    }

    /**
     * 查询数据
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function find()
    {
        return DealerRecommendRewardSettingModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->first();
    }

    /**
     * 返回设置
     * @return null
     */
    public static function getCurrentSiteSetting()
    {
        $setting = new DealerRecommendRewardSetting();
        $model = $setting->getModel();
        return $model;
    }
}
