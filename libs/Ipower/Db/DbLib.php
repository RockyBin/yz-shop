<?

namespace Ipower\Db;

class DbLib
{
    private $dbconn = null;
    protected $config = null;
    protected $fields = array();

    public function __construct($config)
    {
        if (is_a($config, 'PDO')) {
            $this->dbconn = $config;
            $this->config['type'] = 'mysql';
        } else if ($config['type'] == 'mysql') {
            $this->dbconn = new \PDO("mysql:host=" . $config['host'] . ";port=" . $config['port'] . ";dbname=" . $config['database'] . ";charset=" . $config['charset'], $config['username'], $config['password']);
            $this->config = $config;
        } else if ($config['type'] == 'sqlite') {
            $this->dbconn = new \PDO('sqlite:' . $config['database']);
            $this->config = $config;
        } else throw new \Exception('dbtype not supported');


    }

    public function getTables($dbName = null)
    {
        if ($this->config['type'] == 'mysql') {
            $sql = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ';
            $result = $this->query($sql);
            $info = array();
            foreach ($result as $key => $val) {
                $info[$key] = current($val);
            }
            return $info;
        } elseif ($this->config['type'] == 'sqlite') {
            $result = $this->query("SELECT name FROM sqlite_master WHERE type='table' "
                . "UNION ALL SELECT name FROM sqlite_temp_master "
                . "WHERE type='table' ORDER BY name");
            $info = array();
            foreach ($result as $key => $val) {
                $info[$key] = current($val);
            }
            return $info;
        }
    }

    public function getTablePk($table)
    {
        $fields = $this->getFields($table);
        //print_r($fields);
        foreach ($fields as $field) {
            if ($field['primary']) return $field['name'];
        }
        return '';
    }

    public function getFields($tableName, $refresh = 0)
    {
        if (!$refresh && array_key_exists($tableName, $this->fields)) return $this->fields[$tableName];
        //echo "load $tableName fields";
        if ($this->config['type'] == 'mysql') {
            list($tableName) = explode(' ', $tableName);
            if (strpos($tableName, '.')) {
                list($dbName, $tableName) = explode('.', $tableName);
                $sql = 'SHOW COLUMNS FROM `' . $dbName . '`.`' . $tableName . '`';
            } else {
                $sql = 'SHOW COLUMNS FROM `' . $tableName . '`';
            }

            $result = $this->query($sql);
            $info = array();
            if ($result) {
                foreach ($result as $key => $val) {
                    if (\PDO::CASE_LOWER != $this->dbconn->getAttribute(\PDO::ATTR_CASE)) {
                        $val = array_change_key_case($val, CASE_LOWER);
                    }
                    $info[$val['field']] = array(
                        'name' => $val['field'],
                        'type' => $val['type'],
                        'notnull' => $val['null'] == 'NO',
                        'default' => $val['default'],
                        'primary' => (strtolower($val['key']) == 'pri'),
                        'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                    );
                }
            }
            $this->fields[$tableName] = $info;
            return $info;
        } elseif ($this->config['type'] == 'sqlite') {
            list($tableName) = explode(' ', $tableName);
            $result = $this->query('PRAGMA table_info( ' . $tableName . ' )');
            $info = array();
            if ($result) {
                foreach ($result as $key => $val) {
                    $name = $val['field'] ? $val['field'] : $val['name'];
                    $info[$name] = array(
                        'name' => $name,
                        'type' => $val['type'],
                        'notnull' => array_key_exists('null', $val) ? (bool)($val['null'] === '') : $val['notnull'], // not null is empty, null is yes
                        'default' => ($val['default'] != '' ? $val['default'] : $val['dflt_value']),
                        'primary' => (strtolower($val['dey']) == 'pri') || $val['pk'] == '1',
                        'autoinc' => (strtolower($val['extra']) == 'auto_increment'), //sqlite 是不会显示是否自增的
                    );
                }
            }
            $this->fields[$tableName] = $info;
            return $info;
        }
    }

    public function getDbConn()
    {
        return $this->dbconn;
    }

    public function execute($sql)
    {
        return $this->dbconn->exec($sql);
    }

    //$autofetch 自动将结果 fetch 到数组（一般用于数据量不大的情况），如果此值为false,则需要在外部进行fetch(这种一般用在数据量比较大的情况，如果自动fetch，会导致占用内存过多)
    public function query($sql, $params = array(), $autofetch = true)
    {
        $statement = $this->dbconn->prepare($sql);
        if (is_array($params)) {
            foreach ($params as $key => $val) {
                $statement->bindValue(':' . $key, $val);
            }
        }
        if (!$statement->execute()) {
            throw new \Exception("query data error: $sql : " . var_export($statement->errorInfo(), true));
        }
        if ($autofetch) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            return $statement;
        }
    }

    public function insert($table, $data)
    {
        $fields = array_keys($data);
        $sql = "insert into " . $table . "(`" . implode('`,`', $fields) . "`) values(:" . implode(',:', $fields) . ")";
        $statement = $this->dbconn->prepare($sql);
        if (!$statement) {
            echo "insert into table $table error:";
            print_r($this->dbconn->errorInfo());
            return;
        }
        foreach ($data as $key => $val) {
            if (!$statement->bindValue(':' . $key, $val)) {
                throw new \Exception("bind param value error: " . var_export($statement->errorInfo(), true));
            }
        }
        if (!$statement->execute()) {
            throw new \Exception("insert data error: " . var_export($statement->errorInfo(), true));
        }
    }

    //要注意 $data 和 $whereparams 数组的键名不能重复，否则会导致更新条件的参数设置不正确
    public function update($table, $data, $where = '', $whereparams = array())
    {
        $sql = "update " . $table . " SET ";
        foreach ($data as $key => $val) {
            $fields[] = "`" . $key . "` = :$key";
        }
        $sql .= implode(',', $fields);
        if ($where) {
            $sql .= " where " . $where;
        }
        $statement = $this->dbconn->prepare($sql);
        if (!$statement) {
            echo "insert into table $table error:";
            print_r($this->dbconn->errorInfo());
            return;
        }
        foreach ($data as $key => $val) {
            $statement->bindValue(':' . $key, $val);
        }
        foreach ($whereparams as $key => $val) {
            $statement->bindValue(':' . $key, $val);
        }
        if (!$statement->execute()) {
            throw new \Exception("update data error: " . var_export($statement->errorInfo(), true));
        }
    }

    public function getLastInsertID()
    {
        return $this->dbconn->lastInsertId();
        /*
        if ($this->config['type'] == 'sqlite') return $this->getLastInsertIDForSqlite();
        elseif ($this->config['type'] == 'mysql') return $this->getLastInsertIDForMysql();
        elseif ($this->config['type'] == 'sqlsrv') return $this->getLastInsertIDForMssql();
        else return -1;
        */
    }

    private function getLastInsertIDForMysql()
    {
        $result = $this->query("select LAST_INSERT_ID() as 'insid'", null, true);
        return $result[0]['insid'];
    }

    private function getLastInsertIDForSqlite()
    {
        $result = $this->query("select last_insert_rowid() as 'insid'", null, true);
        return $result[0]['insid'];
    }

    private function getLastInsertIDForMssql()
    {
        $result = $this->query("SELECT @@IDENTITY as 'insid'", null, true);
        return $result[0]['insid'];
    }

    public function close()
    {
        $this->dbconn = null;
    }
}

?>