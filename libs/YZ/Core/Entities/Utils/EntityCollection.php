<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use YZ\Core\Common\Runtime;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\EntityTrait;

class EntityCollection extends Collection
{
    use EntityTrait;
    /**
     * @var ReflectionClass
     */
    private $entityClass;
    /**
     * @var EntityEvent
     */
    private $entityEvent; // Entity事件
    private $globalRelatedDataValues = null; // 关联数据缓存
    private $gainRelatedDataAllProperties = false;
    private $gainRelatedDataType = 2;

    /**
     * 获取Entity事件
     * @return EntityEvent|null
     */
    public function getEntityEvent()
    {
        return $this->entityEvent;
    }

    /**
     * 设置Entity事件
     * @param EntityEvent $entityEvent
     */
    public function setEntityEvent(EntityEvent $entityEvent)
    {
        $this->entityEvent = $entityEvent;
    }

    /**
     * 创建EntityCollection的实例
     * @param string $entityClassName Entity类型名称
     * @param EntityEvent|null $entityEvent Entity事件
     * @param EntityExecutionOptions|null $entityExecutionOptions Entity执行选项
     * @param EntityExecutionPresets|null $entityExecutionPresets Entity执行预设
     * @param EntityExecutionActions|null $entityExecutionActions Entity执行动作
     * @return EntityCollection EntityCollection实例
     * @throws Exception
     */
    static public function createInstance(string $entityClassName, EntityEvent $entityEvent = null, EntityExecutionOptions $entityExecutionOptions = null,
                                          EntityExecutionPresets $entityExecutionPresets = null, EntityExecutionActions $entityExecutionActions = null)
    {
        $instance = new self();
        try {
            $instance->entityClass = new ReflectionClass($entityClassName);
        } catch (Exception $e) {
            throw new Exception('[EntityCollection]映射错误。');
        }

        $instance->entityExecutionOptions = is_null($entityExecutionOptions) ? new EntityExecutionOptions() : $entityExecutionOptions;
        $instance->entityExecutionPresets = is_null($entityExecutionPresets) ? new EntityExecutionPresets() : $entityExecutionPresets;
        $instance->entityExecutionActions = is_null($entityExecutionActions) ? new EntityExecutionActions() : $entityExecutionActions;

        $instance->entityEvent = $entityEvent;

        return $instance;
    }

    /**
     * 加载Model Collection数据到Entity Collection
     * @param Collection $modelCollection Model集合
     * @throws Exception
     */
    public function loadData(Collection $modelCollection)
    {
        if ($modelCollection->count() === 0) return;
        /**
         * @var BaseEntity $entity
         */
        $entity = $this->entityClass->newInstance(null, $this->getEntityExecutionOptions(),
            $this->getEntityExecutionPresets(), $this->getEntityExecutionActions());
        /**
         * @var Model $model
         */
        $model = $modelCollection->first();
        $globalRelatedDataKey = hash('md4', $model->getTable());

        if($this->gainRelatedDataType === 1) {
            $this->makeRelatedModelCollection($entity, $model, $modelCollection, $globalRelatedDataKey);
        }
        else if($this->gainRelatedDataType === 2) {
            $this->makeRelatedModelCollections($entity, $modelCollection, $globalRelatedDataKey);
        }

        foreach ($modelCollection as $model) {
            /**
             * @var BaseEntity $entityInstance
             */
            $entityInstance = $this->entityClass->newInstance($model, $this->getEntityExecutionOptions(),
                $this->getEntityExecutionPresets(), $this->getEntityExecutionActions(), $this->globalRelatedDataValues, $globalRelatedDataKey);

            if (!is_null($this->entityEvent)) {
                if (!method_exists($this->entityEvent->getObject(), $this->entityEvent->getMethod())) {
                    throw new Exception('[EntityEvent]对象或类中没有这个方法。');
                }

                call_user_func_array(array($this->entityEvent->getObject(), $this->entityEvent->getMethod()),
                    array_merge(array($entityInstance), $this->entityEvent->getParameters()));
            }

            array_push($this->items, $entityInstance);
        }
    }

