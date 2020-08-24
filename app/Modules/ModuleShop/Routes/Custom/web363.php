<?php
/**
 * Created by Aison.
 */

// 后台路由
Route::prefix('shop/admin')->group(function () {
    Route::any('/custom/member/cert/info', 'Custom\Site363\Admin\MemberCertController@getInfo');
    Route::any('/custom/member/cert/save', "Custom\Site363\Admin\MemberCertController@save");
    Route::any('/custom/member/cert/delete', "Custom\Site363\Admin\MemberCertController@delete");
    Route::any('/custom/member/extend/info', 'Custom\Site363\Admin\MemberExtendController@getInfo');
    Route::any('/custom/member/extend/save', "Custom\Site363\Admin\MemberExtendController@save");
    Route::any('/url/static', 'Custom\Site363\Admin\UrlManageController@getStaticUrl'); // 静态链接
    Route::any('/custom/distribution/order/list', 'Custom\Site363\Admin\DistributionOrderController@getList');
});
// 前台路由
Route::prefix('shop/front')->group(function () {
    Route::any('/custom/member/cert/info', 'Custom\Site363\Front\MemberCertController@getInfo');
    Route::any('/custom/member/extend/info', 'Custom\Site363\Front\MemberExtendController@getInfo');
    Route::any('/custom/member/distributionorder/count', 'Custom\Site363\Front\DistributionOrderController@getCountInfo');
    Route::any('/custom/member/distributionorder/list', 'Custom\Site363\Front\DistributionOrderController@getList');
});