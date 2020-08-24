<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

use Illuminate\Database\Eloquent\Model;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\EntityTrait;

class EntityRelatedData
{
    use EntityTrait;

    protected $foreignKey;
    protected $relatedModelType;
    protected $relatedEntityType;
    protected $relatedKey;
    protected $relatedDataKey;
    private $relatedProperties = []; // 关联属性集合，白名单
    private $nonRelatedProperties = []; // 非关联属性集合，即排除，黑名单
    private $relatedModel = null;
    private $relatedEntity = null;

    /**
     * EntityRelatedData constructor.
     * @param string $foreignKey 外键
     * @param string $relatedModelType 关联模型类型
     * @param string $relatedEntityType 关联Entity类型
     * @param string $relatedKey 关联键
     * @param array $relatedProperties 关联属性集合
     * @param string[] $nonRelatedProperties 非关联属性集合
     * @param EntityExecutionOptions|null $entityExecutionOptions 关联Entity的执行选项
     * @param EntityExecutionPresets|null $entityExecutionPresets 关联Entity的执行预设
     * @param EntityExecutionActions|null $entityExecutionActions 关联Entity的执行动作
     */
    public function __construct(string $foreignKey, string $relatedModelType, string $relatedEntityType, string $relatedKey,
                                array $relatedProperties = [], array $nonRelatedProperties = [], EntityExecutionOptions $entityExecutionOptions = null,
                                EntityExecutionPresets $entityExecutionPresets = null, EntityExecutionActions $entityExecutionActions = null)
    {
        $this->foreignKey = $foreignKey;
        $this->relatedModelType = $relatedModelType;
        $this->relatedEntityType = $relatedEntityType;
        $this->relatedKey = $relatedKey;
        $this->setRelatedProperties($relatedProperties);
        $this->setNonRelatedProperties($nonRelatedProperties);

        $this->entityExecutionOptions = is_null($entityExecutionOptions) ? new EntityExecutionOptions() : $entityExecutionOptions;
        $this->entityExecutionPresets = is_null($entityExecutionPresets) ? new EntityExecutionPresets() : $entityExecutionPresets;
        $this->entityExecutionActions = is_null($entityExecutionActions) ? new EntityExecutionActions() : $entityExecutionActions;

        $this->makeRelatedDataKey();
    }

    /**
     * 获取外键
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * 获取关联模型类型
     * @return string
     */
    public function getRelatedModelType()
    {
        return $this->relatedModelType;
    }

    /**
     * 获取关联Entity类型
     * @return string
     */
    public function getRelatedEntityType()
    {
        return $this->relatedEntityType;
    }

    /**
     * 获取关联键
     * @return string
     */
    public function getRelatedKey()
    {
        return $this->relatedKey;
    }

    /**
     * 获取关联数据标识
     * @return mixed
     */
    public function getRelatedDataKey()
    {
        return $this->relatedDataKey;
    }

    /**
     * 获取关联属性集合
     * @return PropertyMapping[]
     */
    public function getRelatedProperties()
    {
        return $this->relatedProperties;
    }

    /**
     * 设置关联属性集合
     * @param array $relatedProperties
     */
    public function setRelatedProperties(array $relatedProperties)
    {
        foreach ($relatedProperties as $relatedProperty) {
            $this->addRelatedProperty($relatedProperty);
        }
    }

    /**
     * 添加一个关联属性到关联属性集合中
     * @param $relatedProperty
     */
    public function addRelatedProperty($relatedProperty)
    {
        if (is_string($relatedProperty)) {
            $relatedProperty = new PropertyMapping($relatedProperty);
        }

        $exists = false;
        foreach ($this->relatedProperties as $original) {
            if ($original->propertyName == $relatedProperty->propertyName && $original->propertyAlias == $relatedProperty->propertyAlias) {
                $exists = true;
                break;
            }
        }
        if (!$exists) array_push($this->relatedProperties, $relatedProperty);
    }

    /**
     * 从关联属性集合中移除一个关联属性
     * @param $relatedProperty
     */
    public function removeRelatedProperty($relatedProperty)
    {
        if (is_string($relatedProperty)) {
            $relatedProperty = new PropertyMapping($relatedProperty);
        }

        foreach ($this->relatedProperties as $original) {
            if ($original->propertyName == $relatedProperty->propertyName && $original->propertyAlias == $relatedProperty->propertyAlias) {
                array_splice($this->relatedProperties, $original, 1);
                break;
            }
        }
    }

    /**
     * 获取非（排除）关联属性集合
     * @return string[]
     */
    public function getNonRelatedProperties()
    {
        return $this->nonRelatedProperties;
    }

    /**
     * 设置非（排除）关联属性集合
     * @param string[] $nonRelatedProperties
     */
    public function setNonRelatedProperties(array $nonRelatedProperties)
    {
        foreach ($nonRelatedProperties as $nonRelatedProperty) {
            $this->addNonRelatedProperty($nonRelatedProperty);
        }
    }

    /**
     * 添加一个非（排除）关联属性到非（排除）关联属性集合中
     * @param string $nonRelatedProperty
     */
    public function addNonRelatedProperty(string $nonRelatedProperty)
    {
        $exists = false;
        foreach ($this->nonRelatedProperties as $original) {
            if ($original == $nonRelatedProperty) {
                $exists = true;
                break;
            }
        }
        if (!$exists) array_push($this->nonRelatedProperties, $nonRelatedProperty);
    }

    /**
     * 从非（排除）关联属性集合中移除一个非（排除）关联属性
     * @param string $nonRelatedProperty
     */
    public function removeNonRelatedProperty(string $nonRelatedProperty)
    {
        foreach ($this->nonRelatedProperties as $original) {
            if ($original == $nonRelatedProperty) {
                array_splice($this->nonRelatedProperties, $original, 1);
                break;
            }
        }
    }

    /**
     * 获取关联模型
     * @return Model|null
     */
    public function getRelatedModel()
    {
        return $this->relatedModel;
    }

    /**
     * 设置关联模型
     * @param Model $relatedModel
     */
    public function setRelatedModel(Model $relatedModel)
    {
        $this->relatedModel = $relatedModel;
    }

    /**
     * 获取关联Entity
     * @return BaseEntity|null
     */
    public function getRelatedEntity()
    {
        return $this->relatedEntity;
    }

    /**
     * 设置关联Entity
     * @param BaseEntity $relatedEntity
     * @return null
     */
    public function setRelatedEntity(BaseEntity $relatedEntity)
    {
        $this->relatedEntity = $relatedEntity;
    }

    /**
     * 生成关联数据标识
     */
    protected function makeRelatedDataKey()
    {
        $this->relatedDataKey = hash('md4', $this->foreignKey . $this->relatedModelType . $this->relatedEntityType . $this->relatedKey);
    }

    /**
     * 检测属性是否被排除
     * @param string $propertyName
     * @return bool
     */
    public function isExcluded(string $propertyName)
    {
        return !empty($this->nonRelatedProperties) && in_array($propertyName, $this->nonRelatedProperties);
    }

    /**
     * 检测是否需要获取所有关联属性
     * @return bool
     */
    public function isRelatedAllProperties()
    {
        foreach ($this->relatedProperties as $relatedProperty)
        {
            /**
             * @var PropertyMapping $relatedProperty
             */
            if ($relatedProperty->propertyName === '*' && $relatedProperty->propertyAlias === '*') {
                return true;
            }
        }
        return false;
    }
}