<?php

namespace SwooleTW\Http\Server;

use Exception;
use SwooleTW\Http\Coroutine\Context;
use Throwable;
use Swoole\Process;
use Swoole\Server\Task;
use Illuminate\Support\Str;
use SwooleTW\Http\Helpers\OS;
use SwooleTW\Http\Server\Sandbox;
use SwooleTW\Http\Server\PidManager;
use SwooleTW\Http\Task\SwooleTaskJob;
use Illuminate\Support\Facades\Facade;
use SwooleTW\Http\Websocket\Websocket;
use SwooleTW\Http\Transformers\Request;
use SwooleTW\Http\Server\Facades\Server;
use SwooleTW\Http\Transformers\Response;
use SwooleTW\Http\Concerns\WithApplication;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use SwooleTW\Http\Concerns\InteractsWithWebsocket;
use Symfony\Component\Console\Output\ConsoleOutput;
use SwooleTW\Http\Concerns\InteractsWithSwooleQueue;
use SwooleTW\Http\Concerns\InteractsWithSwooleTable;
use Symfony\Component\Debug\Exception\FatalThrowableError;

/**
 * Class ResetSuperGlobalVars
 */
class ResetSuperGlobalVars
{
    public static function resetAll($swooleRequest,Container $app,Sandbox $sandbox){
        unset($GLOBALS); //注销PHP的默认超全局变量，以便不同的请求将此变量污染
        $_GET = $_COOKIE = $_POST = $_FILES = $_SESSION = $_SERVER = [];
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
        if(strpos($_SERVER['HTTP_HOST'],':') !== false) $_SERVER['HTTP_HOST'] = substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],':'));
        $_SERVER['SERVER'] = 'swoole';
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
        $_SERVER['DOCUMENT_ROOT'] = public_path();
        $_SERVER['DOCUMENT_ROOT'] = public_path();
        $_SERVER['SWOOLE_REQUEST_ID'] = $swooleRequest->fd.'_'.$swooleRequest->streamId.'_'.time();
        $_REQUEST = array_merge($_COOKIE ?? [],$_POST ?? [],$_GET ??[]);
        Context::setData('_SERVER', $_SERVER);
    }
}
