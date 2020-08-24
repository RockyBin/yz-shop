<?php

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\DealerApplySettingModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use Illuminate\Support\Collection;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class DealerApplySetting
{
    protected $_model = null;
    protected $_siteId = 0;

    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $model = DealerApplySettingModel::find($this->_siteId);
        if (!$model) {
            $model = new DealerApplySettingModel();
            $model->site_id = $this->_siteId;
            $model->save();
            // 拿一下最新的数据
            $model = DealerApplySettingModel::find($this->_siteId);
        }
        $this->_model = $model;
        if(!$this->_model->can_invite_setting){
            $this->_model->can_invite_setting = '{"up_levels":1,"same_levels":1,"sub_levels":1}';
        }
    }

    public function getInfo()
    {
        return $this->_model->toArray();
    }

    public function save($data)
    {
        // 检测字段是否显示
        if (!$this->checkFormFieldShow($data)) {
            throw new \Exception("至少要有一个字段显示");
        }
        // 检测协议是否为空
        if ($data['agreement_show'] && !$data['agreement']) {
            throw new \Exception("请输入加盟协议");
        }
        if (isset($data['site_id'])) {
            unset($data['site_id']);
        }
        $data['can_apply_level'] = json_encode($data['can_apply_level']);
        $data['can_invite_level'] = json_encode($data['can_invite_level']);
        $data['can_invite_setting'] = json_encode($data['can_invite_setting']);
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
        if ($this->_model->status == Constants::DealerApplyStatus_Open) {
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
     * 获取是否开启了经销商加盟
     * @return bool
     */
    public function getApplyStatus()
    {
        if ($this->_model->status == Constants::AgentApplyStatus_Open) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取是否开启了邀请成为经销商
     * @return bool
     */
    public function canInvite()
    {
        return $this->_model->can_invite && $this->_model->status;
    }

    /**
     * 获取可以主动申请加盟的等级
     * @return \Illuminate\Database\Eloquent\Collection|static|static[]
     */
    public function getCanApplyLevel()
    {
        $levels = DealerLevelModel::query()->where(['site_id' => $this->_siteId, 'parent_id' => 0, 'status' => 1])->orderBy('weight', 'desc')->get();
        if ($this->_model->can_invite_level) {
            $openLevels = json_decode($this->_model->can_apply_level, true);
            if (is_array($openLevels)) {
                // 如果不用values会因为键的不连续导致出来的数据是个对象不是个数组
                $levels = $levels->whereIn('id', $openLevels)->values()->all();
                $levels = new Collection($levels);
            }
        }
        return $levels;
    }

    /**
     * 获取可以邀请加盟的等级
     * @return \Illuminate\Database\Eloquent\Collection|static|static[]
     */
    public function getCanInviteLevel()
    {
        $levels = DealerLevelModel::query()->where(['site_id' => $this->_siteId, 'parent_id' => 0, 'status' => 1])->orderBy('weight', 'desc')->get();
        if ($this->_model->can_invite_level) {
            $openLevels = json_decode($this->_model->can_invite_level, true);
            if (is_array($openLevels)) {
                $levels = $levels->whereIn('id', $openLevels)->values()->all();
                $levels = new Collection($levels);
            }
        }
        return $levels;
    }
}