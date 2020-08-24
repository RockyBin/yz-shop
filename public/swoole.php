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

function cleanRequest($app)
{
    $app->forgetInstance('request');
    \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
}

function cleanSession($app)
{
    if (!$app->offsetExists('session')) {
        return;
    }

    $ref = new \ReflectionObject($app->make('session'));
    $drivers = $ref->getProperty('drivers');
    $drivers->setAccessible(true);
    $drivers->setValue($app->make('session'), []);

    $app->forgetInstance('session.store');
    \Illuminate\Support\Facades\Facade::clearResolvedInstance('session.store');

    if ($app->offsetExists('redirect')) {
        $redirect = $app->offsetGet('redirect');
        $redirect->setSession($app->make('session.store'));
    }
}

function cleanCookie($app)
{
    if (!$app->offsetExists('cookie')) {
        return;
    }
    $appCookie = $app->offsetGet('cookie');
    $cookies = $appCookie->getQueuedCookies();
    foreach ($cookies as $name => $cookie) {
        $appCookie->unqueue($name);
    }
}

define('HOST', '0.0.0.0');
define('PORT', 9501);

if ($argv[1] == 'reload') {
	reload(HOST, PORT);
}

if ($argv[1] == 'stop') {
	stop(HOST, PORT);
}

$http = new swoole_http_server(HOST, PORT);

$http->set([
    'worker_num' => 2,
    'max_request' => 5000,
	'daemonize' => 0,
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
    //加载index文件的内容
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $GLOBALS['oriApp'] = $app;
});

//监听http请求
$http->on('request', function ($swooleRequest, $swooleResponse) {
    //unset($GLOBALS); //注销PHP的默认超全局变量，以便不同的请求将此变量污染
    $app = clone $GLOBALS['oriApp'];
    (new \Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
	\Illuminate\Container\Container::setInstance($app);
    $_GET = $_COOKIE = $_POST = $_FILES = $_SERVER;
    //server信息
    if (isset($swooleRequest->server)) {
        foreach ($swooleRequest->server as $k => $v) {
            $_SERVER[strtoupper($k)] = $v;
        }
    }

    //header头信息
    if (isset($swooleRequest->header)) {
        foreach ($swooleRequest->header as $k => $v) {
            $_SERVER['HTTP_'.str_replace('-','_',strtoupper($k))] = $v;
        }
    }

    //get请求
    if (isset($swooleRequest->get)) {
        foreach ($swooleRequest->get as $k => $v) {
            $_GET[$k] = $v;
        }
    }

    //post请求
    if (isset($swooleRequest->post)) {
        foreach ($swooleRequest->post as $k => $v) {
            $_POST[$k] = $v;
        }
    }

    //文件请求
    if (isset($swooleRequest->files)) {
        foreach ($swooleRequest->files as $k => $v) {
            $_FILES[$k] = $v;
        }
    }

    //cookies请求
    if (isset($swooleRequest->cookie)) {
        foreach ($swooleRequest->cookie as $k => $v) {
            $_COOKIE[$k] = $v;
        }
    }
    
    $_SERVER['SERVER'] = 'swoole';
    $_SERVER['SERVER_NAME'] = $_SERVER['HOST'];
    $_SERVER['DOCUMENT_ROOT'] = __DIR__;
    $_REQUEST = array_merge($_COOKIE ?? [],$_POST ?? [],$_GET ??[]);

	//因为微信分享的时候，会过滤掉#号和后面的内容，所以分享时，将URL里的#替换为vuehash
	//当进入页面时，重新将vuehash替换回来
	if(strpos($_SERVER['REQUEST_URI'],'vuehash') !== false) {
		$swooleResponse->status(301, "vue redirect");
		$swooleResponse->header("Location", str_replace("vuehash","#",$_SERVER['REQUEST_URI']));
		$swooleResponse->end("vue redirect");
	}

    cleanCookie($app);
    cleanSession($app);
    //cleanRequest($app);

    //加载laravel请求核心模块
    //$app->forgetInstance(\Illuminate\Cookie\CookieServiceProvider::class);
    //$app->register(\Illuminate\Cookie\CookieServiceProvider::class,[],true);

    //$app->forgetInstance(\Illuminate\Session\SessionServiceProvider::class);
    //$app->register(\Illuminate\Session\SessionServiceProvider::class,[],true);

    \Illuminate\Http\Request::enableHttpMethodParameterOverride();
    $laravelRequest = \Illuminate\Http\Request::createFromBase(new \Symfony\Component\HttpFoundation\Request($swooleRequest->get ?? [], $swooleRequest->post ?? [], [], $swooleRequest->cookie ?? [], $swooleRequest->files ?? [], $_SERVER, $swooleRequest->rawContent()));

    $app->forgetInstance(\App\Providers\YZSiteServiceProvider::class);
    $app->register(\App\Providers\YZSiteServiceProvider::class);

    // restful 接口才用到的，我们可以不用
    /*if (0 === strpos($request->header['content-type'], 'application/x-www-form-urlencoded')
        && in_array(strtoupper($request->server['request_method]']), ['PUT', 'DELETE', 'PATCH'])
    ) {
        parse_str($request->getContent(), $data);
        $laravelRequest->request = new \Symfony\Component\HttpFoundation\ParameterBag($data);
    }*/

    ob_start();//启用缓存区
    $app->instance('request', $laravelRequest);
    $laravelResponse = $app->handle($laravelRequest);
    $laravelResponse->send();
    $app->terminate($laravelRequest, $laravelResponse);
    $res = ob_get_contents();//获取缓存区的内容
    ob_end_clean();//清除缓存区
    //unset($GLOBALS);
    
	//输出缓存区域的内容
	$contentType = $laravelResponse->headers->get('Content-Type');
	if(!$contentType) $contentType = "text/html; charset=utf-8";
	$swooleResponse->header("Content-Type", $contentType);
	
	//输出cookie
	$hasIsRaw = null;
	$cookies = $laravelResponse->headers->getCookies();
	foreach ($cookies as $cookie) {
		if ($hasIsRaw === null) {
			$hasIsRaw = method_exists($cookie, 'isRaw');
		}
		$setCookie = $hasIsRaw && $cookie->isRaw() ? 'rawcookie' : 'cookie';
		$swooleResponse->$setCookie(
			$cookie->getName(),
			$cookie->getValue(),
			$cookie->getExpiresTime(),
			$cookie->getPath(),
			$cookie->getDomain(),
			$cookie->isSecure(),
			$cookie->isHttpOnly()
		);
	}
    $swooleResponse->end($res);
});

$http->start();
