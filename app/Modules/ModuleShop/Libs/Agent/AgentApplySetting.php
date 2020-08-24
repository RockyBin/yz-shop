<?php
/**
 * User: liyaohui
 * Date: 2019/6/26
 * Time: 14:24
 */

namespace App\Modules\ModuleShop\Libs\Agent;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\AgentApplySettingModel;
use YZ\Core\Site\Site;

class AgentApplySetting
{
    protected $_model = null;
    protected $_siteId = 0;

    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $model = AgentApplySettingModel::find($this->_siteId);
        if (!$model) {
            $model = new AgentApplySettingModel();
            $model->site_id = $this->_siteId;
            // 根据开启的代理等级 生成默认数据
            $agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->level) {
                $level = [];
                for ($i = 1; $i <= $agentBaseSetting->level; $i++) {
                    $level[] = $i;
                }
                $model->agent_apply_level = json_encode($level);
            } else {
                // 代理功能关闭 默认为空
                $model->agent_apply_level = "";
            }
            $model->save();
            // 拿一下最新的数据
            $model = AgentApplySettingModel::find($this->_siteId);
        }
        $this->_model = $model;
    }

    public function getInfo()
    {
        return $this->_model->toArray();
    }

    public function save($data)
    {
        if ($data['agent_apply_status'] == Constants::AgentApplyStatus_Open) {
            if (!$data['agent_apply_level'] || !is_array($data['agent_apply_level'])) {
                throw new \Exception("请选择可申请加盟的代理等级");
            }
            // 比对是否有不合法的等级
            $acceptableLevel = [1, 2, 3];
            $errorLevel = array_diff($data['agent_apply_level'], $acceptableLevel);
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
        $data['agent_apply_level'] = json_encode($data['agent_apply_level']);
        $data['extend_fields'] = $data['extend_fields'] ? json_encode($data['extend_fields']) : NULL;
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
        if ($this->_model->agent_apply_status == Constants::AgentApplyStatus_Open) {
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
        if ($this->_model->agent_apply_status == Constants::AgentApplyStatus_Open) {
            return true;
        } else {
            return false;
        }
    }

    public function getCanApplyLevel()
    {
        return $this->_model->agent_apply_level;
    }
}