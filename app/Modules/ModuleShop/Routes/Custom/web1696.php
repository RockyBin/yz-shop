<?php
/**
 * 定制路由
 * User: liyaohui
 * Date: 2020/5/21
 * Time: 15:15
 */

// 后台路由
Route::prefix('shop/admin')->group(function () {
    Route::any('/custom/member/sub/order/money', 'Custom\Site1696\Admin\MemberController@getSubMemberOrderMoneyList');
});