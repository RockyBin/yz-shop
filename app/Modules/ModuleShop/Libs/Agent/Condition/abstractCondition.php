<?php
/**
 * 代理升级的抽象类 用于定义一些公共的方法
 * User: liyaohui
 * Date: 2019/8/8
 * Time: 18:13
 */

namespace App\Modules\ModuleShop\Libs\Agent\Condition;


abstract class abstractCondition implements ICondition
{
    protected $value = '';
    protected $name = '';
    protected $productid = 0;
    protected $onlyAgent = false; // 只允许代理身份升级
    protected $textValue = ''; // 用于生成快照
    public $unit = '人';// 满足条件的单位

    /**
     * 是否需要检测代理身份
     * @param $params
     * @return bool
     */
    public function checkIsAgent($params)
    {
        // 只允许代理身份的 需要判断当前代理等级
        if ($this->onlyAgent) {
            if (isset($params['currentAgentLevel']) && $params['currentAgentLevel'] > 0) {
                return true;
            }
            return false;
        }
        return true;
    }
    /**
     * 此升级条件是否可用
     * @return bool
     */
    public function enabled()
    {
        if ($this->value === null || $this->value === '') {
            return false;
        } else {
            return true;
        }
    }
    /**
     * 获取此升级条件的类型名称
     * @return string
     */
    public function getTypeName() {
        return $this->name;
    }

    public function getTitle()
    {
        $value = $this->textValue ?: $this->value;
        $value = $this->unit == '元' ? moneyCent2Yuan($value) : $value;
        return $this->name . $value . $this->unit;
    }

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getDesc() {
        return $this->getTypeName();
    }

    /**
     * 判断某代理商是否满足此分销条件
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     */
    abstract public function canUpgrade($memberId, $params = []);
}