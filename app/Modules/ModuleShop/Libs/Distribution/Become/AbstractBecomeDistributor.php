<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Logger\Log;

/**
 * 申请成为分销商
 * Class AbstractBecomeDistributor
 * @package App\Modules\ModuleShop\Libs\Distribution\Become
 */
abstract class AbstractBecomeDistributor
{
    protected $conditionType = Constants::DistributionCondition_Error; // 成为分销商条件
    protected $member; // 会员
    protected $distributor; // 分销商
    protected $formSetting; // 分销商表单申请设置
    protected $setting; // 分销商设置
    protected $extendData = []; // 额外数据，会同时插入到 tbl_distributor，也会作为结果返回出来
    protected $errorMsg; // 错误信息
    protected $periodFlag = Constants::Period_Error; // 时期，付款后 或 维权期后
    private $terminalType = CodeConstants::TerminalType_Unknown; // 终端类型

    public function __construct(Member $member, DistributionSetting $distributionSetting = null)
    {
        if (is_null($distributionSetting)) {
            $distributionSetting = new DistributionSetting();
        }
        $this->setting = $distributionSetting->getSettingModel();
        $this->formSetting = $distributionSetting->getFormSettingModel();
        $this->member = $member;
        if ($this->member && $this->member->checkExist()) {
            $this->distributor = new Distributor($this->member->getMemberId());
        }
    }

    /**
     * 设置额外的数据
     * @param $data
     */
    public function setExtendData($data)
    {
        if (is_array($data)) {
            $this->extendData = array_merge($this->extendData, $data);
        }
    }

    /**
     * 获取额外的数据
     * @return array
     */
    public function getExtendData()
    {
        return $this->extendData;
    }

    /**
     * 设置终端类型
     * @param $terminalType
     */
    public function setTerminalType($terminalType)
    {
        $this->terminalType = intval($terminalType);
    }

    /**
     * 获取错误信息
     * @return mixed
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * 获取申请条件
     * @return int
     */
    public function getConditionType()
    {
        return $this->conditionType;
    }

    /**
     * 返回会员模型数据
     * @return int
     */
    public function getMemberModel()
    {
        if ($this->member && $this->member->checkExist()) {
            return $this->member->getModel();
        }
        return false;
    }

    /**
     * 是否满足条件
     * @return bool
     */
    public function match()
    {
        // 升级条件不一致
        if ($this->conditionType != intval($this->setting->condition)) {
            $this->errorMsg = 'err condition type';
            return false;
        }
        // 检查会员是否存在
        if (!$this->member || !$this->member->checkExist()) {
            $this->errorMsg = trans('shop-front.member.not_exist');
            return false;
        }
        // 如果已经是分销商了，则不能再申请了
        if ($this->member->isDistributor() || ($this->distributor && $this->distributor->isActive())) {
            $this->errorMsg = trans('shop-front.member.is_distributor');
            return false;
        }
        // 检查自定义规则
        return $this->customRule();
    }

    /**
     * 返回升级时期
     * @return int
     */
    public function getPeriodFlag()
    {
        return $this->periodFlag;
    }

    /**
     * 提交申请
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function apply()
    {
        try {
                if ($this->Match()) {
                $dbData = array_merge($this->extendData, [
                    'is_del' => Constants::DistributorIsDel_No,
//                    'show_in_review' => Constants::DistributionReviewShow_Yes,
                    'status' => Constants::DistributorStatus_WaitReview,
                    'apply_condition' => $this->makeApplyConditionString(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                if ($this->distributor->checkExist()) {
                    // 存在修改状态
                    $this->distributor->edit($dbData);
                } else {
                    // 不存在则创建数据
                    $dbData['member_id'] = $this->member->getMemberId();
                    $dbData['site_id'] = $this->member->getSiteID();
                    $dbData['terminal_type'] = $this->terminalType;
                    $this->distributor->add($dbData, true);
                }
                $this->errorMsg = '';
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 分销商审核通过
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function Active()
    {
        try {
            if ($this->Match()) {
                $dbData = array_merge($this->extendData, [
                    'status' => Constants::DistributorStatus_Active,
                    'is_del' => Constants::DistributorIsDel_No,
//                    'show_in_review' => Constants::DistributionReviewShow_Yes,
                    'passed_at' => date('Y-m-d H:i:s'),
                    'reject_reason' => '',
                    'apply_condition' => $this->makeApplyConditionString(),
                ]);
                if ($this->distributor->checkExist()) {
                    // 存在修改状态
                    $this->distributor->edit($dbData);
                } else {
                    // 不存在则创建数据
                    $dbData['member_id'] = $this->member->getMemberId();
                    $dbData['site_id'] = $this->member->getSiteID();
                    $dbData['terminal_type'] = $this->terminalType;
                    $dbData['created_at'] = date('Y-m-d H:i:s');
                    $this->distributor->add($dbData, true);
                }
                $this->errorMsg = '';
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 自动审核
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function autoCheck()
    {
        // 必须是自动审核
        if (intval($this->setting->review_type) != Constants::DistributionReviewType_Auto) {
            return false;
        }
        return $this->Active();
    }

    /**
     * 自定义规则的具体实现
     * @return mixed
     */
    abstract protected function customRule();

    /**
     * 构造申请条件JSON字符窜
     * @return string
     */
    private function makeApplyConditionString()
    {
        $settingData = $this->setting->toArray();
        unset($settingData['site_id']);
        if (intval($settingData['condition']) == Constants::DistributionCondition_Apply) {
            $formSettingData = $this->formSetting->toArray();
            unset($formSettingData['site_id']);
            unset($formSettingData['agreement']);
            if ($formSettingData['extend_fields']) {
                $formSettingData['extend_fields'] = json_decode($formSettingData['extend_fields'], true);
            }
            $settingData['form_setting'] = $formSettingData;
        }
        return json_encode($settingData);
    }
}