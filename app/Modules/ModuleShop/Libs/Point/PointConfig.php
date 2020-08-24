<?php

namespace App\Modules\ModuleShop\Libs\Point;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\PointConfigModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;

/**
 * 积分配置业务类
 */
class PointConfig
{
    private $_model = null;
    private $_siteId = 0;
    private $_pointRule = null;

    /**
     * 初始化
     * PointConfig constructor.
     */
    public function __construct($siteIDOrModel)
    {
        if ($siteIDOrModel) {
            if (is_numeric($siteIDOrModel)) {
                $this->_siteId = $siteIDOrModel;
                $this->find($siteIDOrModel);
            } else {
                $this->init($siteIDOrModel);
            }
        }
    }

    /**
     * 返回模型
     * @return null
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        return $this->_model ? true : false;
    }

    /**
     * 保存（新建或修改）
     * @param array $data
     * @return bool
     */
    public function save(array $data)
    {
        if (!$this->checkExist()) {
            $this->_model = new PointConfigModel();
            $this->_model->site_id = $this->_siteId;
            $this->_pointRule = new ProductPriceRuleModel();
            $this->_pointRule->site_id = $this->_siteId;
            $this->_pointRule->type = Constants::ProductPriceRuleType_Point;
            $this->_pointRule->rule_for = 0;
        }
        // 积分抵扣规则
        $rule = [
            // 多少积分抵扣1元
            'out_order_pay_point' => isset($data['out_order_pay_point']) ? $data['out_order_pay_point'] : 0,
            // 订单最多抵扣的百分比 或者 固定金额 分
            'out_order_pay_max_percent' => isset($data['out_order_pay_max_percent']) ? $data['out_order_pay_max_percent'] : 0,
            // 最多抵扣限制类型 0 为百分比 1为固定金额 分
            'out_order_pay_type' => isset($data['out_order_pay_type']) ? $data['out_order_pay_type'] : 0
        ];
        unset($data['out_order_pay_point']);
        unset($data['out_order_pay_max_percent']);
        unset($data['out_order_pay_type']);
        $this->_pointRule->rule_info = json_encode($rule);
        $this->_pointRule->save();
        $this->_model->fill($data);
        $this->_model->save();
    }

    /**
     * 获取配置，如果没有，则新建一个再返回
     * @return null
     */
    public function getInfo()
    {
        if (!$this->checkExist()) {
            $this->save(['']);
            $this->find($this->_siteId);
        }
        $pointConfig = $this->getModel()->toArray();
        $ruleInfo = json_decode($this->_pointRule->rule_info, true);
        return array_merge($pointConfig, $ruleInfo);
    }

    /**
     * 查找配置
     * @param $siteID
     */
    private function find($siteID)
    {
        $model = PointConfigModel::where([
            ['site_id', $siteID]
        ])->first();
        $this->init($model);
    }

    /**
     * 初始化
     * @param $model
     */
    private function init($model)
    {
        $this->_model = $model;
        if ($this->checkExist()) {
            $this->_siteId = $this->_model->site_id;
            $this->_pointRule = ProductPriceRuleModel::where('site_id', $this->_siteId)
                ->where('type', Constants::ProductPriceRuleType_Point)
                ->where('rule_for', 0)
                ->first();
        }
    }

    /**
     * 获取积分抵扣规则 方便以后扩展使用
     * @param int $ruleId   如果是0 则为通用规则  不为0 则是对应的规则id
     * @return array|mixed
     */
    public function getPointRule($ruleId = 0)
    {
        $rule = ProductPriceRuleModel::query()
            ->where('type', Constants::ProductPriceRuleType_Point)
            ->where('site_id', $this->_siteId);
        if ($ruleId > 0) {
            $rule->where('id', $ruleId);
        } else {
            $rule->where('rule_for', 0);
        }
        $ruleInfo = $rule->first();
        if ($ruleInfo) {
            return json_decode($ruleInfo->rule_info, true);
        } else {
            // 默认值
            return [
                // 多少积分抵扣1元
                'out_order_pay_point' => 0,
                // 订单最多抵扣的百分比 或者 固定金额 分
                'out_order_pay_max_percent' => 0,
                // 最多抵扣限制类型 0 为百分比 1为固定金额 分
                'out_order_pay_type' => 0
            ];
        }
    }
}