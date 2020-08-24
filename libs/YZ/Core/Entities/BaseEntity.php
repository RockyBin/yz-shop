<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities;

 use Exception;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Http\Request;
 use Illuminate\Support\Collection;
 use Illuminate\Validation\Factory;
 use Illuminate\Validation\Validator;
 use JsonSerializable;
 use ReflectionClass;
 use ReflectionException;
 use ReflectionProperty;
 use Symfony\Component\Translation\Loader\PhpFileLoader;
 use YZ\Core\Common\Runtime;
 use YZ\Core\Entities\Utils\EntityExecutionActions;
 use YZ\Core\Entities\Utils\EntityExecutionOptions;
 use YZ\Core\Entities\Utils\EntityExecutionPresets;
 use YZ\Core\Entities\Utils\EntityRelatedData;
 use YZ\Core\Entities\Utils\PropertyMapping;
 use YZ\Core\Model\BaseModel;

 class BaseEntity implements JsonSerializable
 {
     use EntityTrait;

     /**
      * @var BaseModel|null $model
      */
     private $model = null;
     private $fillItems = []; // fillData白名单
     private $nonFillItems = []; // fillData黑名单
     private $hiddenProperties = []; // 隐藏属性列表
     private $isRequest = false;
     /**
      * @var Model[] $relatedModels
      */
     private $relatedModels = [];
     /**
      * @var BaseEntity $relatedEntities
      */
     private $relatedEntities = [];
     private $entityClass;
     private $globalRelatedDataKey = ''; // 关联数据缓存标识
     private $globalRelatedDataValues = null; // 关联数据缓存值

     public function getModel()
     {
         return $this->model;
     }

     public function isRequest()
     {
         return $this->isRequest;
     }

     public function getRelatedModel(EntityRelatedData $entityRelatedData)
     {
         return $this->relatedModels[$entityRelatedData->getRelatedDataKey()];
     }

     public function getRelatedEntity(EntityRelatedData $entityRelatedData)
     {
         return $this->relatedEntities[$entityRelatedData->getRelatedDataKey()];
     }

     /**
      * BaseEntity constructor.
      * @param Model|Request|array|null $map 属性与值的键值对
      * @param EntityExecutionOptions|null $entityExecutionOptions Entity执行选项
      * @param EntityExecutionPresets|null $entityExecutionPresets Entity执行预设
      * @param EntityExecutionActions|null $entityExecutionActions Entity执行动作
      * @param array|Collection|null $globalRelatedDataValues
      * @param string $globalRelatedDataKey
      * @throws ReflectionException
      */
     public function __construct($map = null, EntityExecutionOptions $entityExecutionOptions = null,
                                 EntityExecutionPresets $entityExecutionPresets = null, EntityExecutionActions $entityExecutionActions = null,
                                 $globalRelatedDataValues = null, string $globalRelatedDataKey = '')
     {
         $this->entityClass = self::getClassInfo();
         $this->entityExecutionOptions = is_null($entityExecutionOptions) ? new EntityExecutionOptions() : clone $entityExecutionOptions;
         $this->entityExecutionPresets = is_null($entityExecutionPresets) ? new EntityExecutionPresets() : clone $entityExecutionPresets;
         $this->entityExecutionActions = is_null($entityExecutionActions) ? new EntityExecutionActions() : clone $entityExecutionActions;
         $this->globalRelatedDataKey = $globalRelatedDataKey;
         $this->globalRelatedDataValues = $globalRelatedDataValues;

         // 加载继承类预设的属性事件
         if ($this->getIsLoadPresetPropertyEvent()) {
             $propertyEventPresetEvent = $this->getPropertyEventPresetEvent();
             if (!is_null($propertyEventPresetEvent)) {
                 if (method_exists($propertyEventPresetEvent->getObject(), $propertyEventPresetEvent->getMethod())) {
                     call_user_func_array(array($propertyEventPresetEvent->getObject(), $propertyEventPresetEvent->getMethod()),
                         array_merge(array($this), $propertyEventPresetEvent->getParameters()));
                 } else {
                     throw new Exception('[PropertyEventPresetEvent]对象或类中没有这个方法。');
                 }
             } else if (method_exists($this, 'presetPropertyEvent')) {
                 call_user_func_array(array($this, 'presetPropertyEvent'), []);
             }
         }

         // 加载继承类预设的关联数据
         if ($this->getIsLoadPresetRelatedData()) {
             $relatedDataPresetEvent = $this->getRelatedDataPresetEvent();
             if (!is_null($relatedDataPresetEvent)) {
                 if (method_exists($relatedDataPresetEvent->getObject(), $relatedDataPresetEvent->getMethod())) {
                     call_user_func_array(array($relatedDataPresetEvent->getObject(), $relatedDataPresetEvent->getMethod()),
                         array_merge(array($this), $relatedDataPresetEvent->getParameters()));
                 } else {
                     throw new Exception('[RelatedDataPresetEvent]对象或类中没有这个方法。');
                 }
             } else if (method_exists($this, 'presetRelatedData')) {
                 call_user_func_array(array($this, 'presetRelatedData'), []);
             }
         }

         // 加载继承类预设的填充事件
         if ($this->getIsLoadPresetFillEvent()) {
             $fillEventPresetEvent = $this->getFillEventPresetEvent();
             if (!is_null($fillEventPresetEvent)) {
                 if (method_exists($fillEventPresetEvent->getObject(), $fillEventPresetEvent->getMethod())) {
                     call_user_func_array(array($fillEventPresetEvent->getObject(), $fillEventPresetEvent->getMethod()),
                         array_merge(array($this), $fillEventPresetEvent->getParameters()));
                 } else {
                     throw new Exception('[FillEventPresetEvent]对象或类中没有这个方法。');
                 }
             } else if (method_exists($this, 'presetFillEvent')) {
                 call_user_func_array(array($this, 'presetFillEvent'), []);
             }
         }

         // 加载继承类预设的输出事件
         if ($this->getIsLoadPresetOutputEvent()) {
             $outputEventPresetEvent = $this->getOutputEventPresetEvent();
             if (!is_null($outputEventPresetEvent)) {
                 if (method_exists($outputEventPresetEvent->getObject(), $outputEventPresetEvent->getMethod())) {
                     call_user_func_array(array($outputEventPresetEvent->getObject(), $outputEventPresetEvent->getMethod()),
                         array_merge(array($this), $outputEventPresetEvent->getParameters()));
                 } else {
                     throw new Exception('[OutputEventPresetEvent]对象或类中没有这个方法。');
                 }
             } else if (method_exists($this, 'presetOutputEvent')) {
                 call_user_func_array(array($this, 'presetOutputEvent'), []);
             }
         }

         if ($map != null) {
             // 设置Entity属性值
             $this->setValues($map);

             // 填充Entity关联数据
             $this->fillRelatedData();

             // 触发Entity属性事件
             $this->activatePropertyEvent();
         }

         $this->setDefaultFillItems();
     }

     /**
      * 设置Entity对象所有对应的属性值
      * @param Model|Request|array $map 属性与值的键值对
      * @throws Exception
      */
     public function setValues($map)
     {
         if ($map instanceof Model) {
             $this->model = $map; // 是Model时保存Model
             $map = $map->toArray();
         } else if ($map instanceof Request) {
             $this->isRequest = true; //
             $map = $map->all();
         }

         // 设置属性值
         foreach ($map as $key => $value) {
             // 设置显式声名的属性的值
             if ($this->entityClass->hasProperty($key) && $this->entityClass->getProperty($key)->isPublic()) {
                 $this->entityClass->getProperty($key)->setValue($this, $map[$key]);
             } // 设置隐藏属性的值
             else if ($this->entityExecutionOptions->useHiddenProperty) {
                 $this->$key = $value;
             }
         }
     }

     /**
      * 触发Entity属性事件
      * @throws Exception
      */
     public function activatePropertyEvent()
     {
         if (!$this->getUsePropertyEvent() || is_null($this->getEntityPropertyEvents())) return;

         // 触发Entity属性事件
         foreach ($this->getEntityPropertyEvents() as $entityPropertyEvent) {
             if (array_key_exists($entityPropertyEvent->getPropertyName(), $this->hiddenProperties) || property_exists($this->entityClass->getName(), $entityPropertyEvent->getPropertyName())) {
                 if (method_exists($entityPropertyEvent->getObject(), $entityPropertyEvent->getMethod())) {
                     call_user_func_array(array($entityPropertyEvent->getObject(), $entityPropertyEvent->getMethod()),
                         array_merge(array($this, $entityPropertyEvent->getPropertyName()), $entityPropertyEvent->getParameters()));
                 } else {
                     throw new Exception("[EntityPropertyEvent]对象或类中没有这个方法。[{$entityPropertyEvent->getPropertyName()} -  {$entityPropertyEvent->getObject()} -  {$entityPropertyEvent->getMethod()}]");
                 }
             }
         }
     }

     /**
      * 填充Entity关联数据
      * @throws ReflectionException
      * @throws Exception
      */
     public function fillRelatedData()
     {
         if (!$this->getUseRelatedData() || is_null($this->model) || is_null($this->getEntityRelatedDataList())) return;

         // 填充关联数据
         foreach ($this->getEntityRelatedDataList() as $entityRelatedData) {
             if (!array_key_exists($entityRelatedData->getRelatedDataKey(), $this->relatedModels)) {
                 $collection = null;
                 if ($this->globalRelatedDataValues === null) {
                     $collection = $this->model->belongsTo($entityRelatedData->getRelatedModelType(), $entityRelatedData->getForeignKey(), $entityRelatedData->getRelatedKey())->get();
                 } // 使用关联数据预读机制
                 else if (is_array($this->globalRelatedDataValues)) {
                     $this->globalRelatedDataKey = hash('md4', $this->globalRelatedDataKey . $entityRelatedData->getRelatedDataKey());
                     if (array_key_exists($this->globalRelatedDataKey, $this->globalRelatedDataValues)) {
                         /**
                          * @var Collection $relatedModelCollection
                          */
                         $relatedModelCollection = $this->globalRelatedDataValues[$this->globalRelatedDataKey];
                         // 不使用Laravel的Collection，因为where比对太慢
                         // $collection = $relatedModelCollection->where($entityRelatedData->getRelatedKey(), '=', $this->model[$entityRelatedData->getForeignKey()]);
                         $collection = new Collection();
                         foreach ($relatedModelCollection as $relatedModel) {
                             if ($relatedModel[$entityRelatedData->getRelatedKey()] === $this->model[$entityRelatedData->getForeignKey()]) {
                                 $collection->push($relatedModel);
                             }
                         }
                     } else {
                         $collection = new Collection();
                     }
                 }
                 else if($this->globalRelatedDataValues instanceof Collection) {
                     $this->globalRelatedDataKey = hash('md4', $this->globalRelatedDataKey . $entityRelatedData->getRelatedDataKey());
                     $collection = new Collection();
                     // 不使用Laravel的Collection，因为where比对太慢
                     // $relatedModelCollection = $this->globalRelatedDataValues->where("{$this->globalRelatedDataKey}_{$entityRelatedData->getRelatedKey()}", '=', $this->model[$entityRelatedData->getForeignKey()]);
                     $relatedModelCollection = [];
                     foreach ($this->globalRelatedDataValues as $relatedModel) {
                         if ($relatedModel["{$this->globalRelatedDataKey}_{$entityRelatedData->getRelatedKey()}"] === $this->model[$entityRelatedData->getForeignKey()]) {
                             $relatedModelCollection[] = $relatedModel;
                             break;
                         }
                     }

                     if (count($relatedModelCollection) > 0) {
                         $relatedEntityClass = new ReflectionClass($entityRelatedData->getRelatedEntityType());
                         $properties = $relatedEntityClass->getProperties(ReflectionProperty::IS_PUBLIC);
                         /**
                          * @var Model $relatedModel
                          */
                         $relatedModel = $relatedModelCollection[0];

                         $setModelPropertyValue = function(Model $model, string $propertyName, $value) {
                             $model->$propertyName = $value;
                         };

                         /**
                          * @var Model $model
                          */
                         $model = (new ReflectionClass($entityRelatedData->getRelatedModelType()))->newInstance();

                         foreach ($entityRelatedData->getRelatedProperties() as $relatedProperty) {
                             /**
                              * @var PropertyMapping|string $relatedProperty
                              */
                             if (is_string($relatedProperty)) {
                                 $relatedProperty = new PropertyMapping($relatedProperty);
                             }

                             // 获取关联Model里的所有属性
                             if ($relatedProperty->propertyName === '*' && $relatedProperty->propertyAlias === '*') {
                                 foreach ($properties as $property) {
                                     $propertyName = $property->getName();
                                     $setModelPropertyValue($model, $property->getName(), $relatedModel->getAttribute("{$this->globalRelatedDataKey}_{$propertyName}"));
                                 }
                                 break;
                             }

                             $setModelPropertyValue($model, $relatedProperty->propertyName, $relatedModel->getAttribute("{$this->globalRelatedDataKey}_{$relatedProperty->propertyName}"));
                         }

                         $collection->push($model);
                     }
                 }

                 $modelNumber = $collection->count();

                 if ($modelNumber > 1) {
                     throw new Exception("[RelatedData]只能获取一对一的关联数据。");
                 } else if ($modelNumber === 1) {
                     $model = $collection->first();
                     $model->timestamps = false;
                     $this->relatedModels[$entityRelatedData->getRelatedDataKey()] = $model;
                     $entityRelatedData->setRelatedModel($model);
                 } else {
                     /**
                      * @var Model $model
                      */
                     $model = (new ReflectionClass($entityRelatedData->getRelatedModelType()))->newInstance();
                     $entityRelatedData->setRelatedModel($model);
                     $this->relatedModels[$entityRelatedData->getRelatedDataKey()] = null;
                 }

                 // 检查关联层级计数，等于1时取消再次获取关联数据的相关操作选项，即停止获取子级关联数据
                 if ($this->getRelatedCount() === 1) {
                     $entityRelatedData->setUseRelatedData(false);
                     $entityRelatedData->setIsLoadPresetRelatedData(false);
                     $entityRelatedData->setRelatedDataPresetEvent(null);
                     $entityRelatedData->setEntityRelatedDataList([]);
                 } else if ($this->getRelatedCount() > 1) {
                     $entityRelatedData->setRelatedCount($this->getRelatedCount() - 1);
                 } else if ($this->getRelatedCount() < 0) {
                     $entityRelatedData->setRelatedCount($this->getRelatedCount());
                 }

                 // 生成Entity
                 $entityClass = new ReflectionClass($entityRelatedData->getRelatedEntityType());
                 /**
                  * @var BaseEntity $entityInstance
                  */
                 $entityInstance = $entityClass->newInstance($entityRelatedData->getRelatedModel(),
                     $entityRelatedData->getEntityExecutionOptions(), $entityRelatedData->getEntityExecutionPresets(),
                     $entityRelatedData->getEntityExecutionActions(), $this->globalRelatedDataValues, $this->globalRelatedDataKey);

                 $entityRelatedData->setRelatedEntity($entityInstance);
                 $this->relatedEntities[$entityRelatedData->getRelatedDataKey()] = is_null($this->relatedModels[$entityRelatedData->getRelatedDataKey()]) ? null : $entityInstance;
             } else {
                 $entityRelatedData->setRelatedModel($this->relatedModels[$entityRelatedData->getRelatedDataKey()]);
                 $entityRelatedData->setRelatedEntity($this->relatedEntities[$entityRelatedData->getRelatedDataKey()]);
             }

             foreach ($entityRelatedData->getRelatedProperties() as $relatedProperty) {
                 /**
                  * @var PropertyMapping|string $relatedProperty
                  */
                 if (is_string($relatedProperty)) {
                     $relatedProperty = new PropertyMapping($relatedProperty);
                 }

                 // 获取关联Model或Entity里的所有属性
                 if ($relatedProperty->propertyName === '*' && $relatedProperty->propertyAlias === '*') {
                     $map = $entityRelatedData->getRelatedEntity()->toArrayByRelatedData();
                     foreach (array_keys($map) as $key) {
                         $this->setRelatedDataValue($entityRelatedData, new PropertyMapping($key));
                     }
                     break;
                 }

                 $this->setRelatedDataValue($entityRelatedData, $relatedProperty);
             }
         }
     }

     private function setRelatedDataValue(EntityRelatedData $entityRelatedData, PropertyMapping $relatedProperty)
     {
         if($entityRelatedData->isExcluded($relatedProperty->propertyName)) return;

         $propertyName = $relatedProperty->propertyName;
         $propertyAlias = $relatedProperty->propertyAlias;
         $propertyValue = null;

         $propertyValue = $entityRelatedData->getRelatedEntity()->$propertyName;

         $this->$propertyAlias = $propertyValue;
     }

     static private function objectToArray($object)
     {
         $object = (array)$object;
         foreach ($object as $key => $value) {
             if (gettype($value) == 'resource') {
                 return;
             }
             if (gettype($value) == 'object' || gettype($value) == 'array') {
                 $object[$key] = (array)self::objectToArray($value);
             }
         }
         return $object;
     }

     /**
      * @return ReflectionClass
      * @throws ReflectionException
      */
     static private function getClassInfo(): ReflectionClass
     {
         return new ReflectionClass(get_called_class());
     }

     private function setDefaultFillItems()
     {
         $properties = $this->entityClass->getProperties(ReflectionProperty::IS_PUBLIC);
         foreach ($properties as $property) {
             array_push($this->fillItems, $property->getName());
         }
     }

     /**
      * 获取FillData白名单
      * @return string[] FillData白名单
      */
     public function getFillItems()
     {
         return $this->fillItems;
     }

     /**
      * 设置FillData白名单
      * @param string[] $fillItems FillData白名单
      */
     public function setFillItems(array $fillItems)
     {
         $this->fillItems = $fillItems;
     }

     /**
      * 新增一个FillItem到FillData白名单
      * @param string $fillItem
      */
     public function addFillItem(string $fillItem)
     {
         if (!in_array($fillItem, $this->fillItems)) {
             array_push($this->fillItems, $fillItem);
         }
     }

     /**
      * 移除FillData白名单里的一个FillItem
      * @param string $fillItem
      */
     public function removeFillItem(string $fillItem)
     {
         if (in_array($fillItem, $this->fillItems)) {
             array_splice($this->fillItems, array_search($fillItem, $this->fillItems), 1);
         }
     }

     /**
      * 获取FillData黑名单
      * @return string[]
      */
     public function getNonFillItems()
     {
         return $this->nonFillItems;
     }

     /**
      * 设置FillData黑名单
      * @param string[] $nonFillItems
      */
     public function setNonFillItems(array $nonFillItems)
     {
         $this->nonFillItems = $nonFillItems;
     }

     /**
      * 新增一个NonFillItem到FillData黑名单
      * @param string $nonFillItem
      */
     public function addNonFillItem(string $nonFillItem)
     {
         if (!in_array($nonFillItem, $this->nonFillItems)) {
             array_push($this->nonFillItems, $nonFillItem);
         }
     }

     /**
      * 移除FillData黑名单里的一个NonFillItem
      * @param string $nonFillItem
      */
     public function removeNonFillItem(string $nonFillItem)
     {
         if (in_array($nonFillItem, $this->nonFillItems)) {
             array_splice($this->nonFillItems, array_search($nonFillItem, $this->nonFillItems), 1);
         }
     }

     /**
      * 触发Entity填充事件
      * @param array $fillData
      * @throws Exception
      */
     protected function activateFillEvent(array &$fillData)
     {
         if (!$this->getUseFillEvent() || is_null($this->getEntityFillEvents())) return;

         // 触发Entity填充事件
         foreach ($this->getEntityFillEvents() as $entityFillEvent) {
             if (array_key_exists($entityFillEvent->getFillItem(), $fillData)) {
                 if (method_exists($entityFillEvent->getObject(), $entityFillEvent->getMethod())) {
                     call_user_func_array(array($entityFillEvent->getObject(), $entityFillEvent->getMethod()),
                         array_merge(array($this, &$fillData, $entityFillEvent->getFillItem()), $entityFillEvent->getParameters()));
                 } else {
                     throw new Exception("[EntityFillEvent]对象或类中没有这个方法。[{$entityFillEvent->getFillItem()} - {$entityFillEvent->getObject()} - {$entityFillEvent->getMethod()}]");
                 }
             }
         }
     }

     /**
      * 获取非空值的FillData
      * @return array $fillData
      * @throws Exception
      */
     public function getNonNullFillData(): array
     {
         $fillData = [];
         $properties = $this->entityClass->getProperties(ReflectionProperty::IS_PUBLIC);

         foreach ($properties as $property) {
             if ($property->getValue($this) != null) {
                 $fillData[$property->getName()] = $property->getValue($this);
             }
         }

         $this->activateFillEvent($fillData);

         return $fillData;
     }

     /**
      * 获取完整的FillData，包括空值及忽略FillData黑、白名单
      * @return array $fillData
      * @throws Exception
      */
     public function getFullFillData(): array
     {
         $fillData = [];
         $properties = $this->entityClass->getProperties(ReflectionProperty::IS_PUBLIC);

         foreach ($properties as $property) {
             $fillData[$property->getName()] = $property->getValue($this);
         }

         $this->activateFillEvent($fillData);

         return $fillData;
     }

     /**
      * 获取基于FillData白名单对应的FillData，并且过滤掉FillData黑名单里对应的FillData
      * @param array|null $fillItems FillData白名单，null时则使用对象内的FillData白名单
      * @param array|null $nonFillItems FillData黑名单，null时则使用对象内的FillData黑名单
      * @return array $fillData
      * @throws Exception
      */
     public function getFillData(array $fillItems = null, array $nonFillItems = null): array
     {
         if ($fillItems != null) $this->fillItems = $fillItems;
         if ($nonFillItems != null) $this->nonFillItems = $nonFillItems;

         $fillData = [];
         $properties = $this->entityClass->getProperties(ReflectionProperty::IS_PUBLIC);

         foreach ($properties as $property) {
             if (in_array($property->getName(), $this->fillItems) && !in_array($property->getName(), $this->nonFillItems)) {
                 $fillData[$property->getName()] = $property->getValue($this);
             }
         }

         $this->activateFillEvent($fillData);

         return $fillData;
     }

     public function __get($name)
     {
         return $this->getHiddenProperty($name);
     }

     public function __set($name, $value)
     {
         $this->setHiddenProperty($name, $value);
     }

     public function __unset($name)
     {
         if (array_key_exists($name, $this->hiddenProperties)) {
             $this->unsetHiddenProperty($name);
         } else {
             unset($this->$name);
         }
     }

     public function __isset($name)
     {
         if (array_key_exists($name, $this->hiddenProperties)) {
             return true;
         } else {
             return isset($this->$name);
         }
     }

     private function getHiddenProperty($name)
     {
         return $this->hiddenProperties[$name];
     }

     private function setHiddenProperty($name, $value)
     {
         if ($this->entityExecutionOptions->useHiddenProperty) $this->hiddenProperties[$name] = $value;
     }

     private function unsetHiddenProperty($name)
     {
         unset($this->hiddenProperties[$name]);
     }

     /**
      * 触发Entity输出事件
      * @param array $outputData
      * @throws Exception
      */
     protected function activateOutputEvent(array &$outputData)
     {
         if (!$this->getUseOutputEvent() || is_null($this->getEntityOutputEvents())) return;

         // 触发Entity输出事件
         foreach ($this->getEntityOutputEvents() as $entityOutputEvent) {
             if (array_key_exists($entityOutputEvent->getOutputItem(), $outputData)) {
                 if (method_exists($entityOutputEvent->getObject(), $entityOutputEvent->getMethod())) {
                     call_user_func_array(array($entityOutputEvent->getObject(), $entityOutputEvent->getMethod()),
                         array_merge(array($this, &$outputData, $entityOutputEvent->getOutputItem()), $entityOutputEvent->getParameters()));
                 } else {
                     throw new Exception("[EntityOutputEvent]对象或类中没有这个方法。[{$entityOutputEvent->getOutputItem()} -  {$entityOutputEvent->getObject()} -  {$entityOutputEvent->getMethod()}]");
                 }
             }
         }
     }

     /**
      * @return array
      * @throws Exception
      */
     public function toArrayWithNotNullValues()
     {
         $outputData = [];

         foreach ($this->toArray() as $key => $value) {
             if (!is_null($value)) $outputData[$key] = $value;
         }

         return $outputData;
     }

     /**
      * @return array
      * @throws Exception
      */
     private function toArrayByRelatedData()
     {
         $useOutputEvent = $this->getUseOutputEvent();
         $this->setUseOutputEvent(false);
         $outputData = $this->toArray();
         $this->setUseOutputEvent($useOutputEvent);
         return $outputData;
     }

     /**
      * @return array
      * @throws Exception
      */
     public function toArray()
     {
         $entityClass = self::getClassInfo();
         $properties = $entityClass->getProperties(ReflectionProperty::IS_PUBLIC);
         $publicProperties = [];
         foreach ($properties as $property) {
             $publicProperties[$property->getName()] = $property->getValue($this);
         }

         $outputData = array_merge($publicProperties, $this->hiddenProperties);

         $this->activateOutputEvent($outputData);

         return $outputData;
     }

     /**
      * @return array|mixed
      * @throws Exception
      */
     public function jsonSerialize()
     {
         return $this->toArray();
     }

     // 验证-------Start
     /**
      * @var array
      */
     protected static $rules =[];

     /**
      * @var Validator
      */
     protected $validator;

     /**
      * @var Factory
      */
     private static $validationFactory = null;

     private static function getValidationFactory()
     {
         return is_null(self::$validationFactory) ? self::createValidationFactory()  : self::$validationFactory;
     }

     private static function createValidationFactory($lang = 'en')
     {
         $translator = new \Symfony\Component\Translation\Translator($lang);
         $translator->addLoader('file_loader', new PhpFileLoader());
         $translator->addResource('file_loader',
             dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $lang .
             DIRECTORY_SEPARATOR . 'validation.php', $lang);
         return Factory($translator);
     }

     public function validate(array $data, array $rules)
     {
         $this->validator = self::getValidationFactory()->make($data, $rules, [], []);
         return $this->validator->passes();
     }
     // 验证-------End
 }