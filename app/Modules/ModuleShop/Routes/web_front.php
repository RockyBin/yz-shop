<?php
//前台相关路由
Route::prefix('shop/front')->group(function () {
    Route::any('product/class/list', 'Front\Product\ProductClassController@getClassList');
    Route::get('site/info', "Front\IndexController@getSiteInfo"); // 获取网站信息
    Route::get('site/service', "Front\IndexController@getSiteServiceInfo"); // 获取网站客服信息
    Route::any('product/list', 'Front\Product\ProductListController@getList');
    Route::any('product/detail', 'Front\Product\ProductDetailController@getDetail');
    Route::any('product/adress', 'Front\Product\ProductDetailController@getAdressList');
    Route::any('product/freight', 'Front\Product\ProductDetailController@getFreight');
    Route::any('product/sku', 'Front\Product\ProductDetailController@getSku');
    Route::any('coupon/getcoupon', 'Front\Coupon\CouponController@getProductCoupon');
    Route::any('coupon/list', 'Front\Coupon\CouponController@getCouponList');
    Route::any('coupon/receivedcoupon', 'Front\Member\Center\MemberCouponController@receivedCoupon');// 领取优惠券
    Route::any('browse/list', 'Front\Member\Center\MemberBrowseController@getBrowse'); // 浏览记录列表
    Route::any('browse/add', 'Front\Member\Center\MemberBrowseSaveController@addBrowse'); // 添加浏览记录
    Route::any('browse/clear', 'Front\Member\Center\MemberBrowseController@clear'); // 清空浏览记录
    Route::any('browse/delete', 'Front\Member\Center\MemberBrowseController@delete'); // 删除浏览记录
    Route::any('page/mobile/info', 'Front\Page\MobilePageController@getInfo'); // 获取手机端前台首页和自定义页面的信息，包括模块，底部菜单等
    Route::any('page/mobile/baseinfo', 'Front\Page\MobilePageController@getBaseInfo'); // 获取手机端前台首页的基本属性
    Route::any('member/setfromadmin', 'Front\Member\FromadminController@setFromadmin'); // 用于给前台通过ajax设置推荐员工的ID
    Route::any('member/getfromadmin', 'Front\Member\FromadminController@getFromadmin'); // 获取当前的fromadmin id
    Route::any('member/setinvite', 'Front\Member\InviteController@setInvite'); // 用于给前台通过ajax设置推荐者的ID
    Route::any('member/getinvite', 'Front\Member\InviteController@getInvite'); // 获取当前的invite id
    Route::any('visitlog', 'Front\VisitLogController@index'); // 记录访问统计相关记录
    Route::any('ui/mobile/nav', 'Front\UI\ModuleController@getMobileNavInfo'); // 获取手机端底部导航
    Route::any('site/wxjssdk', "Front\IndexController@getConfigWxJSSDK"); // 获取网站微信JSSDK
    Route::any('/sharepaper/mobi/paper/render', 'Front\SharePaper\PaperMobiController@render'); // 渲染海报信息
    Route::any('/sharepaper/mobi/paper/renderimage', 'Front\SharePaper\PaperMobiController@renderImage'); // 渲染海报生成图片
    Route::any('site/after/share', "Front\IndexController@afterShare"); // 微信分享后的事件
    Route::any('product/comment/list', 'Front\Product\ProductCommentController@getList'); // 商品评论列表
    Route::any('/authcert/render', 'Front\AuthCert\AuthCertController@render'); // 渲染授权证书信息
    Route::any('/authcert/renderimage', 'Front\AuthCert\AuthCertController@renderImage'); // 渲染授权证书生成图片
    Route::any('/authcert/query', 'Front\AuthCert\AuthCertController@query'); // 查询授权证书
    Route::any('/dealerinvite/poster/render', 'Front\DealerInvite\PosterController@render'); // 渲染邀请海报信息
    Route::any('/dealerinvite/poster/renderimage', 'Front\DealerInvite\PosterController@renderImage'); // 渲染邀请海报生成图片
    Route::any('/security/code/query/{code}', 'Front\ProductSecurity\SecurityCodeController@queryCode'); // 查询防伪码
    //小店(前台)
    Route::any('smallshop/info', "Front\SmallShop\SmallShopController@getInfo"); // 小店信息
    Route::any('smallshop/product/list', "Front\SmallShop\SmallShopController@getSmallShopProductList"); // 小店产品信息
    //广告屏绑定
    Route::any('member/searchinvite', 'Front\Member\InviteController@searchInvite'); // 用于广告屏查询绑定邀请码
    //直播相关
    Route::any('live/info', 'Front\Live\LiveController@getInfo'); // 获取直播信息
    Route::any('live/list', 'Front\Live\LiveController@getList'); // 获取直播信息列表
    Route::any('live/viewer/add', 'Front\Live\ViewerController@add'); // 增加观看人信息
    Route::any('live/viewer/reduce', 'Front\Live\ViewerController@reduce'); // 减少观看人信息
    Route::any('live/chat/add', 'Front\Live\ChatController@add'); // 记录聊天内容
    Route::any('live/chat/newest', 'Front\Live\ChatController@getNewestList'); // 取出最新的N条聊天记录
    Route::any('live/coupon/list', 'Front\Live\LiveController@getCoupon'); // 重新获取优惠券信息
    Route::any('live/like/add', 'Front\Live\LiveController@addLike'); // 添加点赞数
    //拼团
    Route::any('groupbuying/product/list', 'Front\GroupBuying\GroupBuyingProductController@getProductList'); // 拼团专场产品列表
    Route::any('groupbuying/product/detail', 'Front\GroupBuying\GroupBuyingProductController@getProductDetail'); // 拼团专场产品详情
    Route::any('groupbuying/group/virtual', 'Front\GroupBuying\GroupBuyingProductController@getVirtualGroupList'); // 拼团专场产品详情中的凑团列表
    Route::any('groupbuying/product/sku', 'Front\GroupBuying\GroupBuyingProductController@getSku'); // 拼团专场产品SKU
    Route::any('groupbuying/check', 'Front\GroupBuying\GroupBuyingController@checkQualification'); // 拼团专区老带新的检测
    Route::any('groupbuying/info', 'Front\GroupBuying\GroupBuyingController@getInfo'); // 拼团详情
    Route::any('groupbuying/activity/check', 'Front\GroupBuying\GroupBuyingProductController@checkActivityStatus'); // 检测活动状态
    Route::any('groupbuying/mock', 'Front\GroupBuying\GroupBuyingController@mockGroupBuyingSuccess'); // 凑团
    //小程序相关
    Route::any('wxapp/scene/tourl', 'Front\WxApp\SceneController@toUrl'); // 根据小程序端传过来的场景值返回完整URL
    // 快递回调
    Route::any('express/callback', 'Front\Express\ExpressCallbackController@callback');
});