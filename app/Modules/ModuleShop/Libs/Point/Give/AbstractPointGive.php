<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use YZ\Core\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Point\Point;
use App\Modules\ModuleShop\Libs\Point\PointConfig;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use YZ\Core\Common\DataCache;

abstract class AbstractPointGive
{
    protected $member;
    protected $point;
    protected $pointConfig;

    protected $statusColumnName = ''; // 状态对应的数据库字段
    protected $pointColumnName = '';  // 赠送积分对应的数据库字段

    private $terminalType = -1;

    /**
     * 积分赠送抽象类
     * AbstractPointGive constructor.
     * @param $memberModal
     */
    public function __construct($memberModal)
    {
        $this->member = new Member($memberModal);
        $this->point = new Point($this->member->getSiteID());
        $this->pointConfig = new PointConfig($this->member->getSiteID());
    }

    /**
     * 设置终端类型
     * @param $terminalType
     */
    public function setTerminalType($terminalType)
    {
        if (is_numeric($terminalType)) {
            $this->terminalType = intval($terminalType);
        }
    }

    /**
     * 获取终端类型
     * @return int
     */
    public function getTerminalType()
    {
        return $this->terminalType;
    }

    /**
     * 自定义规则的具体实现
     * @return mixed
     */
    abstract protected function customRule();

    /**
     * 添加积分的具体实现
     * @return mixed
     */
    abstract protected function addPointHandle();

    /**
     * 添加积分
     */
    public function addPoint()
    {
        // 如果没有设置特定的终端类型，已当前环境为准
        if ($this->terminalType == -1) {
            $this->terminalType = getCurrentTerminal();
        }

        // 规则检查通过，则添加积分
        if ($this->Enable()) {
            return $this->addPointHandle();
        }
        return false;
    }

    /**
     * 检查规则
     * @return bool
     */
    public function Enable()
    {
        // 检查会员是否存在
        if (!$this->member || !$this->member->checkExist()) {
            return false;
        }
        // 检查终端
        if (!$this->checkTerminal()) {
            return false;
        }
        // 检查 状态设置 和 积分设置
        if (!$this->getStatus() || !$this->getPoint()) {
            return false;
        }
        // 如果是后台产生的，不发放
        if (DataCache::getData(LibsConstants::GlobalsKey_PointAtAdmin)) {
            return false;
        }
        // 检查自定义规则
        if (!$this->customRule()) {
            return false;
        }

        return true;
    }

    /**
     * 获取赠送积分的的配置值
     * @return int
     */
    public function getPoint()
    {
        $pointModel = $this->pointConfig->getModel();
        if ($pointModel) {
            return intval($pointModel[$this->pointColumnName]);
        }
        return 0;
    }

    /**
     * 获取积分配置的模型
     * @return null
     */
    public function getPointConfig()
    {
        return $this->pointConfig->getModel();
    }

    /**
     * 获取总状态 以及 状态的配置值
     * @return int
     */
    private function getStatus()
    {
        $pointModel = $this->pointConfig->getModel();
        if ($pointModel) {
            if ($pointModel->status && $pointModel[$this->statusColumnName]) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查终端
     * 暂且去掉终端的判断（2019-08-13 Aison）
     * @return bool
     */
    private function checkTerminal()
    {
        return true;
//        if (!$this->pointConfig || !$this->pointConfig->getModel()) return false;
//        $pointConfigModel = $this->pointConfig->getModel();
//        if ($this->terminalType == Constants::TerminalType_PC && $pointConfigModel->terminal_pc) {
//            return true;
//        }
//        if ($this->terminalType == Constants::TerminalType_WxOfficialAccount && $pointConfigModel->terminal_wx) {
//            return true;
//        }
//        if ($this->terminalType == Constants::TerminalType_WxApp && $pointConfigModel->terminal_wxapp) {
//            return true;
//        }
//        if ($this->terminalType == Constants::TerminalType_Mobile && $pointConfigModel->terminal_mobile) {
//            return true;
//        }
//        return false;
    }
}