<?php
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

function validate($user, $pass) {
	$users = ['admin'=>'hsq3389', 'hsq1011'=>'hsq3389'];
		if(isset($users[$user]) && $users[$user] === $pass) {
		return true;
	} else {
		return false;
	}
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
	return mb_convert_encoding(implode("",$res), "GBK",'utf-8'); 
}

define('HOST', '0.0.0.0');
define('PORT', 888);

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
	swoole_set_process_name(getProcessName($serv->host, $serv->port));
});

$http->on('WorkerStart', function ($serv, $worker_id) {
	@opcache_reset();
});

$http->on('request', function ($swooleRequest, $swooleResponse) {
	$auth = $swooleRequest->header['authorization'];
	if($auth){
		$userpass = base64_decode(trim(preg_split('/\s+/',$auth)[1]));
		$userpass = explode(':',$userpass);
	}
	if(!validate($userpass[0], $userpass[1])) {
		$swooleResponse->status(401);
		$swooleResponse->header('WWW-Authenticate', 'Basic realm="git cmd tool"', true);
		$swooleResponse->end('需要用户名和密码才能继续访问');
		return;
	}
	$cmd = $swooleRequest->get['cmd'];
	$p = $swooleRequest->get['p'];
	
	$html = "<pre>";
	$dir = '/mnt/YZ-Shop/YZ-Shop';
	if($p == 'admin') $dir = '/mnt/YZ-Shop/YZ-Shop/public/shop/admin';
	if($p == 'front') $dir = '/mnt/YZ-Shop/YZ-Shop/public/shop/front';
	$html .= "cd ".$dir."\ngit ".$cmd."\n";
	$html .= execCmd("cd ".$dir."\ngit ".$cmd."\n");
	$html .= "</pre>";
    $swooleResponse->end($html);
	return;
});

$http->start();