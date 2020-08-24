<?php
//72ad后台相关路由
Route::prefix('shop/sysmanage')->group(function () {
    // 移动端模板管理
    Route::any('/template/list', 'SysManage\Template\TemplateController@getList'); // 列出模板
    Route::any('/template/add', 'SysManage\Template\TemplateController@add'); // 添加模板
    Route::any('/template/info', 'SysManage\Template\TemplateController@getInfo'); // 获取模板信息
    Route::any('/template/edit', 'SysManage\Template\TemplateController@edit'); // 修改模板
    Route::any('/template/delete', 'SysManage\Template\TemplateController@delete'); // 删除模板
    Route::any('/template/industry', 'SysManage\Template\TemplateController@getIndustryList'); // 列出行业
	//Route::any('/style/color/add', 'Admin\UI\StyleColor\StyleColorController@addStyleColor'); // 添加配色
});