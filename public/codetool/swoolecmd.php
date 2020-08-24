<?php
//这是一个工具服务器，有来接收一些命令以便于执行一些任务
function getProcessName($host, $port){
	return sprintf('swoole_%s_%s',$host, $port);
}

function reload($host, $port){
	$pid = system('pidof '.getProcessName($host, $port));
	$cmd = 'kill -USR1 '.$pid;
	system($cmd);
	echo "$cmd\n";
	echo "reload success\n";
	exit;
}

function stop($host, $port){
	$pid = system('pidof '.getProcessName($host, $port));
	$cmd = 'kill '.$pid;
	system($cmd);
	echo "$cmd\n";
	echo "stop success\n";
	exit;
}

function execCmd($cmd,$readOutPut = 1)
{
	$descriptorspec = array(
	   0 => array("pipe", "r"),  // stdin
	   1 => array("pipe", "w"),  // stdout
	   2 => array("pipe", "w"),  // stderr
	);

	$res = array();
	$process = proc_open($cmd, $descriptorspec, $pipes, dirname(__FILE__), null);
	if(!$readOutPut) return;
	while($line = fgets($pipes[1])){
		if(trim($line)){
			if(strpos($line,"have new mail in") !== false) continue;
			if(strpos($line,"HOME environment variable not set") !== false) continue;
			array_push($res,$line);
		}
	}
	fclose($pipes[1]);
	while($line = fgets($pipes[2])){
		if(trim($line)){
			if(strpos($line,"have new mail in") !== false) continue;
			if(strpos($line,"HOME environment variable not set") !== false) continue;
			array_push($res,$line);
		}
	}
	fclose($pipes[2]);
	proc_close($process);
	return $res;
}

define('HOST', '10.0.71.254');
define('PORT', 10000);

if ($argv[1] == 'reload') {
	reload(HOST, PORT);
}

if ($argv[1] == 'stop') {
	stop(HOST, PORT);
}

$http = new swoole_http_server(HOST, PORT);

$http->set([
    'worker_num' => 1,
    'max_request' => 5000,
	'daemonize' => 1,
	'enable_static_handler' => true,
	'document_root' => __DIR__,
	'http_compression' => true,
	'package_max_length' => 204800000,
]);

$http->on("start", function($serv){
	swoole_set_process_name(getProcessName($serv->host, $serv->port)); //指定服务进程名称，为后台平滑重启等作准备
});

//工作进程启动
$http->on('WorkerStart', function ($serv, $worker_id) {
	@opcache_reset();
});

//监听http请求
$http->on('request', function ($swooleRequest, $swooleResponse) {
	//reload主系统的swoole_http_server
	if($swooleRequest->get['cmd'] == 'reloadswoole'){
		$res = execCmd("/usr/php-7.2.9/bin/php /mnt/YZ-Shop/YZ-Shop/artisan swoole:http reload");
		$res = implode("",$res);
	}
	//restart主系统的swoole_http_server
	if($swooleRequest->get['cmd'] == 'restartswoole'){
		$res = execCmd("/usr/php-7.2.9/bin/php /mnt/YZ-Shop/YZ-Shop/artisan swoole:http restart");
		$res = implode("",$res);
	}
    $swooleResponse->end($res);
});

$http->start();
