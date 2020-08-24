<?
function connectdb(){
	$conf = getEnvInfo();
	$dbconn = new PDO("mysql:host=".$conf['DB_HOST_WRITE'].";port=".$conf['DB_PORT_WRITE'].";dbname=".$conf['DB_DATABASE'].";charset=utf8",$conf['DB_USERNAME_WRITE'],$conf['DB_PASSWORD_WRITE']);
	return $dbconn;
}

function getEnvInfo(){
	$contents = file('../../.env');
	foreach($contents as $line){
		if(strpos($line,'=') === false) continue;
		$key = trim(substr($line, 0, strpos($line,'=')));
		$val = trim(substr($line, strpos($line,'=') + 1));
		$conf[$key] = $val;
	}
	return $conf;
}

function query($dbconn, $table,$data){
	$fields = array_keys($data);
	$sql = "INSERT INTO ".$table." (`".implode("`,`",$fields)."`) ";
	$values = array();
	$params = [];
	foreach($fields as $field){
		$value = $data[$field];
		$values[] = ":$field";
		$params[$field] = $value;
	}
	$sql .= "VALUES(".implode(",",$values).");";
	$statement = $dbconn->prepare($sql);
	if(is_array($params)){
		foreach($params as $key => $val){
			$statement->bindValue(':'.$key, $val);
		}
	}
	if(!$statement->execute()){
		throw new Exception("query data error: ".var_export($statement->errorInfo(),true));
	}
	return $statement;
}

function restore1(){
	$dir = 'G:\yz-data-2\yz-comdata-backup\14918127651418\2017-11-24\database_289275';
	$conn = connectdb();

	require($dir.'/table_tbl_site.php');
	foreach($table_tbl_site as $site){
		query($conn, 'tbl_site',$site);
	}

	require($dir.'/table_tbl_siteadmin.php');
	foreach($table_tbl_siteadmin as $admin){
		query($conn, 'tbl_siteadmin',$admin);
	}

	require($dir.'/table_tbl_backuplog.php');
	foreach($table_tbl_backuplog as $log){
		query($conn, 'tbl_backuplog',$log);
	}

	echo "finish!";
}

function restore2(){
	$dir = '/mnt/datadisk/wwwroot/YZ-Shop/public/comdata/2019-09-16/database_1326';
	$hdir = opendir($dir);
	$conn = connectdb();
	while($file = readdir($hdir)){
		if($file == '.' || $file == '..') continue;
		$table = str_replace('table_','',$file);
		$table = str_replace('.php','',$table);
		require($dir.'/table_'.$table.'.php');
		$var = 'table_'.$table;
		foreach(${$var} as $log){
			query($conn, $table,$log);
		}
	}
	closedir($hdir);
	echo "finish!";
}

function restore3(){
	$conn = connectdb();
	$arr = array('wx_weixininfo','wx_autoback','wx_group','wx_groupmessage','wx_keyword','wx_users','wx_menus','wx_newsmessage');
	foreach($arr as $table){
		require('G:\yz-data-2\yz-comdata-backup\14909329785783\2017-06-27\database_182545/table_'.$table.'.php');
		$var = 'table_'.$table;
		foreach(${$var} as $log){
			query($conn, $table,$log);
		}
	}
	echo "finish!";
}

$func = $_REQUEST['action'];
if($func) $func();
?>