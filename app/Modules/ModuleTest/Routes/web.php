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

Route::prefix('moduletest')->group(function() {
    Route::get('/', 'ModuleTestController@index');
});

Route::prefix('moduletest/create')->group(function() {
    Route::get('/', 'ModuleTestController@create');
});
