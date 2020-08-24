<?php
// 给管总的网站临时做了个自动发货的功能 需求ID https://www.tapd.cn/58588678/prong/stories/view/1158588678001002826
// 网站ID：1289	二级域名：u179.meidianbang.net

// 后台路由
Route::prefix('shop/admin')->group(function () {
    Route::any('/custom/order/delivery/auto', 'Custom\Site1289\Autocron\OrderController@autoDelivery');
});