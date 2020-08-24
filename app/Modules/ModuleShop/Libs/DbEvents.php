<?php

namespace App\Modules\ModuleShop\Libs;

use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use YZ\Core\Common\DataCache;
use YZ\Core\Member\Member;

/**
 * 数据表更新时自动处理缓存的类，此类在 vendor\laravel\framework\src\Illuminate\Database\Connection.php affectingStatement 中调用
 */
class DbEvents
{
    public function updated($query, $params = [])
    {
        //echo $query; print_r($params);

        $cacheKeys = DataCache::getKeys();
        //处理会员记录缓存
        if (preg_match('/update\s+`?tbl_member`?\s+/i', $query)) {
            foreach ($cacheKeys as $key) {
                if (strpos($key, Member::class . '_member_') !== false) {
                    DataCache::remove($key);
                    //echo "cache member cache";
                }
            }
        }

        //处理云仓主记录缓存
        if (preg_match('/update\s+`?tbl_cloudstock`?\s+/i', $query)) {
            foreach ($cacheKeys as $key) {
                if (strpos($key, CloudStock::class) !== false) {
                    DataCache::remove($key);
                    //echo "cache cloud stock cache";
                }
            }
        }
    }
}
