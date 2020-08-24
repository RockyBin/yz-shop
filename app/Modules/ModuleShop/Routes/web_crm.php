<?php
//前台CRM路由
Route::prefix('shop/crm')->group(function () {
    Route::any('config/info', 'Crm\ConfigController@getConfig'); // 获取基础公共配置信息
    Route::any('error/report', "Crm\ErrorController@report"); // 记录前端小程序提交的错误信息
    Route::any('login/miniapp/auth', "Crm\LoginController@miniAppAuth"); // 小程序授权登录
    Route::any('login/miniapp/auth/info', "Crm\LoginController@wxAppAuthGetMobile"); // 小程序授权登录,信息返回(用于返回手机号码等信息)
    Route::any('login/miniapp/switch', "Crm\LoginController@miniAppSwitchAdmin"); // 切换登录帐户
    Route::any('login', "Crm\LoginController@login"); // 使用用户名密码登录
    Route::any('index', "Crm\IndexController@index"); // CRM首页
    Route::get('site/info', "Crm\IndexController@getSiteInfo"); // 获取网站信息
    Route::any('staff/unbind', "Crm\Staff\StaffController@unbind"); // 解除与企业的绑定
    Route::any('staff/bind', "Crm\Staff\StaffController@bind"); // 企业的绑定
    Route::any('login/member/add', "Crm\LoginController@add"); // 绑定新增客户
    Route::any('login/member/edit', "Crm\LoginController@edit"); // 修改客户

    //客户
    Route::any('member/add', "Crm\Member\MemberController@add"); // 新增客户
    Route::any('member/list', "Crm\Member\MemberController@getList"); // 客户列表
    Route::any('member/list/search/data', "Crm\Member\MemberController@getMemberListSearchData"); // 客户列表高级搜索需要的数据
    Route::any('member/info', "Crm\Member\MemberController@getInfo"); // 客户信息
    Route::any('member/edit', "Crm\Member\MemberController@edit"); // 修改客户信息
    Route::any('member/label/list', "Crm\Member\MemberController@getLabelList"); // 获取客户标签
    Route::any('member/label/edit', "Crm\Member\MemberController@editMemberLabel"); // 获取客户标签
    Route::any('member/level', "Crm\Member\MemberController@getMemberLevel"); // 会员等级列表
    Route::any('member/dealer/level', "Crm\Member\MemberController@getDealerLevel"); // 经销商商等级列表
    Route::any('member/distribution/level', "Crm\Member\MemberController@getDistributionLevel"); // 分销商等级列表
    Route::any('member/agent/level', "Crm\Member\MemberController@getAgentLevel"); // 代理商等级列表
    Route::any('member/agent/level/set', 'Crm\Member\MemberController@setAgentLevel'); // 修改代理等级
    Route::any('member/distributor/edit', "Crm\Member\MemberController@editDistributor"); // 修改分销商
    //员工
    Route::any('staff/info', "Crm\Staff\StaffController@getInfo"); // 客户信息
    Route::any('staff/edit', "Crm\Staff\StaffController@edit"); // 新增客户
    Route::any('staff/shop/list', "Crm\Staff\StaffController@getShopList"); // 获取企业列表
    Route::any('staff/list', "Crm\Staff\StaffController@getList"); // 获取员工列表
    Route::any('staff/label/edit', "Crm\Staff\StaffLabelController@editLabel"); // 员工修改自定义标签
    Route::any('staff/label/delete', "Crm\Staff\StaffLabelController@deleteLabel"); // 删除自定义标签
    Route::any('staff/label/custom/add', "Crm\Staff\StaffLabelController@addCustomLabel"); // 添加自定义标签
    Route::any('staff/label/info', "Crm\Staff\StaffLabelController@getInfo"); // 删除自定义标签
    Route::any('staff/visit/log/add', "Crm\Staff\StaffVisitLogController@add"); // 添加拜访记录
    Route::any('staff/visit/log/edit', "Crm\Staff\StaffVisitLogController@edit"); // 编辑拜访记录
    Route::any('staff/visit/log/info', "Crm\Staff\StaffVisitLogController@getInfo"); // 获取拜访记录
    Route::any('staff/visit/log/list', "Crm\Staff\StaffVisitLogController@getList"); // 获取拜访列表
    Route::any('staff/visit/log/delete', "Crm\Staff\StaffVisitLogController@delete"); // 删除拜访列表
    Route::any('staff/index/info', "Crm\Staff\StaffController@getHomePageData"); // 员工首页数据
    Route::any('staff/index/count', "Crm\Staff\StaffController@getHomePageMemberCount"); // 员工首页客户统计数据
});