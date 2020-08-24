<?php

namespace YZ\Core\Model;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webpatser\Uuid\Uuid;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Logger\Log;

/**
 * Class BaseModel 基础模型，集成 LaravelArdent\Ardent\Ardent 的验证功能
 * @package App
 */
class BaseModel extends \LaravelArdent\Ardent\Ardent
{
    /**
     * @var bool 默认关闭 Eloquent 的 updated_at 和 created_at 字段
     */
    public $timestamps = false;

    /**
     * @var bool 指定是否在主库进行查询，用于避免读写分离时，由于主从同步引起的数据不一致的情况
     */
    protected $forceWriteConnection = false;

    /**
     * 重写验证方法，Eloquent 的验证只返回 true 或 false，我们将其改为抛出具体的异常信息，方便外层调用
     * @return bool
     * @throws \Exception
     */
    public function afterValidate()
    {
        $errors = $this->errors()->messages();
        if (count($errors) > 0) {
            $msg = '';
            foreach ($errors as $k => $v) {
                $msg .= get_class($this) . ":" . implode("\r\n", $v) . "\r\n";
            }
            throw new \Exception($msg);
        }
        return true;
    }

    /**
     * cli模式下，每次都boot一下
     */
    protected function bootIfNotBooted()
    {
        if (isInCli() || isSwoole()) unset(static::$booted[static::class]);
        parent::bootIfNotBooted();
    }

    /**
     * 生成GUID
     * @param int $maxlen
     * @return bool|mixed|string
     * @throws \Exception
     */
    static function genUuid($maxlen = 0)
    {
        $uuid = str_replace('-', '', Uuid::generate());
        if ($maxlen) $uuid = substr($uuid, 0, $maxlen);
        return $uuid;
    }

    /**
     * 执行原始sql查询
     * @param $sql
     * @param array $params
     * @return array|bool|int
     */
    public static function runSql($sql, $params = [])
    {
        if (preg_match('/^(delete\s+)/i', $sql)) $query = DB::delete($sql, $params);
        elseif (preg_match('/^(insert\s+into)/i', $sql)) $query = DB::insert($sql, $params);
        else $query = DB::select($sql, $params);
        return $query;
    }

    /**
     * 重写模型查询构造器器规则，可以指定从主库读，避免读写分离时，可能数据不同步的问题
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQuery()
    {
        $query = parent::newQuery();
        if ($this->forceWriteConnection) $query->useWritePdo();
        return $query;
    }

//    /**
//     * 定义获取SiteID的访问器，以便在 cli 模式下根据 site_id 字段进行网站初始化，避免在 cli 模式下 Site::getCurrentSite() 出错
//     * @param $value
//     * @return mixed
//     */
//    public function getSiteIdAttribute($value)
//    {
//        $isSiteModel = $this instanceof SiteModel;
//        // 过滤到 SiteModel
//        if (intval($value) >= 0 && !$isSiteModel) {
//            \YZ\Core\Site\Site::initSiteForCli($value);
//        }
//        return $value;
//    }

    /**
     * 批量更新
     * @param array $multipleData 要更新的数据
     * @param string $primaryKey 主键名
     * @param string $where      额外的where条件
     * @return int
     * @throws \Exception
     */
    public function updateBatch($multipleData = [], $primaryKey = '', $where = '')
    {
        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
            }
            $tableName = $this->getTable(); // 表名
            $firstRow = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 获取数据表的字段名 不存在的字段不去拼接更新
            $allColumn = Schema::getColumnListing($tableName);
            $updateColumn = collect($updateColumn)->filter(function ($value) use ($allColumn) {
                return in_array($value, $allColumn, true);
            })->all();
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            if (empty($primaryKey)) {
                $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
                unset($updateColumn[0]);
            } else {
                $primaryKeyIndex = array_search($primaryKey, $updateColumn);
                if ($primaryKeyIndex === false) {
                    throw new \Exception('主键不存在');
                }
                $referenceColumn = $primaryKey;
                unset($updateColumn[$primaryKeyIndex]);
            }

            // 拼接sql语句
            $updateSql = "UPDATE " . $tableName . " SET ";
            $sets = [];
            $bindings = [];
            foreach ($updateColumn as $uColumn) {
                $setSql = "`" . $uColumn . "` = CASE ";
                foreach ($multipleData as $data) {
                    $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                    $bindings[] = $data[$referenceColumn];
                    $bindings[] = $data[$uColumn];
                }
                $setSql .= "ELSE `" . $uColumn . "` END ";
                $sets[] = $setSql;
            }
            $updateSql .= implode(', ', $sets);
            $whereIn = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $bindings = array_merge($bindings, $whereIn);
            $whereIn = rtrim(str_repeat('?,', count($whereIn)), ',');
            $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
            if ($where) {
                $updateSql .= " AND {$where}";
            }
            // 传入预处理sql语句和对应绑定数据
            return DB::update($updateSql, $bindings);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param BaseEntity $entity
     * @return mixed
     * @throws \Exception
     */
    public function addSingle(BaseEntity $entity)
    {
        if (self::class === static::class) throw new \Exception('[BaseModel]不能在BaseModel调用addSingle方法，因为BaseModel为父类，没有$table属性没有值。');

        $primaryKey = $this->getKeyName();

        if($this->incrementing) {
            $entity->addNonFillItem($primaryKey);
        }

        $entity->$primaryKey = $this->newQuery()->create($entity->getNonNullFillData())->getKey();
        return $entity->$primaryKey;
    }

    /**
     * @param BaseEntity $entity
     * @return bool|int
     * @throws \Exception
     */
    public function updateSingle(BaseEntity $entity)
    {
        if (self::class === static::class) throw new \Exception('[BaseModel]不能在BaseModel调用updateSingle方法，因为BaseModel为父类，没有$table属性没有值。');

        $primaryKey = $this->getKeyName();

        if (is_null($entity->$primaryKey)) throw new \Exception('没有主键，不能Update。');

        $entity->addNonFillItem($primaryKey);
        return $this->newQuery()->where($primaryKey, '=', $entity->$primaryKey)->update($entity->getFillData());
    }
}
