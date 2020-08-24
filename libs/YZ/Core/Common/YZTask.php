<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/3/28
 * Time: 20:00
 */

namespace YZ\Core\Common;


use Illuminate\Support\Facades\File;
use YZ\Core\Logger\Log;

class YZTask
{
    public static function addTask($cmd, $arg, $retry = 1,$startTime = '')
    {
        $task = [
            'cmd' => $cmd,
            'arg' => base64_encode(json_encode($arg)),
            'retry' => $retry,
            'startTime' => $startTime
        ];
        $task = json_encode($task);
        // 写入到路径
        $taskPath = config('yztask.task_dir', base_path() . '/task');
        File::isDirectory($taskPath) or File::makeDirectory($taskPath, 0777, true, true);
        $taskName = 'task' . date('YmdHis') . randInt(1000, 9999) . '.json';
        File::put($taskPath . '/' . $taskName, $task);
    }

    public static function runSingleTask($task, $taskFile, $isWin, $phpPath)
    {
        try {
            // 先删掉json文件
            File::delete($taskFile);
            // 解析任务信息
            $taskInfo = json_decode($task, true);
            $cmd = $taskInfo['cmd'];
            $arg = json_decode(base64_decode($taskInfo['arg']), true);
            $startTime = $taskInfo['startTime'];
            if ($isWin) {
                $runCmd = 'start /b ' . $phpPath . ' ' . $cmd . ' ' . $arg . ' > null';
            } else {
                $runCmd = $phpPath . ' ' . $cmd . ' ' . $arg . '> /dev/null';
            }
            $p = popen($runCmd, 'r');
            // 失败重新加回去
            if (!$p) {
                // 小于重试次数才加回去
                if ($taskInfo['retry'] < config('yztask.retry', 5)) {
                    ++$taskInfo['retry'];
                    self::addTask($cmd, $taskInfo['arg'], $taskInfo['retry'], $startTime);
                }
            }
        } catch (\Exception $e) {
            Log::writeLog('CommandError', $e->getMessage());
        }
    }

    public static function runAllTask()
    {
        $taskDir = config('yztask.task_dir', base_path() . '/task');
        $isWin = stripos(PHP_OS, 'WIN') !== false;
        if ($isWin) {
            $phpPath = config('yztask.php_path_win');
        } else {
            $phpPath = config('yztask.php_path_linux');
        }
        while (true) {
            $allTaskFile = File::files($taskDir);
            foreach ($allTaskFile as $task) {
                $taskInfo = $task->getContents();
                self::runSingleTask($taskInfo, $task->getRealPath(), $isWin, $phpPath);
            }
            sleep(1);
        }
    }
}