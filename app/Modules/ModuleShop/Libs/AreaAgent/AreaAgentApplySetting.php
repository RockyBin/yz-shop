<?php
/**
 * 区域代理加盟设置
 * User: liyaohui
 * Date: 2020/5/18
 * Time: 16:14
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


use App\Modules\ModuleShop\Libs\AreaAgent\Condition\BecomeConditionHelper;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplySettingModel;
use YZ\Core\Site\Site;


class AreaAgentApplySetting
{
    protected $_model = null;
    protected $_siteId = 0;

    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $model = AreaAgentApplySettingModel::find($this->_siteId);
        if (!$model) {
            $model = new AreaAgentApplySettingModel();
            $model->site_id = $this->_siteId;
            $model->apply_level = '[]';
            $model->save();
            // 拿一下最新的数据
            $model = AreaAgentApplySettingModel::find($this->_siteId);
        }
        $this->_model = $model;
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return $this->_model->toArray();
    }

    /**
     * 保存加盟设置
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function save($data)
    {
        if ($data['status'] == 1) {
            if (!$data['apply_level'] || !is_array($data['apply_level'])) {
                throw new \Exception("请选择可申请加盟的代理等级");
            }
            // 比对是否有不合法的等级
            $acceptableLevel = AreaAgentConstants::getAreaAgentAllLevel();
            $errorLevel = array_diff($data['apply_level'], $acceptableLevel);
            if ($errorLevel) {
                throw new \Exception("代理等级错误");
            }

            // 检测字段是否显示
            if (!$this->checkFormFieldShow($data)) {
                throw new \Exception("至少要有一个字段显示");
            }
            // 检测协议是否为空
            if ($data['agreement_show'] && !$data['agreement']) {
                throw new \Exception("请输入加盟协议");
            }
        }
        if (isset($data['site_id'])) {
            unset($data['site_id']);
        }
        $data['apply_level'] = json_encode($data['apply_level']);
        $data['extend_fields'] = $data['extend_fields'] ? json_encode($data['extend_fields']) : NULL;
        // 自身等级条件
        // 结构如下：
        /*$selfLevel = [
            10 => [
                'distribution' => [
                    'level' => [1,2,3],
                    'logistic' => 'or'
                ],
                'agent' => [
                    'level' => [1,2,3],
                    'logistic' => 'and'
                ]
            ]
        ];*/
        $data['self_level'] = $data['self_level'] ? json_encode($data['self_level']) : NULL;
        return $this->_model->fill($data)->save();
    }

    /**
     * 检测代理申请表单是否有字段显示
     * @param $data
     * @return bool
     */
    public function checkFormFieldShow($data)
    {
        // 是否开启了字段显示
        $fieldShow = false;
        foreach ($data as $key => $val) {
            if (substr($key, -5) == '_show' && $val == 1) {
                $fieldShow = true;
                break;
            }
        }
        // 预设的字段没有显示 检测一下是否有自定义的字段
        if (!$fieldShow && $data['extend_fields']) {
            foreach ($data['extend_fields'] as $val) {
                if ($val['show'] == 1) {
                    $fieldShow = true;
                    break;
                }
            }
        }
        return $fieldShow;
    }

    /**
     * 获取代理申请表单
     * @return array
     */
    public function getApplyForm()
    {
        $fields = [];
        $extendFields = [];
        $agreement = ['show' => 0];
        if ($this->_model->status == 1) {
            // 预设字段
            foreach ($this->_model->toArray() as $key => $val) {
                if (substr($key, -5) == '_show' && $val == 1) {
                    $fieldName = str_replace('_show', '', $key);
                    $fields[$fieldName] = $this->_model->{$fieldName . '_require'};
                }
            }
            unset($fields['agreement']);
            // 自定义字段
            if ($extend = json_decode($this->_model->extend_fields, true)) {
                foreach ($extend as $field) {
                    if ($field['show']) {
                        $extendFields[] = $field;
                    }
                }
            }
            // 协议
            if ($this->_model->agreement_show) {
                $agreement['show'] = 1;
                $agreement['content'] = $this->_model->agreement;
            }

        }
        return ['defaultFields' => $fields, 'extendFields' => $extendFields, 'agreement' => $agreement];
    }

    /**
     * 获取是否开启了代理申请
     * @return bool
     */
    public function getApplyStatus()
    {
        return $this->_model->status == 1;
    }

    /**
     * 获取可以申请加盟的等级
     * @return mixed
     */
    public function getCanApplyLevel()
    {
        return $this->_model->apply_level;
    }

    /**
     * 获取会员可以申请加盟的等级以及对应的状态
     * @param $member_id
     * @return array $memberApplyLevel = ['10'=>0,'9'=>1]; 10代表省代，可以去看常量表
     *  ['10'=>[status,desc],'9'=>['']]
     *
     */
    public function getMemberApplyLevel($memberId)
    {
        // 允许加盟的代理
        $applyLevel = json_decode($this->_model->apply_level, true);
        // 加盟的条件
        $selfLevel = json_decode($this->_model->self_level, true);

        $memberApplyLevel = [];
        foreach ($selfLevel as $araeAgentLevel => $conditionItem) {
            $conditions = ['and' => [], 'or' => []];
            if (in_array($araeAgentLevel, $applyLevel)) {
                $memberApplyLevel[$araeAgentLevel]['desc'] = ['and' => [], 'or' => []];
                //这里排序暂时用 ASCII来排序，后期有需要再进行修改，暂不做复杂的操作
                ksort($conditionItem);
                foreach ($conditionItem as $key => $val) {
                    if ($val['value']) {
                        $conI = BecomeConditionHelper::createInstance($key, $val['value']);
                        $conditions[$val['logistic']][] = $conI;
                        array_push($memberApplyLevel[$araeAgentLevel]['desc'][$val['logistic']], $conI->getTitle());
                    }
                }
                // 执行and条件
                $andFlag = true;
                foreach ($conditions['and'] as $and) {
                    // 只要有一个and条件不满足 则整个都不会满足 直接返回false
                    if (!$andFlag) {
                        $andFlag = false;
                        break;
                    }
                    $andFlag = $andFlag && $and->canUpgrade($memberId);
                }
                // 没有or条件的时候 or的计算结果默认为true 有的时候默认为false
                $orFlag = count($conditions['or']) === 0;
                foreach ($conditions['or'] as $or) {
                    // 当or条件有一个满足时即可
                    if ($orFlag) break;
                    $orFlag = $orFlag || $or->canUpgrade($memberId);
                }
                if ($orFlag && $andFlag) $memberApplyLevel[$araeAgentLevel]['status'] = 1;
                else $memberApplyLevel[$araeAgentLevel]['status'] = 0;
            }
        }
        return $memberApplyLevel;
    }
}