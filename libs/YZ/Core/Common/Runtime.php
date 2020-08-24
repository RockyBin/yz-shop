<?php
namespace YZ\Core\Common;

use Closure;

class Runtime
{
    static private $points = [];

    static public function startPoint(string $pointName)
    {
        self::$points[$pointName] = microtime(true);
    }

    static public function endPoint(string $pointName)
    {
        $endTime = microtime(true);
        $startTime = self::$points[$pointName];
        unset(self::$points[$pointName]);
        if (is_null($startTime)) {
            echo "没有设置{$pointName}开始节点！\r\n";
            return;
        }
        $total = $endTime - $startTime;
        echo "$pointName:$total\r\n";
    }
}