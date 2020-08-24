<?php
/**
 * 智应官方商城定制的路由
 */

// 会员中心路由
Route::prefix('shop/member')->group(function () {
    Route::any('/custom/member/createshop', 'Custom\Zhiying\Front\CreateShopController@create');
    Route::any('/custom/member/updatestatus', 'Custom\Zhiying\Front\CreateShopController@updateOrderStatus');
    Route::any('/custom/member/updatedeliverystatus', 'Custom\Zhiying\Front\CreateShopController@updateOrderDeliveryStatus');
});