<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/3/29
 * Time: 13:56
 */
require_once 'YZTaskConfig.php';
function addTask($cmd, $retry = 0, $startTime = '')
{
    $task = [
        'cmd' => $cmd,
        'retry' => $retry,
        'startTime' => $startTime
    ];
    $task = json_encode($task);
    // 写入到路径
    $taskPath = TASK_DIR;
    is_dir($taskPath) or mkdir($taskPath);
    $taskName = 'task' . date('YmdHis') . randInt(1000, 9999) . '.json';
    $file = fopen($taskPath . '/' . $taskName, 'a+');
    fwrite($file, $task);
    fclose($file);
}

function runSingleTask($task, $taskPath = '')
{
    // 先删掉json文件
    if ($taskPath && is_file($taskPath)) {
        unlink($taskPath);
    }
    // 解析任务信息
    $taskInfo = json_decode($task, true);
    $cmd = $taskInfo['cmd'];
    $startTime = $taskInfo['startTime'];
    if (IS_WIN) {
        $runCmd = 'start /b ' . PHP_PATH . ' ' . $cmd . ' > NUL';
    } else {
        $runCmd = PHP_PATH . ' ' . $cmd . ' > /dev/null &';
    }
    $p = popen($runCmd, 'r');
    // 失败重新加回去
    if (!$p) {
        // 小于重试次数才加回去
        if ($taskInfo['retry'] < FAIL_RETRY) {
            ++$taskInfo['retry'];
            addTask($cmd, $taskInfo['retry'], $startTime);
        }
    }
}

function getPhpProcessNum(){
	$num = 0;
	@exec("ps -ef",$lines);
    foreach($lines as $line){
        if(preg_match('/'.PHP_PATH.'\s+/i',$line)) $num++;
    }
    return $num;
}

function runAllTask()
{
    if(!is_dir(TASK_DIR)) return;
    while (true) {
    	$num = getPhpProcessNum();
        $taskDir = opendir(TASK_DIR);
        while($file = readdir($taskDir)){
            if($file == '.' || $file == '..') continue;
            $num++;
            if($num > MAX_NUM) {
            	echo "the quantity of php cli process > ".MAX_NUM.",wait next round\r\n";
            	break;
            }
            $taskPath = TASK_DIR . '/' . $file;
            $taskInfo = file_get_contents($taskPath);
            runSingleTask($taskInfo, $taskPath);
        }
        closedir($taskDir);
        sleep(2);
    }

}

