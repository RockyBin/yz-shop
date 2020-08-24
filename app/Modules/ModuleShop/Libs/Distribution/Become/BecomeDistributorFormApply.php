<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;

/**
 * 提交申请成为分销商
 * Class BecomeDistributorFormApply
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
class BecomeDistributorFormApply extends AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_Apply;

    /**
     * 实例化
     * BecomeDistributorFormApply constructor.
     * @param $memberModal
     * @param DistributionSetting|null $distributionSetting
     */
    public function __construct($memberModal, DistributionSetting $distributionSetting = null)
    {
        parent::__construct($memberModal, $distributionSetting);
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 至少有一个值
        if (count($this->extendData) == 0) {
            $this->errorMsg = trans('shop-front.distributor.need_form');
            return false;
        }
        // 检查结构
        $checkResult = true;
        // 默认字段
        $commonKeys = [
            'company',
            'business_license',
            'business_license_file',
            'idcard',
            'idcard_file',
            'applicant',
            'mobile',
            'sex',
            'address',
            'remark',
        ];
        // 验证显示的且比填的字段必须填写
        if ($this->formSetting) {
            // 先检查固定字段
            if ($checkResult) {
                foreach ($commonKeys as $commonKey) {
                    if (in_array($commonKey, ['company', 'business_license', 'business_license_file']) && $this->extendData['business_type']===0) {
                        // 如果是个人，不验证公司相关信息
                        continue;
                    }
                    if ($this->formSetting[$commonKey . '_show'] && $this->formSetting[$commonKey . '_require']) {
                        $value = $this->extendData[$commonKey] . '';
                        if (strlen($value) == 0) {
                            $this->errorMsg = trans('shop-front.distributor.form_need.' . $commonKey);
                            $checkResult = false;
                            break;
                        }
                    }
                }
            }
            // 再检查自定义字段
            if ($checkResult) {
                // 分析扩展字段
                if ($this->formSetting['extend_fields']) {
                    // 构造数据
                    $extendFieldDatas = [];
                    if ($this->extendData['extend_fields']) {
                        $arrayTemp = json_decode($this->extendData['extend_fields'], true);
                        foreach ($arrayTemp as $arrayTempItem) {
                            if (!$arrayTempItem['name']) continue;
                            $extendFieldDatas[$arrayTempItem['name']] = $arrayTempItem;
                        }
                    }
                    // 检查
                    $extendFields = json_decode($this->formSetting['extend_fields'], true);
                    foreach ($extendFields as $extendField) {
                        if (trim($extendField['name']) && $extendField['show'] && $extendField['require']) {
                            $value = $extendFieldDatas[$extendField['name']]['value'];
                            if (strlen($value) == 0) {
                                $this->errorMsg = $this->errorMsg = trans('shop-front.distributor.extend') . $extendField['name'];
                                $checkResult = false;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $checkResult;
    }
}