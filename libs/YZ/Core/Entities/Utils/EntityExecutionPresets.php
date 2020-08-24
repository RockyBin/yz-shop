<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

class EntityExecutionPresets
{
    /**
     * @var PropertyEventPresetEvent|null 属性事件预设事件
     */
    public $propertyEventPresetEvent;
    /**
     * @var RelatedDataPresetEvent|null 关联数据预设事件
     */
    public $relatedDataPresetEvent;
    /**
     * @var FillEventPresetEvent|null 填充事件预设事件
     */
    public $fillEventPresetEvent;
    /**
     * @var OutputEventPresetEvent|null 输出事件预设事件
     */
    public $outputEventPresetEvent;

    /**
     * EntityExecutionPresets constructor.
     * @param PropertyEventPresetEvent|null $propertyEventPresetEvent 属性事件预设事件
     * @param RelatedDataPresetEvent|null $relatedDataPresetEvent 关联数据预设事件
     * @param FillEventPresetEvent|null $fillEventPresetEvent 填充事件预设事件
     * @param OutputEventPresetEvent|null $outputEventPresetEvent 输出事件预设事件
     */
    public function __construct(PropertyEventPresetEvent $propertyEventPresetEvent = null,
                                RelatedDataPresetEvent $relatedDataPresetEvent = null,
                                FillEventPresetEvent $fillEventPresetEvent = null,
                                OutputEventPresetEvent $outputEventPresetEvent = null)
    {
        $this->propertyEventPresetEvent = $propertyEventPresetEvent;
        $this->relatedDataPresetEvent = $relatedDataPresetEvent;
        $this->fillEventPresetEvent = $fillEventPresetEvent;
        $this->outputEventPresetEvent = $outputEventPresetEvent;
    }
}