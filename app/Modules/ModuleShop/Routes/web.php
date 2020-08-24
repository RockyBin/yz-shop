<?php

/*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register web routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */

//前台路由
Route::prefix('shop')->group(function () {
    Route::get('/', 'ModuleShopController@index');
});

// 测试
include(__DIR__.'/web_test.php');

//用户中心路由
include(__DIR__.'/web_member.php');

//后台相关路由
include(__DIR__.'/web_admin.php');

//72ad后台相关路由
include(__DIR__.'/web_72ad.php');

//前台相关路由
include(__DIR__.'/web_front.php');

//前台CRM路由
include(__DIR__.'/web_crm.php');

//供应商平台路由
include(__DIR__.'/web_supplier.php');