<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

class EntityExecutionOptions
{
    /**
     * @var int 关联数据层级计数
     */
    public $relatedCount;
    /**
     * @var bool 使用隐藏属性开关
     */
    public $useHiddenProperty;
    /**
     * @var bool 使用属性事件开关
     */
    public $usePropertyEvent;
    /**
     * @var bool 加载预设属性事件开关
     */
    public $isLoadPresetPropertyEvent;
    /**
     * @var bool 使用关联数据开关
     */
    public $useRelatedData;
    /**
     * @var bool 加载预设关联数据开关
     */
    public $isLoadPresetRelatedData;
    /**
     * @var bool 使用填充事件开关
     */
    public $useFillEvent;
    /**
     * @var bool 加载预设填充事件开关
     */
    public $isLoadPresetFillEvent;
    /**
     * @var bool 使用输出事件开关
     */
    public $useOutputEvent;
    /**
     * @var bool 加载预设输出事件开关
     */
    public $isLoadPresetOutputEvent;

    /**
     * EntityExecutionOptions constructor.
     * @param int $relatedCount 关联数据层级计数
     * @param bool $useHiddenProperty 使用隐藏属性开关
     * @param bool $usePropertyEvent 使用属性事件开关
     * @param bool $isLoadPresetPropertyEvent 加载预设属性事件开关
     * @param bool $useRelatedData 使用关联数据开关
     * @param bool $isLoadPresetRelatedData 加载预设关联数据开关
     * @param bool $useFillEvent 使用填充事件开关
     * @param bool $isLoadPresetFillEvent 加载预设填充事件开关
     * @param bool $useOutputEvent 使用输出事件开关
     * @param bool $isLoadPresetOutputEvent 加载预设输出事件开关
     */
    public function __construct(int $relatedCount = 1,
                                bool $useHiddenProperty = true,
                                bool $usePropertyEvent = true,
                                bool $isLoadPresetPropertyEvent = true,
                                bool $useRelatedData = true,
                                bool $isLoadPresetRelatedData = true,
                                bool $useFillEvent = true,
                                bool $isLoadPresetFillEvent = true,
                                bool $useOutputEvent = true,
                                bool $isLoadPresetOutputEvent = true)
    {
        $this->relatedCount = $relatedCount;
        $this->useHiddenProperty = $useHiddenProperty;
        $this->usePropertyEvent = $usePropertyEvent;
        $this->isLoadPresetPropertyEvent = $isLoadPresetPropertyEvent;
        $this->useRelatedData = $useRelatedData;
        $this->isLoadPresetRelatedData = $isLoadPresetRelatedData;
        $this->useFillEvent = $useFillEvent;
        $this->isLoadPresetFillEvent = $isLoadPresetFillEvent;
        $this->useOutputEvent = $useOutputEvent;
        $this->isLoadPresetOutputEvent = $isLoadPresetOutputEvent;
    }

    /**
     * 创建一个关联数据层级计数为1，各开关都为false的实例
     * @return self
     */
    static public function createNotWorkingInstance(): self
    {
        return new self(1, false, false, false,
            false, false, false, false, false, false);
    }

    const NOT_WORKING = 0;
    const USE_HIDDEN_PROPERTY = 1 << 0;
    const USE_PROPERTY_EVENT = 1 << 1;
    const USE_RELATED_EVENT = 1 << 2;
    const USE_FILL_EVENT = 1 << 3;
    const USE_OUTPUT_EVENT = 1 << 4;
    const LOAD_PRESET_PROPERTY_EVENT = 1 << 5;
    const LOAD_PRESET_RELATED_EVENT = 1 << 6;
    const LOAD_PRESET_FILL_EVENT = 1 << 7;
    const LOAD_PRESET_OUTPUT_EVENT = 1 << 8;
    const ALL = self::USE_HIDDEN_PROPERTY | self::USE_PROPERTY_EVENT |
    self::USE_RELATED_EVENT | self::USE_FILL_EVENT | self::USE_OUTPUT_EVENT |
    self::LOAD_PRESET_PROPERTY_EVENT | self::LOAD_PRESET_RELATED_EVENT |
    self::LOAD_PRESET_FILL_EVENT | self::LOAD_PRESET_OUTPUT_EVENT;

    private $options = self::NOT_WORKING;

    public function addOptions(int $options)
    {
        $this->options |= $options;
        echo "options:{$this->options}" . PHP_EOL;
    }

    public function removeOptions(int $options)
    {
        $this->options &= ~$options;
        echo "options:{$this->options}" . PHP_EOL;
    }

    public function checkOptions(int $options)
    {
        return ($this->options & $options) > 0;
    }
}