    /**
     * @param BaseEntity $entity
     * @param Model $model
     * @param Collection $modelCollection
     * @param string $globalRelatedDataKey
     * @throws ReflectionException
     */
    private function makeRelatedModelCollection(BaseEntity $entity, Model $model, Collection $modelCollection, string $globalRelatedDataKey)
    {
        $builder = $model->newQuery();
        $filedAlias = ["$globalRelatedDataKey.*"];
        $this->makeRelatedBuilder($entity, $builder, $globalRelatedDataKey, $filedAlias);
        $builder->from("{$model->getTable()} as $globalRelatedDataKey");
        $builder->select($filedAlias);

        $keys = $modelCollection->mapWithKeys(function ($item) {
            /**
             * @var Model $item
             */
            return [$item->getKey() => $item->getKey()];
        })->all();
        $builder->whereIn("$globalRelatedDataKey.{$model->getKeyName()}", $keys);
        $this->globalRelatedDataValues = $builder->get();
    }

    /**
     * @param BaseEntity $entity
     * @param Builder $builder
     * @param string $globalRelatedDataKey
     * @param array $filedAlias
     * @throws ReflectionException
     */
    private function makeRelatedBuilder(BaseEntity $entity, $builder, string $globalRelatedDataKey, array &$filedAlias)
    {
        $entityRelatedDataList = $entity->getEntityRelatedDataList();
        $oldGlobalRelatedDataKey = $globalRelatedDataKey;

        foreach ($entityRelatedDataList as $entityRelatedData) {
            $globalRelatedDataKey = hash('md4', $globalRelatedDataKey . $entityRelatedData->getRelatedDataKey());
            /**
             * @var Model $relatedModel
             */
            $relatedModel = (new ReflectionClass($entityRelatedData->getRelatedModelType()))->newInstance();
            $builder->leftJoin("{$relatedModel->getTable()} as {$globalRelatedDataKey}", "{$globalRelatedDataKey}.{$entityRelatedData->getRelatedKey()}",
                '=', "{$oldGlobalRelatedDataKey}.{$entityRelatedData->getForeignKey()}");

            $relatedEntityClass = new ReflectionClass($entityRelatedData->getRelatedEntityType());

            $setFieldAlias = function (string $propertyName) use ($globalRelatedDataKey, $entityRelatedData, $relatedModel, &$filedAlias) {
                if ($entityRelatedData->isExcluded($propertyName) &&
                    $relatedModel->getKeyName() !== $propertyName &&
                    $entityRelatedData->getRelatedKey() !== $propertyName) return;
                $filedAlias[] = "{$globalRelatedDataKey}.{$propertyName} as {$globalRelatedDataKey}_{$propertyName}";
            };

            if($this->gainRelatedDataAllProperties)
            {
                $properties = $relatedEntityClass->getProperties(ReflectionProperty::IS_PUBLIC);
                foreach ($properties as $property) {
                    $setFieldAlias($property->getName());
                }
            }
            else {
                if ($entityRelatedData->isRelatedAllProperties()) {
                    $properties = $relatedEntityClass->getProperties(ReflectionProperty::IS_PUBLIC);
                    foreach ($properties as $property) {
                        $setFieldAlias($property->getName());
                    }
                } else {
                    foreach ($entityRelatedData->getRelatedProperties() as $relatedProperty) {
                        $setFieldAlias($relatedProperty->propertyName);
                    }
                }
            }

            $this->checkContinue($entity, $entityRelatedData);

            /**
             * @var BaseEntity $relatedEntity
             */
            $relatedEntity = $relatedEntityClass->newInstance(null, $entityRelatedData->getEntityExecutionOptions(),
                $entityRelatedData->getEntityExecutionPresets(), $entityRelatedData->getEntityExecutionActions());

            $this->makeRelatedBuilder($relatedEntity, $builder, $globalRelatedDataKey, $filedAlias);
        }
    }

    /**
     * 预读EntityCollection里的Entity的所有关联数据Model
     * @param BaseEntity $entity
     * @param Collection $modelCollection
     * @param string $globalRelatedDataKey
     * @throws ReflectionException
     */
    private function makeRelatedModelCollections(BaseEntity $entity, Collection $modelCollection, string $globalRelatedDataKey)
    {
        $entityRelatedDataList = $entity->getEntityRelatedDataList();

        foreach ($entityRelatedDataList as $entityRelatedData) {
            $globalRelatedDataKey = hash('md4', $globalRelatedDataKey . $entityRelatedData->getRelatedDataKey());
            $relatedEntityClass = new ReflectionClass($entityRelatedData->getRelatedEntityType());

            $foreignKeys = array_unique($modelCollection->mapWithKeys(function ($item) use ($entityRelatedData) {
                /**
                 * @var Model $item
                 */
                return [$item->getKey() => $item[$entityRelatedData->getForeignKey()]];
            })->all());

            /**
             * @var Model $relatedModel
             */
            $relatedModel = (new ReflectionClass($entityRelatedData->getRelatedModelType()))->newInstance();
            $relatedModelCollection = $relatedModel->newQuery()->whereIn($entityRelatedData->getRelatedKey(), $foreignKeys)->get();
            $this->globalRelatedDataValues[$globalRelatedDataKey] = $relatedModelCollection;

            $this->checkContinue($entity, $entityRelatedData);

            /**
             * @var BaseEntity $relatedEntity
             */
            $relatedEntity = $relatedEntityClass->newInstance(null, $entityRelatedData->getEntityExecutionOptions(),
                $entityRelatedData->getEntityExecutionPresets(), $entityRelatedData->getEntityExecutionActions());

            $this->makeRelatedModelCollections($relatedEntity, $relatedModelCollection, $globalRelatedDataKey);
        }
    }

