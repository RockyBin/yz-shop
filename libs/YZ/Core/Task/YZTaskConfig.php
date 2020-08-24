<?php
define('IS_WIN',stripos(PHP_OS, 'WIN') !== false);
define('TASK_DIR', realpath(dirname(__FILE__).'/../../../../tasks'));
define('FAIL_RETRY', 5);
// 默认都为PHP
define('PHP_PATH', IS_WIN ? 'php' : 'php');
// 最大php进程数目，避免并发过大
define('MAX_NUM', 100);
