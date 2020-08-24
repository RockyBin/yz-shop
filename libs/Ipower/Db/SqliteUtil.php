<?
namespace Ipower\Db;

class SqliteUtil{
	/**
	* Mysql表字段一键生成创建sqlite的SQL
	* $db 数据库 PDO 对象
	* $tbname 表名
	* $is_blob 需要保存的图片二进制数据，非数组 默认为false
	* $retbname 自定义生成sqlite的表名
	* return SQL的语句形式
	*/
	public static function getCreateTableSqlFromMysql($db, $tbname, $is_blob = false, $retbname = false){
	 
		 $fields_info = self::getMysqlTableInfo($db,$tbname);
		
		 //创建数据的SQL语句
		 $retbname = $retbname == true ? $retbname : $tbname;
		 $creat_sql = self::getCreataTableSql($retbname,$fields_info);
		 
		 //生成索引 sql		 
		 $index_data = self::getMysqlIndexInfo($db,$tbname);
		 if (count($index_data) > 0)
		 {
			foreach ($index_data as $item)
			{
				if($item["ispk"] == 1) continue;
				$unique = "";
				$isunique = $item["isunique"];
				if ($isunique == "1") $unique = "unique";
				$indexname = $item["name"];
				if ($indexname == "PRIMARY") $indexname = "PK_" . $tbname;
				$create_idx = "create " . $unique . " index  ";                //创建普通索引
				$create_idx .= $indexname . " on " . $tbname . "(" . $item["cols"] . ")";
				$index_sqls[] = $create_idx;
			}
		 }

		 //生成触发器
		 $trigger_data = self::getMysqlTriggerInfo($db,$tbname);
		 if (count($trigger_data) > 0)
		 {
			foreach ($trigger_data as $item)
			{
				$trigger_sqls[] = "CREATE TRIGGER ".$item['name']." ".$item['action_timing'].' '.$item['event'].' ON '.$tbname.' FOR EACH '.$item['action_orientation'].' '.$item['action_statement'];
			}
		 }
		 
		 return array('create' => $creat_sql,'index' => $index_sqls,'trigger' => $trigger_sqls);
	}

	private static function getCreataTableSql($table,$fields_info)
	{
		$pk = "";
		$createsql = "";  //创建表语句
		$createsql = "create table " . $table . "(";
		foreach ($fields_info as $field)
		{
			$name = $field["ColumnName"];
			$type = $field["ColumnType"];
			$length = $field["Length"];
			$dpoint = $field["DecimalPoint"];
			$defaultvalue = $field["DefaultValue"];
			$isautoinc = $field["IsAutoInc"];
			$ispk = $field["IsPK"];
			$cannull = $field["CanNull"];
			$createsql .= "`" . $name . "` ";
			$createsql .= "" . (preg_match('/char$/i',$type) ? 'nvarchar':$type) . " ";
			if (substr($type,-4) == "char") $createsql .= "(" . $length . ") ";
			elseif (substr($type,-7) == "decimal") $createsql .= "(" . $length . "," . $dpoint . ") ";
			//if ($isautoinc == "Y") $createsql .= " AUTOINCREMENT ";
			if ($cannull == "Y") $createsql .= " NULL ";
			else $createsql .= " NOT NULL ";
			if ($defaultvalue != "")
			{
				if (preg_match('/(char|datetime|text)$/i',$type)) $createsql .= " DEFAULT '" . $defaultvalue . "'";
				elseif (preg_match('/bit$/i',$type)) $createsql .= " DEFAULT 0";
				else $createsql .= " DEFAULT " . $defaultvalue;
			}
			if ($isautoinc == "Y") $createsql .= " AUTO_INCREMENT ";
			if ($ispk == "Y") $pk = $name;
			$createsql .= " ,";
		}
		if ($pk != "")
		{
			$createsql .= " PRIMARY KEY(`" .$pk. "`)";
		}
		else
		{
			$createsql = substr($createsql,0,strlen($createsql));
		}
		$createsql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8";

		return $createsql;
	}
	