    /**
     * @param BaseEntity $entity
     * @param EntityRelatedData $entityRelatedData
     */
    public function checkContinue(BaseEntity $entity, EntityRelatedData $entityRelatedData)
    {
        // 检查关联层级计数，等于1时取消再次获取关联数据的相关操作选项，即停止获取子级关联数据
        if ($entity->getRelatedCount() === 1) {
            $entityRelatedData->setUseRelatedData(false);
            $entityRelatedData->setIsLoadPresetRelatedData(false);
            $entityRelatedData->setRelatedDataPresetEvent(null);
            $entityRelatedData->setEntityRelatedDataList([]);
        } else if ($entity->getRelatedCount() > 1) {
            $entityRelatedData->setRelatedCount($entity->getRelatedCount() - 1);
        } else if ($entity->getRelatedCount() < 0) {
            $entityRelatedData->setRelatedCount($entity->getRelatedCount());
        }
    }

    /**
     * 创建分页Model集合，并且Builder中包含GroupBy
     * @param Builder $query
     * @param PaginationEntity $paginationEntity
     * @return Collection 装载Model的Collection
     * @throws Exception
     */
    public static function createDataPaginationWithGroupBy(Builder $query, PaginationEntity $paginationEntity): Collection
    {
        if ($paginationEntity == null) throw new Exception('没有传递分页信息。');

        if ($paginationEntity->page <= 0) $paginationEntity->page = 1;
        if ($paginationEntity->page_size <= 0) $paginationEntity->page_size = 20;

        if ($query->getQuery()->groups) {
            $groups = $query->getQuery()->groups;
            $query->getQuery()->groups = null;
            $paginationEntity->total = $query->count();
            $query->getQuery()->groups = $groups;
        } else {
            $paginationEntity->total = $query->count();
        }

        if ($paginationEntity->show_all) {
            $paginationEntity->last_page = 1;
            return $query->get();
        } else {
            $paginationEntity->last_page = (int)ceil($paginationEntity->total / $paginationEntity->page_size);
            return $query->offset(($paginationEntity->page - 1) * $paginationEntity->page_size)->limit($paginationEntity->page_size)->get();
        }
    }

    /**
     * 创建分页Model集合
     * @param Builder $query
     * @param PaginationEntity $paginationEntity
     * @return Collection 装载Model的Collection
     * @throws Exception
     */
    public static function createDataPagination(Builder $query, PaginationEntity $paginationEntity): Collection
    {
        if ($paginationEntity == null) {
            throw new Exception('没有传递分页信息。');
        }

        if ($paginationEntity->page <= 0) $paginationEntity->page = 1;
        if ($paginationEntity->page_size <= 0) $paginationEntity->page_size = 20;

        $paginationEntity->total = $query->count();

        if ($paginationEntity->show_all) {
            $paginationEntity->last_page = 1;
            return $query->get();
        } else {
            $paginationEntity->last_page = (int)ceil($paginationEntity->total / $paginationEntity->page_size);
            return $query->offset(($paginationEntity->page - 1) * $paginationEntity->page_size)->limit($paginationEntity->page_size)->get();
        }
    }

    /**
     * 封装返回值
     * @param EntityCollection $entityCollection
     * @param PaginationEntity $paginationEntity
     * @return array
     */
    public static function createReturnValue(EntityCollection $entityCollection, PaginationEntity $paginationEntity): array
    {
        return ["list" => $entityCollection->toArray(),
            "total" => $paginationEntity->total,
            "last_page" => $paginationEntity->last_page,
            "current" => $paginationEntity->page,
            "page_size" => $paginationEntity->page_size];
    }
}