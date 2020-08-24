<?php


namespace App\Modules\ModuleShop\Libs\AreaAgent\Condition;


abstract class abstractCondition implements ICondition
{
    protected $value = '';
    protected $name = '';
    protected $textValue = ''; // 用于生成快照
    public $unit = '人';// 满足条件的单位

    /**
     * 获取此条件的类型名称
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
     * 获取此条件的说明文本
     * @return string
     */
    public function getDesc() {
        return $this->getTypeName();
    }

    /**
     * 判断某区域代理是否满足此分销条件
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     */
    abstract public function canUpgrade($memberId, $params = []);
}