	//$db PDO 连接 mysql 的对象
	private static function getMysqlTableInfo($db,$tbname){
		/*dt.Columns.Add("ColumnName",typeof(string));
		dt.Columns.Add("IsAutoInc", typeof(string));
		dt.Columns.Add("IsPK", typeof(string));
		dt.Columns.Add("ColumnType", typeof(string));
		dt.Columns.Add("Length", typeof(int));
		dt.Columns.Add("DecimalPoint", typeof(int));
		dt.Columns.Add("CanNull", typeof(string));
		dt.Columns.Add("DefaultValue", typeof(string));*/
		$data = array();
		$query = $db->query("SHOW FULL COLUMNS FROM " . $tbname);
		foreach ($query as $dr)
		{
			$drnew = array();
			$drnew["ColumnName"] = $dr["Field"];
			$drnew["IsAutoInc"] = strpos($dr["Extra"], "auto_increment") !== false ? "Y":"N";
			$drnew["IsPK"] = $dr["Key"] == "PRI" ? "Y" : "N";

			$Type = $dr["Type"];
			$ColumnType = $Type;
			$length = -1;
			$DecimalPoint = -1;
			if (strpos($ColumnType,"(") !== false)
			{
				$ColumnType = substr($ColumnType,0,strpos($ColumnType,"("));
				preg_match('/\((?<len>\d+)(,(?<dep>\d+))?\)/',$Type,$m);
				if ($m["len"])
				{
					$length = intval($m["len"]);
					if ($m["dep"] !== '') $DecimalPoint = intval($m["dep"]);
				}
			}
			$drnew["ColumnType"] = $ColumnType;
			$drnew["Length"] = $length;
			$drnew["DecimalPoint"] = $DecimalPoint;

			$Default = $dr["Default"];
			if ($Default == "NULL") $Default = "";
			$drnew["CanNull"] = $dr["Null"] == "YES" ? "Y" : "N";
			$drnew["DefaultValue"] = $Default;
			$data[] = $drnew;
		}
		return $data;
	}
	
	//$db PDO 连接 mysql 的对象
	private static function getMysqlIndexInfo($db,$tbname){
		 //生成索引 sql
		 $index_data = array();
		 $keys = array();
		 $query = $db->query("SHOW INDEX FROM " . $tbname);
		 $indextable = array();
		 foreach ($query as $r) $indextable[] = $r;
		 foreach ($indextable as $r) {
			 if (array_key_exists($r['Key_name'],$keys)) continue;
			 $keys[$r['Key_name']] = true;
			
			 $item = array();
			 $item['name'] = $r['Key_name'];
			 $item['ispk'] = $r['Key_name'] == 'PRIMARY' ? 1 : 0;
			 $item['isunique'] = $r['Non_unique'] == '0' ? 1 : 0;
			 $columns = "";
			 foreach ($indextable as $rc){
				if($rc["Key_name"] == $r['Key_name']){
					$columns .= $rc['Column_name'].',';
				}
			 }
			 $columns = substr($columns,0,strlen($columns) - 1);
			 $item["cols"] = $columns;
			 $index_data[] = $item;
		 }
		 return $index_data;
	}

	private static function getMysqlTriggerInfo($db,$tbname){
		 //生成索引 sql
		 $data = array();
		 $query = $db->query("SELECT * FROM information_schema.`TRIGGERS` where EVENT_OBJECT_TABLE = '".$tbname."'");
		 $indextable = array();
		 foreach ($query as $r){
			$data[] = array(
				'name' => $r['TRIGGER_NAME'],
				'event' => $r['EVENT_MANIPULATION'],
				'action_statement' => $r['ACTION_STATEMENT'],
				'action_orientation' => $r['ACTION_ORIENTATION'],
				'action_timing' => $r['ACTION_TIMING'],
			);
		 }
		 return $data;
	}

	public static function createSqliteDb($dbpath){
		$dbh = new \PDO('sqlite:'.$dbpath);
		$dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$dbh->exec('
		CREATE TABLE test(
		id integer NOT NULL,
		name varchar(32) NOT NULL,
		PRIMARY KEY(id)
		)');
		$dbh = null;
	}
}
?>