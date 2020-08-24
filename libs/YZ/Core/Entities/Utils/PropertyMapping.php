<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

class PropertyMapping
{
    /**
     * @var string 属性名称
     */
    public $propertyName;
    /**
     * @var string 属性别名
     */
    public $propertyAlias;

    /**
     * PropertyMapping constructor.
     * @param string $propertyName 属性名称
     * @param string|null $propertyAlias 属性别名
     */
    public function __construct(string $propertyName, string $propertyAlias = null)
    {
        $this->propertyName = $propertyName;
        $this->propertyAlias = is_null($propertyAlias) ? $propertyName : $propertyAlias;
    }
}