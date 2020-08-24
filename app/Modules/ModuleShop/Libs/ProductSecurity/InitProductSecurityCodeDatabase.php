<?php
/**
 * 用于初始化防伪码表
 * User: liyaohui
 * Date: 2019/11/1
 * Time: 10:42
 */

namespace App\Modules\ModuleShop\Libs\ProductSecurity;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InitProductSecurityCodeDatabase
{
    public static function initProductSecurityCodeTable()
    {
        $path = config('database.connections.system_security_code_sqlite.database');
        if (!is_file($path)) {
            touch($path);
        }
        $connec = Schema::connection('system_security_code_sqlite');
        // 防伪码数据表结构
        $connec->create('tbl_product_security_code', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('site_id', false, true)->nullable(false);
            $table->integer('batch_id', false, true)->nullable(false);        // 批次id
            $table->string('batch_code', 30)->nullable(false); // 批次号
            $table->integer('product_id', false, true)->default(0); // 关联的商品id
            $table->string('code', 30)->nullable(false); // 防伪码
            $table->integer('query_times')->default(0); // 查询次数
            $table->dateTime('last_query_at')->nullable();// 最后一次查询时间
            $table->index('batch_id', 'IX_batch_id');
            $table->index('product_id', 'IX_product_id');
            $table->unique(['site_id', 'code'], 'IX_site_code');
        });
    }
}