<?php
//后台相关路由
Route::prefix('shop/admin')->group(function () {
    Route::get('/index', 'Admin\IndexController@index'); // 首页
    Route::get('/site/info', "Admin\IndexController@getSiteInfo"); // 获取网站信息

    //后台会员模块路由
    Route::any('/member/list', "Admin\Member\MemberController@getList"); // 获取会员列表
    Route::any('/member/info', "Admin\Member\MemberController@getInfo"); // 获取某一个会员的信息
    Route::any('/member/delete', "Admin\Member\MemberController@delete"); // 删除指定会员的信息
    Route::any('/member/add', "Admin\Member\MemberController@add"); // 添加会员的信息
    Route::any('/member/edit', "Admin\Member\MemberController@edit"); // 编辑会员的信息
    Route::any('/member/base/edit', "Admin\Member\MemberController@editBaseInfo"); // 编辑会员的信息
    Route::any('/member/status', "Admin\Member\MemberController@status"); // 修改会员的状态
    Route::any('/member/export', "Admin\Member\MemberController@export"); // 导出会员数据
    Route::any('/member/info/config', "Admin\Member\MemberController@getMemberInfoBaseConfig"); // 获取会员详情需要的基础信息
    Route::any('/member/info/data', "Admin\Member\MemberController@getMemberInfo"); // 获取会员详情
    Route::any('/member/distributor/info', "Admin\Member\MemberController@getDistributorInfo"); // 获取会员分销详情
    Route::any('/member/distributor/list', "Admin\Member\MemberController@getDistributorSubList"); // 获取会员下级列表
    Route::any('/member/agent/info', "Admin\Member\MemberController@getAgentInfo"); // 获取代理信息
    Route::any('/member/agent/list', "Admin\Member\MemberController@getAgentSubList"); // 获取代理下级列表
    Route::any('/member/dealer/info', "Admin\Member\MemberController@getDealerInfo"); // 获取经销商信息
    Route::any('/member/dealer/list', "Admin\Member\MemberController@getDealerSubList"); // 获取下级经销商列表
    Route::any('/member/fans/list', "Admin\Member\FansController@getList"); // 获取粉丝列表
    Route::any('/member/label/edit', "Admin\Member\MemberLabelController@edit"); // 标签编辑
    Route::any('/member/label/list', "Admin\Member\MemberLabelController@getList"); // 标签列表
    Route::any('/member/label/delete', "Admin\Member\MemberLabelController@delete"); // 标签删除
    Route::any('/member/label/check', "Admin\Member\MemberLabelController@check"); // 标签删除前的检测
    Route::any('/member/label/sort', "Admin\Member\MemberLabelController@sort"); // 标签删除前的检测
    Route::any('/member/label/relation', "Admin\Member\MemberController@editMemberLabel"); // 会员修改标签
    Route::any('/member/level/type/list', "Admin\Member\MemberController@getLevelList"); // 会员列表标签选择

    //后台订单设置路由
    Route::get('/orderconfig/info', "Admin\SiteConfig\OrderConfigController@getInfo"); // 获取订单设置
    Route::get('/orderconfig/add', "Admin\SiteConfig\OrderConfigController@add"); // 添加订单设置
    Route::get('/orderconfig/edit', "Admin\SiteConfig\OrderConfigController@edit"); // 编辑订单设置
    //后台短信设置路由
    Route::get('/smsconfig/info', "Admin\SiteConfig\SmsConfigController@getInfo"); // 获取短信设置
    Route::get('/smsconfig/add', "Admin\SiteConfig\SmsConfigController@add"); // 添加短信设置
    Route::get('/smsconfig/edit', "Admin\SiteConfig\SmsConfigController@edit"); // 编辑短信设置
    //后台商户设置路由
    Route::get('/storeconfig/info', "Admin\SiteConfig\StoreConfigController@getInfo"); // 获取商户设置
    Route::get('/storeconfig/add', "Admin\SiteConfig\StoreConfigController@add"); // 添加商户设置
    Route::any('/storeconfig/edit', "Admin\SiteConfig\StoreConfigController@edit"); // 编辑商户设置
    Route::any('/storeconfig/uplode/qrcode', "Admin\SiteConfig\StoreConfigController@uploadQrcodeImg"); // 上传二维码
    //后台提现设置路由
    Route::get('/withdrawconfig/info', "Admin\SiteConfig\WithdrawConfigController@getInfo"); // 获取提现设置
    Route::get('/withdrawconfig/add', "Admin\SiteConfig\WithdrawConfigController@add"); // 添加提现设置
    Route::get('/withdrawconfig/edit', "Admin\SiteConfig\WithdrawConfigController@edit"); // 编辑提现设置
    Route::any('/withdrawconfig/update', 'Admin\SiteConfig\WithdrawConfigController@updateWithdrawConfig'); // 更新提现设置
    Route::get('/withdrawconfig/checkPayConfig', "Admin\SiteConfig\WithdrawConfigController@checkPayConfig"); // 检测支付配置，无配置时不允许编辑提现配置
    //后台支付设置路由
    Route::any('/payconfig/info', "Admin\SiteConfig\PayConfigController@getInfo"); // 获取支付设置
    Route::any('/payconfig/add', "Admin\SiteConfig\PayConfigController@add"); // 添加支付设置
    Route::any('/payconfig/edit', "Admin\SiteConfig\PayConfigController@edit"); // 编辑支付设置
    //后台商城设置路由
    Route::get('/shopconfig/info', "Admin\SiteConfig\ShopConfigController@getInfo"); // 获取商城设置
    Route::get('/shopconfig/add', "Admin\SiteConfig\ShopConfigController@add"); // 添加商城设置
    Route::any('/shopconfig/edit', "Admin\SiteConfig\ShopConfigController@edit"); // 编辑商城设置

    Route::get('/agent/upgrade/info', "Admin\Agent\AgentUpgradeSettingController@getInfo"); // 获取代理升级设置
    Route::any('/agent/upgrade/edit', "Admin\Agent\AgentUpgradeSettingController@edit"); // 编辑代理升级设置
    Route::get('/agent/upgrade/upgradelevel', "Admin\Agent\AgentUpgradeSettingController@upgradelevel"); // 获取代理升级设置
    Route::get('/agent/upgrade/getOrderAgentData', "Admin\Agent\AgentUpgradeSettingController@getOrderAgentData"); // 获取代理升级设置
    // 后台会员等级路由
    Route::any('/member/level/list', "Admin\Member\MemberLevelController@getList"); // 获取会员等级列表
    Route::any('/member/level/info', "Admin\Member\MemberLevelController@getInfo"); // 获取某一个会员等级的信息
    Route::any('/member/level/add', "Admin\Member\MemberLevelController@add"); // 添加会员等级的信息
    Route::any('/member/level/edit', "Admin\Member\MemberLevelController@edit"); // 编辑会员等级的信息
    Route::any('/member/level/delete', "Admin\Member\MemberLevelController@delete"); // 删除会员等级
    Route::any('/member/level/status', "Admin\Member\MemberLevelController@status"); // 变更某一个会员等级的状态
    Route::any('/member/level/transfer', "Admin\Member\MemberLevelController@transfer"); // 编辑会员等级的信息

    //运费模板路由
    Route::get('/freighttemplate/list', "Admin\SiteConfig\FreightTemplateController@getList");//获取运费模板列表
    Route::get('/freighttemplate/info', "Admin\SiteConfig\FreightTemplateController@getInfo");//获取运费模板某一条记录
    Route::get('/freighttemplate/add', "Admin\SiteConfig\FreightTemplateController@add");        //增加运费模板
    Route::get('/freighttemplate/edit', "Admin\SiteConfig\FreightTemplateController@edit");      //编辑运费模板
    Route::get('/freighttemplate/delete', "Admin\SiteConfig\FreightTemplateController@delete");  //删除运费模板
    Route::get('/freighttemplate/getdistrictjs', "Admin\SiteConfig\FreightTemplateController@getDistrictJs");  //生成地区JS
    Route::get('/freighttemplate/get/freight', "Admin\SiteConfig\FreightTemplateController@getFreightTemplateList");  //获取有效的运费模板

    // 产品相关
    // 产品列表
    Route::any('/product/list', 'Admin\Product\ProductController@getList'); //获取产品列表
    Route::any('/product/image/upload', 'Admin\Product\ProductController@uploadProductImage'); // 上传产品图片
    Route::any('/product/video/poster/upload', 'Admin\Product\ProductController@uploadProductVideoPoster'); // 上传产品视频封面图
    Route::any('/product/sku/image/upload', 'Admin\Product\ProductController@uploadProductSkuImage'); // 上传sku图片
    Route::any('/product/save', 'Admin\Product\ProductController@saveProductData'); // 保存产品数据
    Route::any('/product/data/{product}', 'Admin\Product\ProductController@getProductData'); // 获取产品详情数据
    Route::any('/product/add', 'Admin\Product\ProductController@addProduct'); // 添加产品页面
    Route::any('/product/qrcode/{id}', 'Admin\Product\ProductController@getProductQrCode');// 产品二维码和链接
    Route::any('/product/list/export', 'Admin\Product\ProductController@exportProductList');// 产品列表导出
    Route::any('/product/status/edit', 'Admin\Product\ProductController@editProductStatus');// 产品状态编辑
    Route::any('/product/fenxiao/data', 'Admin\Product\ProductController@getFenxiaoProductData'); // 分销产品详情数据
    Route::any('/product/fenxiao/save', 'Admin\Product\ProductController@saveFenxiaoProductData'); // 保存分销产品
    Route::any('/product/commission/max', 'Admin\Product\ProductController@getMaxCommission'); // 保存分销产品
    Route::any('/product/sku/info', 'Admin\Product\ProductController@getProductSkuInfo'); // 保存分销产品
    Route::any('/product/sort/edit', 'Admin\Product\ProductController@editSort');// 修改商品排序值

    // 获取分销选择的商城产品详情
    Route::any('/product/fenxiao/shop/data', 'Admin\Product\ProductController@getFenxiaoShopProductData');
    Route::any('/product/count', 'Admin\Product\ProductController@getProductCount'); //获取产品列表
    Route::any('/product/check/serial/number', 'Admin\Product\ProductController@checkSerialNumber'); //检测商品编码
    Route::any('/product/synchroPriceRule', 'Admin\Product\ProductController@synchroPriceRule'); //获取产品列表
    Route::any('/product/save/inventory', 'Admin\Product\ProductController@saveSkuInventory'); //列表保存库存
    Route::any('/product/import', 'Admin\Product\ProductController@import'); //列表导入库存
    Route::any('/product/import/error', 'Admin\Product\ProductController@importError'); //列表导入库存错误
    Route::any('/product/import/mb', 'Admin\Product\ProductController@importMB'); //列表导入库存模板

    //产品批量导入 单图片上传
    Route::get('/product/import/examples', 'Admin\Product\ImportFeaturesController@getUrlXlsx');
    Route::post('/product/import/uploadImg', 'Admin\Product\ImportFeaturesController@imgUpload');//图片上传
    Route::post('/product/import/xlsx', 'Admin\Product\ImportFeaturesController@importProduct');

    // 分类
    Route::any('/product/class/save', 'Admin\Product\ProductClassController@saveClass');// 保存分类
    Route::any('/product/class/delete/{class}', 'Admin\Product\ProductClassController@deleteClass');// 删除分类
    Route::any('/product/class/count/{class}', 'Admin\Product\ProductClassController@getProductsCount');// 获取分类下的产品数量
    Route::any('/product/class/list', 'Admin\Product\ProductClassController@getClassList');// 获取分类列表
    Route::any('/product/class/name/{class}', 'Admin\Product\ProductClassController@editProductClassName');// 修改分类名称
    Route::any('/product/class/image/upload', 'Admin\Product\ProductClassController@uploadClassImage');// 上传分类图片
    Route::any('/product/class/status/{class}', 'Admin\Product\ProductClassController@editProductClassStatus');// 编辑分类状态

    // 后台会员配置路由
    Route::any('/member/config/info', "Admin\Member\MemberConfigController@getInfo"); // 获取会员配置
    Route::any('/member/config/save', "Admin\Member\MemberConfigController@save"); // 保存会员配置

    // 后台积分管理
    Route::any('/point/list', "Admin\Point\PointController@getList"); // 获取积分列表
    Route::any('/point/member/list', "Admin\Point\PointController@getMemberList"); // 获取积分会员列表
    Route::any('/point/add', "Admin\Point\PointController@add"); // 添加积分
    Route::any('/point/export', "Admin\Point\PointController@export"); // 导出积分数据

    // 后台积分配置
    Route::any('/point/config/info', "Admin\Point\PointConfigController@getInfo"); // 获取积分配置
    Route::any('/point/config/save', "Admin\Point\PointConfigController@save"); // 保存积分配置

    //后台分销设置路由
    Route::get('/distribution/setting/info', "Admin\Distribution\DistributionSettingController@getInfo"); // 获取分销设置
    Route::post('/distribution/setting/edit', "Admin\Distribution\DistributionSettingController@edit"); // 修改分销设置
    Route::get('/distribution/setting/base/info', "Admin\Distribution\DistributionSettingController@getBaseInfo"); // 获取分销基础设置
    Route::post('/distribution/setting/base/edit', "Admin\Distribution\DistributionSettingController@editBase"); // 修改分销基础设置

    //后台分销等级路由
    Route::get('/distribution/level/list', "Admin\Distribution\DistributionLevelController@getList"); // 获取分销等级列表
    Route::get('/distribution/level/info', "Admin\Distribution\DistributionLevelController@getInfo"); // 获取分销等级信息
    Route::post('/distribution/level/add', "Admin\Distribution\DistributionLevelController@add"); // 添加分销等级
    Route::post('/distribution/level/edit', "Admin\Distribution\DistributionLevelController@edit"); // 修改分销等级
    Route::any('/distribution/level/disable', "Admin\Distribution\DistributionLevelController@disable"); // 禁用分销等级
    Route::any('/distribution/level/delete', "Admin\Distribution\DistributionLevelController@delete"); // 删除分销等级
    Route::any('/distribution/level/enable', "Admin\Distribution\DistributionLevelController@enable"); // 启用分销等级
    Route::any('/distribution/level/trans', "Admin\Distribution\DistributionLevelController@trans"); // 等级转移

    //后台分销商管理路由
    Route::any('/distribution/distributor/list', "Admin\Distribution\DistributorController@getList"); // 获取分销商列表
    Route::any('/distribution/distributor/info', "Admin\Distribution\DistributorController@getInfo"); // 获取分销商详情信息
    Route::any('/distribution/distributor/reviewInfo', "Admin\Distribution\DistributorController@reviewDistributorInfo"); // 获取分销商详情信息
    Route::any('/distribution/distributor/review', "Admin\Distribution\DistributorController@review"); // 审核分销商
    Route::any('/distribution/distributor/delete', "Admin\Distribution\DistributorController@delete"); // 删除分销商
    Route::any('/distribution/distributor/deleteInReview', "Admin\Distribution\DistributorController@deleteInReview"); // 在审核列表删除
    Route::any('/distribution/distributor/add', "Admin\Distribution\DistributorController@add"); // 添加分销商
    Route::any('/distribution/distributor/edit', "Admin\Distribution\DistributorController@edit"); // 修改分销商
    Route::any('/distribution/distributor/reactive', "Admin\Distribution\DistributorController@reActive"); // 恢复分销商资格
    Route::any('/distribution/distributor/deactive', "Admin\Distribution\DistributorController@deActive"); // 取消分销商资格

    //后台优惠券
    Route::any('coupon/add', 'Admin\Coupon\CouponController@add');//添加优惠券
    Route::any('coupon/edit', 'Admin\Coupon\CouponController@edit');//编辑优惠
    Route::any('coupon/delete', 'Admin\Coupon\CouponController@delete');//删除优惠券
    Route::any('coupon/status', 'Admin\Coupon\CouponController@editStatus');//只调整优惠券状态
    Route::any('coupon/info', 'Admin\Coupon\CouponController@getInfo');//优惠券详情
    Route::any('coupon/productclass', 'Admin\Coupon\CouponController@getProductClass');//获取优惠券详情中的产品分类
    Route::any('coupon/list', 'Admin\Coupon\CouponController@getList');//优惠券列表
    Route::any('coupon/couponitem', 'Admin\Coupon\CouponController@getCouponItem');
    Route::any('coupon/memberandlevel', 'Admin\Coupon\CouponController@getMemberAndLevel');
    Route::any('coupon/sendCoupon', 'Admin\Coupon\CouponController@sendCoupon');//发放优惠券
    Route::any('coupon/checkcouponamount', 'Admin\Coupon\CouponController@checkCouponAmount');//检测优惠券状态
    Route::any('coupon/export', 'Admin\Coupon\CouponController@export');//导出优惠券
    //后台优惠券记录
    Route::any('coupon/item/confirm', 'Admin\Coupon\CouponItemController@confirm');
    Route::any('coupon/item/export', 'Admin\Coupon\CouponItemController@export');
    Route::any('coupon/item/list', 'Admin\Coupon\CouponItemController@getList');
    Route::any('coupon/item/confirm', 'Admin\Coupon\CouponItemController@confirm');
    Route::any('coupon/item/export', 'Admin\Coupon\CouponItemController@export');

    // 后台订单路由
    Route::any('/order/list', 'Admin\Order\OrderController@getList'); // 订单列表
    Route::any('/order/info', 'Admin\Order\OrderController@getInfo'); // 订单详情
    Route::any('/order/deliver', 'Admin\Order\OrderController@deliver'); // 订单发货
    Route::any('/order/export', 'Admin\Order\OrderController@export'); // 订单导出
    Route::any('/order/edit', 'Admin\Order\OrderController@edit'); // 订单修改信息
    Route::any('/order/receipt', 'Admin\Order\OrderController@confirmReceipt'); // 代客人收货
    Route::any('/order/money/edit', 'Admin\Order\OrderController@editProductMoney'); // 修改订单金额
    Route::any('/order/freight/edit', 'Admin\Order\OrderController@editFreightMoney'); // 修改运费金额
    Route::any('/order/address/edit', 'Admin\Order\OrderController@editAddress'); // 修改收货地址
    Route::any('/aftersale/list', 'Admin\Order\AfterSaleController@getList'); // 售后订单列表
    Route::any('/aftersale/info', 'Admin\Order\AfterSaleController@getInfo'); // 售后订单详情
    Route::any('/aftersale/edit', 'Admin\Order\AfterSaleController@editStatus'); // 售后订单改变状态
    Route::any('/aftersale/export', 'Admin\Order\AfterSaleController@export'); // 售后订单改变状态
    Route::any('/logistics/info', "Admin\Order\LogisticsController@getInfo"); // 物流信息详情
    Route::any('/logistics/edit', "Admin\Order\LogisticsController@edit"); // 物流信息修改

    // 后台页面模块编辑
    Route::any('/ui/design/mobile/page/modules', 'Admin\UI\Design\MobilePageController@save'); //旧的路由，过几天要删除的

    Route::any('/ui/design/mobile/page/save', 'Admin\UI\Design\MobilePageController@save'); //保存编辑页面
    Route::any('/ui/design/mobile/page/publish', 'Admin\UI\Design\MobilePageController@publish'); //发布页面
    Route::any('/url/static', 'Admin\Url\UrlManageController@getStaticUrl'); // 静态链接
    Route::any('/url/product/class', 'Admin\Url\UrlManageController@getProductClassUrl'); // 产品分类链接
    Route::any('/url/product/list', 'Admin\Url\UrlManageController@getProductDetailUrl'); // 产品列表链接
    Route::any('/url/page', 'Admin\Url\UrlManageController@getPageUrl'); // 静态链接
    Route::any('/url/groupbuying/list', 'Admin\Url\UrlManageController@getGroupBuyingUrl'); // 拼团活动连接列表

    Route::any('/ui/design/mobile/page/list', 'Admin\UI\Design\MobilePageController@getPageList'); // 页面列表
    Route::any('/ui/design/mobile/page/set/home', 'Admin\UI\Design\MobilePageController@setHomePage'); // 设置为主页
    Route::any('/ui/design/mobile/page/delete', 'Admin\UI\Design\MobilePageController@deletePage'); // 删除页面
    Route::any('/ui/design/mobile/page/home', 'Admin\UI\Design\MobilePageController@getHomePage'); // 获取主页数据
    Route::any('/ui/design/mobile/page/qrcode', 'Admin\UI\Design\MobilePageController@getHomePageQrCode'); // 获取页面二维码
    Route::any('/ui/design/mobile/page/add', 'Admin\UI\Design\MobilePageController@addPage'); // 新建页面
    Route::any('/ui/design/mobile/page/add/blank', 'Admin\UI\Design\MobilePageController@addBlankPage'); // 新建页面
    // 广告屏页面编辑
    Route::any('/ui/design/mobile/bigscreen/page/save', 'Admin\UI\Design\MobilePageController@bigScreenSave'); //保存编辑页面
    Route::any('/ui/design/mobile/bigscreen/page/home', 'Admin\UI\Design\MobilePageController@bigScreenGetHomePage'); // 获取主页数据
    Route::any('/ui/design/mobile/bigscreen/page/add/blank', 'Admin\UI\Design\MobilePageController@bigScreenAddBlankPage'); // 新建页面
    // 获取会员中心页面需要的配置
    Route::any('/ui/design/mobile/member/center/config', 'Admin\UI\Design\MobilePageController@getMemberCenterPageConfig');

    // 模板配色
    Route::any('/ui/template/list', 'Admin\UI\Template\TemplateMobiController@getList'); // 获取模板列表
    Route::any('/ui/style/color', 'Admin\UI\StyleColor\StyleColorController@getSiteColor'); // 获取当前配色
    Route::any('/ui/style/color/edit', 'Admin\UI\StyleColor\StyleColorController@editSiteColor');// 修改配色
//    Route::any('/ui/color/add', 'Admin\UI\StyleColor\StyleColorController@addStyleColor');// 添加配色数据

    // 后台店铺底部菜单编辑
    Route::any('/ui/design/mobile/nav/get', 'Admin\UI\Design\NavMobileController@getInfo');
    Route::any('/ui/design/mobile/nav/save', 'Admin\UI\Design\NavMobileController@save');
    // 广告屏底部菜单编辑
    Route::any('/ui/design/mobile/bigscreen/nav/get', 'Admin\UI\Design\NavMobileController@bigScreenGetInfo');
    Route::any('/ui/design/mobile/bigscreen/nav/save', 'Admin\UI\Design\NavMobileController@bigScreenSave');
    // 广告弹窗编辑
    Route::any('/ui/design/popup/get', 'Admin\UI\Design\PopupController@getInfo'); //获取广告弹窗信息
    Route::any('/ui/design/popup/save', 'Admin\UI\Design\PopupController@save'); //保存广告弹窗信息
    // 广告屏app
    Route::any('/mobileapp/get', 'Admin\MobileApp\MobileAppController@getInfo'); //获取app信息
    Route::any('/mobileapp/save', 'Admin\MobileApp\MobileAppController@save'); //保存app信息

    // 后台财务路由
    Route::any('/balance/list', 'Admin\Finance\BalanceController@getList'); // 结算管理列表
    Route::any('/balance/export', 'Admin\Finance\BalanceController@export'); // 结算管理列表
    Route::any('/withdraw/list', 'Admin\Finance\WithdrawController@getList'); // 提现管理列表
    Route::any('/withdraw/info', 'Admin\Finance\WithdrawController@getInfo'); // 提现管理详情
    Route::any('/withdraw/export', 'Admin\Finance\WithdrawController@export'); // 提现管理详情
    Route::any('/withdraw/withdraw', 'Admin\Finance\WithdrawController@withDraw'); // 提现
    Route::any('/withdraw/reject', 'Admin\Finance\WithdrawController@reject'); // 拒绝提现
    Route::any('/finance/list', 'Admin\Finance\FinanceController@getList'); // 财务列表
    Route::any('/finance/export', 'Admin\Finance\FinanceController@export'); // 导出财务数据
    Route::any('/finance/rechange', 'Admin\Finance\FinanceController@rechange'); // 财务列表

    // 权限相关
    Route::any('/role/list', 'Admin\Site\SiteRoleController@getList'); // 角色列表
    Route::any('/role/info', 'Admin\Site\SiteRoleController@getInfo'); // 角色信息
    Route::any('/role/save', 'Admin\Site\SiteRoleController@save'); // 角色保存
    Route::any('/role/delete', 'Admin\Site\SiteRoleController@delete'); // 角色删除
    Route::any('/role/check/name', 'Admin\Site\SiteRoleController@checkRoleName'); // 角色名称是否重复
    Route::any('/staff/list', 'Admin\Site\SiteAdminController@getList'); // 员工列表
    Route::any('/staff/export', 'Admin\Site\SiteAdminController@exportStaffList'); // 导出员工
    Route::any('/staff/info', 'Admin\Site\SiteAdminController@getInfo'); // 员工详情
    Route::any('/staff/save', 'Admin\Site\SiteAdminController@save'); // 员工保存
    Route::any('/staff/status', 'Admin\Site\SiteAdminController@status'); // 员工变更状态
    Route::any('/staff/password', 'Admin\Site\SiteAdminController@password'); // 修改密码
    Route::any('/staff/delete', 'Admin\Site\SiteAdminController@deleteAdmin'); // 删除员工
    Route::any('/staff/member/count', 'Admin\Site\SiteAdminController@getMemberCount'); // 获取员工名下会员数量
    Route::any('/staff/upload/headimg', 'Admin\Site\SiteAdminController@uploadHeadImage'); // 上传员工头像
    Route::any('/staff/check/username', 'Admin\Site\SiteAdminController@checkUserName'); // 检测用户名是否重复
    Route::any('/staff/check/mobile', 'Admin\Site\SiteAdminController@checkMobile'); // 检测手机号是否重复
    // 员工流量分配规则
    Route::any('/staff/allocation/info', 'Admin\Site\SiteAdminAllocationController@getInfo'); // 获取流量分配设置信息
    Route::any('/staff/allocation/save', 'Admin\Site\SiteAdminAllocationController@save'); // 保存流量分配设置
    // 部门
    Route::any('/department/list', 'Admin\Site\SiteAdminDepartmentController@getList'); // 部门列表
    Route::any('/department/save', 'Admin\Site\SiteAdminDepartmentController@save'); // 保存部门
    Route::any('/department/delete', 'Admin\Site\SiteAdminDepartmentController@delete'); // 删除部门
    Route::any('/department/subinfo', 'Admin\Site\SiteAdminDepartmentController@getSubInfo'); // 获取部门下级及会员情况
    Route::any('/department/sort/save', 'Admin\Site\SiteAdminDepartmentController@saveSort'); // 保存部门排序

    // 登录
    Route::any('/login', 'Admin\LoginController@login'); // 登录
    Route::any('/autologin', 'Admin\LoginController@autologin'); // 72ad后台一键登录
    Route::any('/logout', 'Admin\LoginController@logout'); // 退出登录

    // 公众号配置
    Route::any('/wx/config/info', 'Admin\Wx\WxConfigController@getInfo'); // 公众号信息
    Route::any('/wx/config/save', 'Admin\Wx\WxConfigController@save'); // 修改公众号信息
    Route::any('/wx/config/unbind', 'Admin\Wx\WxConfigController@unBind'); // 解绑公众号
    // 公众号菜单
    Route::any('/wx/menu/list', 'Admin\Wx\WxMenuController@getList'); // 公众号菜单列表
    Route::any('/wx/menu/save', 'Admin\Wx\WxMenuController@save'); // 添加修改公众号菜单
    Route::any('/wx/menu/delete', 'Admin\Wx\WxMenuController@delete'); // 删除公众号菜单
    // 公众号自动回复
    Route::any('/wx/reply/list', 'Admin\Wx\WxAutoReplyController@getList'); // 自动回复列表
    Route::any('/wx/reply/info', 'Admin\Wx\WxAutoReplyController@getInfo'); // 自动回复详情
    Route::any('/wx/reply/save', 'Admin\Wx\WxAutoReplyController@save'); // 自动回复保存
    Route::any('/wx/reply/delete', 'Admin\Wx\WxAutoReplyController@delete'); // 自动回复删除
    Route::any('/wx/reply/keyword', 'Admin\Wx\WxAutoReplyController@checkKeyword'); // 关键词回复检测关键词
    // 公众号素材
    Route::any('/wx/news/list', 'Admin\Wx\WxNewsController@getList'); // 素材列表
    Route::any('/wx/news/item/list', 'Admin\Wx\WxNewsController@getItemList'); // 子图文列表
    Route::any('/wx/news/info', 'Admin\Wx\WxNewsController@getInfo'); // 素材详情
    Route::any('/wx/news/save', 'Admin\Wx\WxNewsController@save'); // 添加修改公众号素材
    Route::any('/wx/news/delete', 'Admin\Wx\WxNewsController@delete'); // 删除公众号素材
    //公众号引导关注
    Route::any('/wx/subscribe/setting/info', "Admin\Wx\WxSubscribeSettingController@getInfo"); // 获取公众号引导关注信息
    Route::any('/wx/subscribe/setting/edit', "Admin\Wx\WxSubscribeSettingController@edit"); // 修改公众号引导关注信息

    // 企业微信配置
    Route::any('/wxwork/config/info', 'Admin\WxWork\ConfigController@getInfo'); // 获取配置
    Route::any('/wxwork/config/save', 'Admin\WxWork\ConfigController@save'); // 修改配置

    // 微信小程序配置
    Route::any('/wxapp/config/info', 'Admin\WxApp\ConfigController@getInfo'); // 获取配置
    Route::any('/wxapp/config/save', 'Admin\WxApp\ConfigController@save'); // 修改配置
    Route::any('/wxapp/config/delete', 'Admin\WxApp\ConfigController@delete'); // 删除配置
    Route::any('/wxapp/config/package', 'Admin\WxApp\ConfigController@getPackage'); // 生成小程序包
    Route::any('/wxapp/config/upload/info/get', 'Admin\WxApp\ConfigController@getUploadInfo'); // 获取在线上传所需的信息

    // 消息推送
    Route::any('/message/config/info', 'Admin\Message\MessageConfigController@getInfo'); // 消息设置详情
    Route::any('/message/config/save', 'Admin\Message\MessageConfigController@save'); // 保存消息设置
    Route::any('/message/config/list', 'Admin\Message\MessageConfigController@getList'); // 消息列表

    // 海报相关
    Route::any('/sharepaper/mobi/paper/info', 'Admin\SharePaper\PaperMobiController@getInfo'); // 获取海报信息
    Route::any('/sharepaper/mobi/paper/list', 'Admin\SharePaper\PaperMobiController@getList'); // 获取海报列表
    Route::any('/sharepaper/mobi/paper/delete', 'Admin\SharePaper\PaperMobiController@delete'); // 删除某条海报
    Route::any('/sharepaper/mobi/paper/save', 'Admin\SharePaper\PaperMobiController@save'); // 保存海报信息
    Route::any('/sharepaper/mobi/paper/template', 'Admin\SharePaper\PaperMobiController@templateDate'); // 获取海报模板信息
    Route::any('/sharepaper/mobi/paper/save/paper/show', 'Admin\SharePaper\PaperMobiController@savePaperShow'); // 保存海报需要展示的内容
    Route::any('/sharepaper/mobi/paper/config', 'Admin\SharePaper\PaperMobiController@getConfig'); // 保存海报需要展示的内容
    // 文字替换
    Route::any('/word/info', 'Admin\SiteConfig\CustomWordController@getInfo'); // 获取文字信息
    Route::any('/word/save', 'Admin\SiteConfig\CustomWordController@save'); // 获取文字信息

    // 商品参数
    Route::any('/product/param/list', 'Admin\Product\ProductParamTemplateController@getList'); // 获取商品参数列表
    Route::any('/product/param/info', 'Admin\Product\ProductParamTemplateController@getInfo'); // 获取商品参数
    Route::any('/product/param/save', 'Admin\Product\ProductParamTemplateController@save'); // 保存商品参数
    Route::any('/product/param/delete', 'Admin\Product\ProductParamTemplateController@delete'); // 删除商品参数

    // 通用配置
    Route::any('/config/info', 'Admin\SiteConfig\ConfigController@getInfo'); // 获取信息
    Route::any('/config/save', 'Admin\SiteConfig\ConfigController@save'); // 保存信息

    // 产品评价
    Route::any('/product/comment/list', 'Admin\Product\ProductCommentController@getList'); // 产品评价列表
    Route::any('/product/comment/add', 'Admin\Product\ProductCommentController@add'); // 添加产品评价
    Route::any('/product/comment/reply', 'Admin\Product\ProductCommentController@adminReply'); // 商家回复评价
    Route::any('/product/comment/delete', 'Admin\Product\ProductCommentController@delete'); // 删除评价
    Route::any('/product/comment/status', 'Admin\Product\ProductCommentController@status'); // 审核评价

    // 公共方法
    Route::any('/common/product/list', 'Admin\Common\CommonController@getProductList'); // 产品列表
    Route::any('/common/member/list', 'Admin\Common\CommonController@getMemberList'); // 会员列表

    // 代理相关
    Route::any('/agent/basesetting/info', 'Admin\Agent\AgentBaseSettingController@getInfo'); //获取基本设置
    Route::any('/agent/basesetting/edit', 'Admin\Agent\AgentBaseSettingController@edit'); //修改基本设置
    Route::any('/agent/ordercommision/list', 'Admin\Agent\AgentOrderCommisionController@getList'); //订单分红列表
    Route::any('/agent/ordercommision/export', 'Admin\Agent\AgentOrderCommisionController@export'); //订单分红导出
    Route::any('/agent/salerewardsetting/info', 'Admin\Agent\AgentSaleRewardSettingController@getInfo'); //获取销售奖设置
    Route::any('/agent/salerewardsetting/edit', 'Admin\Agent\AgentSaleRewardSettingController@edit'); //修改销售奖设置
    Route::any('/agent/salereward/list', 'Admin\Agent\AgentSaleRewardController@getList'); //订单销售奖列表
    Route::any('/agent/salereward/export', 'Admin\Agent\AgentSaleRewardController@export'); //订单销售奖导出
    Route::any('/agent/recommend/setting/info', 'Admin\Agent\AgentRecommendRewardSettingController@getInfo'); // 推荐奖配置读取
    Route::any('/agent/recommend/setting/save', 'Admin\Agent\AgentRecommendRewardSettingController@save'); // 推荐奖配置修改
    Route::any('/agent/recommend/reward/list', 'Admin\Agent\AgentRecommendRewardController@getList'); // 推荐奖列表
    Route::any('/agent/recommend/reward/info', 'Admin\Agent\AgentRecommendRewardController@getInfo'); // 推荐奖详情
    Route::any('/agent/recommend/reward/check', 'Admin\Agent\AgentRecommendRewardController@check'); // 推荐奖审核
    Route::any('/agent/recommend/reward/export', 'Admin\Agent\AgentRecommendRewardController@export'); // 推荐奖导出
    Route::any('/agent/performance/setting/info', 'Admin\Agent\AgentPerformanceRewardSettingController@getInfo'); // 业绩奖配置读取
    Route::any('/agent/performance/setting/save', 'Admin\Agent\AgentPerformanceRewardSettingController@save'); // 业绩奖配置修改
    Route::any('/agent/performance/rule/list', 'Admin\Agent\AgentPerformanceRewardRuleController@getList'); // 业绩奖规则列表
    Route::any('/agent/performance/rule/info', 'Admin\Agent\AgentPerformanceRewardRuleController@getInfo'); // 业绩奖规则详情
    Route::any('/agent/performance/rule/save', 'Admin\Agent\AgentPerformanceRewardRuleController@save'); // 业绩奖规则保存
    Route::any('/agent/performance/rule/delete', 'Admin\Agent\AgentPerformanceRewardRuleController@delete'); // 业绩奖规则删除
    Route::any('/agent/performance/reward/list', 'Admin\Agent\AgentPerformanceRewardController@getList'); // 业绩奖列表
    Route::any('/agent/performance/reward/info', 'Admin\Agent\AgentPerformanceRewardController@getInfo'); // 业绩奖详情
    Route::any('/agent/performance/reward/check', 'Admin\Agent\AgentPerformanceRewardController@check'); // 业绩奖审核
    Route::any('/agent/performance/reward/export', 'Admin\Agent\AgentPerformanceRewardController@export'); // 业绩奖导出
    Route::any('/agent/performance/list', 'Admin\Agent\AgentController@getPerformanceList'); // 业绩列表
    Route::any('/agent/performance/export', 'Admin\Agent\AgentController@exportPerformanceList'); // 业绩导出
    Route::any('/agent/other/reward/setting/info', 'Admin\Agent\AgentOtherRewardSettingController@getInfo'); // 其他奖配置读取
    Route::any('/agent/other/reward/setting/save', 'Admin\Agent\AgentOtherRewardSettingController@edit'); // 其他奖配置修改
    Route::any('/agent/other/reward/list', 'Admin\Agent\AgentOtherRewardController@getList'); // 其他奖列表
    Route::any('/agent/other/reward/export', 'Admin\Agent\AgentOtherRewardController@export'); // 其他奖导出
    // 代理申请设置
    Route::any('/agent/apply/setting/info', 'Admin\Agent\AgentApplySettingController@getInfo'); // 代理加盟设置信息
    Route::any('/agent/apply/setting/save', 'Admin\Agent\AgentApplySettingController@edit'); // 代理加盟设置保存
    // 代理列表页面
    Route::any('/agent/list', 'Admin\Agent\AgentController@getAgentList'); // 代理列表
    Route::any('/agent/add', 'Admin\Agent\AgentController@adminAddAgent'); // 添加代理
    Route::any('/agent/verify', 'Admin\Agent\AgentController@verifyAgent'); // 审核代理
    Route::any('/agent/cancel', 'Admin\Agent\AgentController@cancelAgent'); // 取消代理
    Route::any('/agent/resume', 'Admin\Agent\AgentController@resumeAgent'); // 恢复代理
    Route::any('/apply/agent/list', 'Admin\Agent\AgentController@getApplyAgentList'); // 申请代理列表
    Route::any('/apply/agent/reject/delete', 'Admin\Agent\AgentController@delAgentRejectApplyData'); // 申请代理列表
    Route::any('/agent/level/set', 'Admin\Agent\AgentController@setAgentLevel'); // 修改代理等级

    //经销商和云仓相关路由
    Route::any('/dealer/dealerinvite/inviteposter/info', 'Admin\Dealer\DealerInvite\InvitePosterController@getInfo'); // 获取经销商邀请海报信息
    Route::any('/dealer/dealerinvite/inviteposter/save', 'Admin\Dealer\DealerInvite\InvitePosterController@save'); // 保存经销商邀请海报信息
    Route::any('/dealer/apply/setting/info', 'Admin\Dealer\DealerApplySettingController@getInfo'); // 经销商加盟设置信息
    Route::any('/dealer/apply/setting/save', 'Admin\Dealer\DealerApplySettingController@edit'); // 经销商加盟设置保存
    Route::any('/dealer/level/list', "Admin\Dealer\DealerLevelController@getList"); // 获取经销商等级列表
    Route::any('/dealer/level/perm/list', "Admin\Dealer\DealerLevelController@getPermList"); // 获取经销商等级列表
    Route::any('/dealer/level/info', "Admin\Dealer\DealerLevelController@getInfo"); // 获取经销商等级详情
    Route::any('/dealer/level/add', "Admin\Dealer\DealerLevelController@add"); // 添加经销商等级
    Route::any('/dealer/level/edit', "Admin\Dealer\DealerLevelController@edit"); // 编辑经销商等级
    Route::any('/dealer/level/disable', "Admin\Dealer\DealerLevelController@disable"); // 禁用经销商等级
    Route::any('/dealer/level/enable', "Admin\Dealer\DealerLevelController@enable"); // 启用经销商等级
    Route::any('/dealer/level/delete', "Admin\Dealer\DealerLevelController@delete"); // 删除经销商等级
    Route::any('/dealer/level/enabled/weight', "Admin\Dealer\DealerLevelController@getEnabledWeight"); // 获取已使用的权重
    Route::any('/dealer/performance/list', 'Admin\Dealer\DealerPerformanceController@getPerformanceList'); // 业绩列表
    Route::any('/dealer/performance/export', 'Admin\Dealer\DealerPerformanceController@exportPerformanceList'); // 业绩导出
    Route::any('/dealer/performance/reward/list', 'Admin\Dealer\DealerPerformanceRewardController@getList'); // 业绩奖列表
    Route::any('/dealer/recommend/reward/list', 'Admin\Dealer\DealerRecommendRewardController@getList'); // 推荐奖列表
    Route::any('/dealer/sale/reward/list', 'Admin\Dealer\DealerSaleRewardController@getList'); // 销售奖列表
    Route::any('/dealer/order/reward/list', 'Admin\Dealer\DealerOrderRewardController@getList'); // 订单返现奖列表
    Route::any('/dealer/reward/info', 'Admin\Dealer\DealerRewardController@getInfo'); // 奖金详情
    Route::any('/dealer/reward/exchange', 'Admin\Dealer\DealerRewardController@exchange'); // 奖金兑换
//    Route::any('/dealer/performance/reward/check', 'Admin\Dealer\DealerPerformanceRewardController@check'); // 业绩奖审核
    Route::any('/dealer/performance/reward/export', 'Admin\Dealer\DealerPerformanceRewardController@export'); // 业绩奖导出
    Route::any('/dealer/recommend/reward/export', 'Admin\Dealer\DealerRecommendRewardController@export'); // 推荐奖导出
    Route::any('/dealer/sale/reward/export', 'Admin\Dealer\DealerSaleRewardController@export'); // 销售奖导出
    Route::any('/dealer/sale/setting/save', 'Admin\Dealer\DealerSaleRewardSettingController@save'); // 保存销售奖设置
    Route::any('/dealer/sale/setting/info', 'Admin\Dealer\DealerSaleRewardSettingController@getInfo'); // 获取销售奖设置
    Route::any('/dealer/recommend/setting/save', 'Admin\Dealer\DealerRecommendRewardSettingController@save'); // 保存推荐奖设置
    Route::any('/dealer/recommend/setting/info', 'Admin\Dealer\DealerRecommendRewardSettingController@getInfo'); // 获取推荐奖设置
    Route::any('/dealer/order/setting/info', 'Admin\Dealer\DealerOrderRewardSettingController@getInfo'); // 获取订货返现奖设置
    Route::any('/dealer/order/setting/save', 'Admin\Dealer\DealerOrderRewardSettingController@save'); // 保存订货返现奖设置
    Route::any('/dealer/order/reward/export', 'Admin\Dealer\DealerOrderRewardController@export'); // 订货返现奖导出

    // --授权证书相关
    Route::any('/dealer/authcert/info', 'Admin\Dealer\AuthCert\AuthCertController@getInfo'); // 获取授权证书信息
    Route::any('/dealer/authcert/list', 'Admin\Dealer\AuthCert\AuthCertController@getList'); // 获取授权证书列表
    Route::any('/dealer/authcert/delete', 'Admin\Dealer\AuthCert\AuthCertController@delete'); // 删除某条授权证书
    Route::any('/dealer/authcert/save', 'Admin\Dealer\AuthCert\AuthCertController@save'); // 保存授权证书信息
    Route::any('/dealer/authcert/template', 'Admin\Dealer\AuthCert\AuthCertController@templateDate'); // 获取授权证书模板信息
    Route::any('/dealer/authcert/applysetting/info', 'Admin\Dealer\AuthCert\AuthCertController@getApplySettingInfo'); // 获取授权证书的应用设置
    Route::any('/dealer/authcert/applysetting/save', 'Admin\Dealer\AuthCert\AuthCertController@saveApplySettingInfo'); // 保存授权证书的应用设置

    Route::any('/cloudstock/list', 'Admin\CloudStock\CloudStockController@getList'); // 获取云仓列表
    Route::any('/cloudstock/info', 'Admin\CloudStock\CloudStockController@getInfo'); // 获取单个云仓的信息
    Route::any('/cloudstock/setstatus', 'Admin\CloudStock\CloudStockController@setStatus'); // 设置云仓状态
    Route::any('/cloudstock/skulist', 'Admin\CloudStock\CloudStockController@getSkuList'); // 获取SKU子仓的列表
    Route::post('/cloudstock/adjustinventory', 'Admin\CloudStock\CloudStockController@adjustInventory');  // 手工调整SKU子仓库存
    Route::any('/cloudstock/skuloglist', 'Admin\CloudStock\CloudStockController@getSkuLogList');  // 获取SKU子仓出入库记录
    Route::post('/cloudstock/addproduct', 'Admin\CloudStock\CloudStockController@addProduct');  // SKU子仓增加商品
    Route::any('/cloudstock/settlelist', 'Admin\CloudStock\CloudStockController@getSettleList');  // 云仓结算记录
    Route::any('/cloudstock/settlesummary', 'Admin\CloudStock\CloudStockController@getSettleSummary');  // 云仓结算汇总
    Route::any('/cloudstock/add', 'Admin\CloudStock\CloudStockController@add');  // 新建云仓
    Route::any('/cloudstock/check', 'Admin\CloudStock\CloudStockController@checkAddBerfore');  // 新建云仓前的检测
    Route::any('/cloudstock/product/skus', 'Admin\CloudStock\CloudStockApplySettingController@getSelectProductSkus');  // 获取选择的skus列表
    Route::any('/cloudstock/apply/setting', 'Admin\CloudStock\CloudStockApplySettingController@getSettingInfo');  // 云仓申请设置
    Route::any('/cloudstock/apply/setting/edit', 'Admin\CloudStock\CloudStockApplySettingController@editSetting');  // 保存云仓申请设置
    Route::any('/cloudstock/withdrawconfig/info', "Admin\CloudStock\CloudStockWithdrawConfigController@getInfo"); // 获取提现设置
    Route::any('/cloudstock/withdrawconfig/edit', "Admin\CloudStock\CloudStockWithdrawConfigController@edit"); // 编辑提现设置
    Route::any('/cloudstock/withdrawconfig/update', 'Admin\CloudStock\CloudStockWithdrawConfigController@updateWithdrawConfig'); // 更新提现设置
    Route::any('/cloudstock/withdrawconfig/checkPayConfig', "Admin\CloudStock\CloudStockWithdrawConfigController@checkPayConfig"); // 检测支付配置，无配置时不允许编辑提现配置
    Route::any('/cloudstock/agent/list', 'Admin\CloudStock\CloudStockController@getAgentList'); // 代理列表
    // 进货单路由
    Route::any('/cloudstock/purchase/order/list', 'Admin\CloudStock\PurchaseOrderController@getList');  // 获取进货订单列表
    Route::any('/cloudstock/purchase/order/info', 'Admin\CloudStock\PurchaseOrderController@getOrderInfo');  // 获取进货订单详情
    Route::any('/cloudstock/purchase/finance/review', 'Admin\CloudStock\PurchaseOrderController@financeReview');  // 进货订单财务审核
    Route::any('/cloudstock/purchase/finance/review/info', 'Admin\CloudStock\PurchaseOrderController@getFinanceReviewInfo');  // 获取进货订单财务审核需要的信息
    Route::any('/cloudstock/purchase/order/edit/inside/remark', 'Admin\CloudStock\PurchaseOrderController@editRemarkInside');  // 进货订单内部备注
    Route::any('/cloudstock/purchase/order/stock/deliver', 'Admin\CloudStock\PurchaseOrderController@orderManualStockDeliver');  // 进货订单配仓
    Route::any('/cloudstock/purchase/order/export', 'Admin\CloudStock\PurchaseOrderController@export');  // 进货订单导出
    // 提货单路由
    Route::any('/cloudstock/take/delivery/order/list', 'Admin\CloudStock\TakeDeliveryOrderController@getList');  // 提货订单列表
    Route::any('/cloudstock/take/delivery/order/info', 'Admin\CloudStock\TakeDeliveryOrderController@getOrderInfo');  // 提货订单详情
    Route::any('/cloudstock/take/delivery/order/deliver', 'Admin\CloudStock\TakeDeliveryOrderController@orderDeliver');  // 提货订单发货
    Route::any('/cloudstock/take/delivery/order/logistics/edit', 'Admin\CloudStock\TakeDeliveryOrderController@editLogistics');  // 提货订单修改物流
    Route::any('/cloudstock/take/delivery/order/inside/remark', 'Admin\CloudStock\TakeDeliveryOrderController@editRemarkInside');  // 提货订单内部备注
    Route::any('/cloudstock/take/delivery/order/export', 'Admin\CloudStock\TakeDeliveryOrderController@export');  // 提货订单导出
    Route::any('/cloudstock/settle/list', 'Admin\CloudStock\CloudStockSkuSettleController@getList');  // 云仓结算列表
    Route::any('/cloudstock/settle/export', 'Admin\CloudStock\CloudStockSkuSettleController@export');  // 云仓结算列表导出
    Route::any('/cloudstock/sku/edit', 'Admin\CloudStock\CloudStockController@editCloudstockSkuProduct');  // 新增某会员云仓库存商品
    Route::any('/cloudstock/sku/edit/list', 'Admin\CloudStock\CloudStockController@editCloudstockSkuProductList');  // 新增某会员云仓库存商品

    // 充值赠送优惠活动
    Route::any('/promotions/rechargebonus/info', 'Admin\Promotions\RechargeBonusController@getInfo'); // 获取充值赠送优惠设置
    Route::any('/promotions/rechargebonus/save', 'Admin\Promotions\RechargeBonusController@save'); // 保存充值赠送优惠设置

    // 防伪码
    Route::any('/security/code/list', 'Admin\ProductSecurity\SecurityCodeController@getCodeList'); // 获取防伪码列表
    Route::any('/security/code/delete', 'Admin\ProductSecurity\SecurityCodeController@deleteCode'); // 删除防伪码
    Route::any('/security/code/batch/add', 'Admin\ProductSecurity\SecurityCodeBatchController@add'); // 新增防伪码批次
    Route::any('/security/code/batch/list', 'Admin\ProductSecurity\SecurityCodeBatchController@getBatchList'); // 获取防伪码批次
    Route::any('/security/code/batch/delete', 'Admin\ProductSecurity\SecurityCodeBatchController@deleteBatch'); // 删除防伪码批次
    Route::any('/security/code/export', 'Admin\ProductSecurity\SecurityCodeController@exportCodeList'); // 导出防伪码

    //经销商
    Route::any('/dealer/basesetting/info', 'Admin\Dealer\DealerBaseSettingController@getInfo'); //获取基本设置
    Route::any('/dealer/basesetting/edit', 'Admin\Dealer\DealerBaseSettingController@edit'); //修改基本设置
    Route::any('/dealer/list', 'Admin\Dealer\DealerController@getDealerList'); // 经销商列表
    Route::any('/dealer/add', 'Admin\Dealer\DealerController@adminAddDealer'); // 新增经销商
    Route::any('/dealer/verify', 'Admin\Dealer\DealerController@verifyDealer'); // 审核经销商
    Route::any('/dealer/withdrawconfig/info', "Admin\Dealer\DealerWithdrawConfigController@getInfo"); // 获取提现设置 弃用
    Route::any('/dealer/withdrawconfig/edit', "Admin\Dealer\DealerWithdrawConfigController@edit"); // 编辑提现设置 弃用
    Route::any('/dealer/withdrawconfig/update', 'Admin\Dealer\DealerWithdrawConfigController@updateWithdrawConfig'); // 更新提现设置
    Route::any('/dealer/withdrawconfig/checkPayConfig', "Admin\Dealer\DealerWithdrawConfigController@checkPayConfig"); // 检测支付配置，无配置时不允许编辑提现配置
    Route::any('/apply/dealer/list', 'Admin\Dealer\DealerController@getApplyDealerList'); // 申请经销商列表
    Route::any('/dealer/cancel', 'Admin\Dealer\DealerController@cancelDealer'); // 取消经销商
    Route::any('/dealer/resume', 'Admin\Dealer\DealerController@resumeDealer'); // 恢复经销商
    Route::any('/apply/dealer/reject/delete', 'Admin\Dealer\DealerController@delDealerRejectApplyData'); // 删除经销商
    Route::any('/dealer/performance/setting/info', 'Admin\Dealer\DealerPerformanceRewardSettingController@getInfo'); // 业绩奖配置读取
    Route::any('/dealer/performance/setting/save', 'Admin\Dealer\DealerPerformanceRewardSettingController@save'); // 业绩奖配置修改
    Route::any('/dealer/reward/verify', 'Admin\Dealer\DealerRewardController@verify'); // 审核奖金

    // 余额管理
    Route::any('/finance/recharge/list', 'Admin\Finance\RechargeController@getlist');
    Route::any('/finance/recharge/info', 'Admin\Finance\RechargeController@getInfo');
    Route::any('/finance/recharge/verify', 'Admin\Finance\RechargeController@verify');
    Route::any('/finance/balance/member/list', 'Admin\Finance\RechargeController@getBalanceList');
    Route::any('/finance/balance/member/export', 'Admin\Finance\RechargeController@exportBalanceList');
    Route::any('/finance/recharge/export', 'Admin\Finance\RechargeController@exportVerifyBalanceList');

    // 直播相关
    Route::any('/live/info', 'Admin\Live\LiveController@getInfo'); //获取直播信息
    Route::any('/live/list', 'Admin\Live\LiveController@getList'); //获取直播信息列表
    Route::any('/live/delete', 'Admin\Live\LiveController@delete'); //删除
    Route::any('/live/close', 'Admin\Live\LiveController@close'); //结束直播
    Route::any('/live/open', 'Admin\Live\LiveController@open'); //开始直播
    Route::any('/live/setmuted', 'Admin\Live\LiveController@setMuted'); //设置是否全员禁言
    Route::any('/live/coupon/onscreen', 'Admin\Live\LiveController@setOnScreenCoupon'); //设置上屏优惠券
    Route::any('/live/product/onscreen', 'Admin\Live\LiveController@setOnScreenProduct'); //设置上屏商品
    Route::any('/live/add', 'Admin\Live\LiveController@add'); //添加直播间
    Route::any('/live/edit', 'Admin\Live\LiveController@edit'); //编辑直播间
    Route::any('/live/upload', 'Admin\Live\LiveController@uploadLiveImage'); //上传图片
    Route::any('/live/custom/upload', 'Admin\Live\LiveController@uploadNavCustomImage'); //上传导航自定义图片

    // 拼团相关
    Route::any('/group/buying/setting/list', 'Admin\GroupBuying\GroupBuyingController@getSettingList'); // 获取拼团活动列表
    Route::any('/group/buying/list', 'Admin\GroupBuying\GroupBuyingController@getList'); // 获取拼团活动列表
    Route::any('/group/buying/save', 'Admin\GroupBuying\GroupBuyingController@save'); // 保存拼团活动
    Route::any('/group/buying/info', 'Admin\GroupBuying\GroupBuyingController@getInfo'); // 获取拼团活动详情
    Route::any('/group/buying/product/info', 'Admin\GroupBuying\GroupBuyingController@getProductSku'); // 获取商品sku信息
    Route::any('/group/buying/end', 'Admin\GroupBuying\GroupBuyingController@setEnd'); // 结束活动
    Route::any('/group/buying/delete', 'Admin\GroupBuying\GroupBuyingController@delete'); // 删除活动
    Route::any('/group/buying/product/list', 'Admin\GroupBuying\GroupBuyingProductController@getList'); // 获取拼团活动产品列表

    // 区域代理相关
    Route::any('/area/agent/basesetting/info', 'Admin\AreaAgent\AreaAgentBaseSettingController@getInfo'); //获取区域代理基本设置
    Route::any('/area/agent/basesetting/edit', 'Admin\AreaAgent\AreaAgentBaseSettingController@edit'); //修改区域代理基本设置
    Route::any('/area/agent/apply/setting/info', 'Admin\AreaAgent\AreaAgentApplySettingController@getInfo'); // 区域代理加盟设置信息
    Route::any('/area/agent/apply/setting/save', 'Admin\AreaAgent\AreaAgentApplySettingController@edit'); // 区域代理加盟设置保存
    Route::any('/area/agent/performance/export', 'Admin\AreaAgent\AreaAgentPerformanceController@export'); // 区域代理业绩导出
    Route::any('/area/agent/performance/list', 'Admin\AreaAgent\AreaAgentPerformanceController@getList'); // 区域代理业绩列表
    Route::any('/area/agent/list', 'Admin\AreaAgent\AreaAgentorController@getList'); // 区域代理列表
    Route::any('/area/agent/cancel', 'Admin\AreaAgent\AreaAgentorController@cancel'); // 取消区域代理资格
    Route::any('/area/agent/recover', 'Admin\AreaAgent\AreaAgentorController@recover'); // 恢复区域代理资格
    Route::any('/area/agent/modify/area', 'Admin\AreaAgent\AreaAgentorController@modifyAgentArea'); // 修改代理区域
    Route::any('/area/agent/add', 'Admin\AreaAgent\AreaAgentorController@addAgentArea'); // 新增区域代理
    Route::any('/area/agent/disable/area', 'Admin\AreaAgent\AreaAgentorController@getDisableAreaIds'); // 获取已有代理的区域id
    Route::any('/area/agent/info', 'Admin\AreaAgent\AreaAgentorController@getAreaAgentCount'); // 获取区代相关统计
    Route::any('/area/agent/sub/list', 'Admin\AreaAgent\AreaAgentorController@getAreaAgentSubList'); // 获取区代下级列表
    // 区域代理审核
    Route::any('/area/agent/apply/list', 'Admin\AreaAgent\AreaAgentApplyController@getList'); // 区域代理申请列表
    Route::any('/area/agent/apply/verify', 'Admin\AreaAgent\AreaAgentApplyController@verify'); // 区域代理审核
    Route::any('/area/agent/apply/delete', 'Admin\AreaAgent\AreaAgentApplyController@delete'); // 区域代理申请记录删除
    //区域代理等级
    Route::post('/area/agent/level/edit', 'Admin\AreaAgent\AreaAgentLevelController@AreaEdit');//编辑或新增
    Route::get('/area/agent/level/get', 'Admin\AreaAgent\AreaAgentLevelController@index');//区代理等级查询
    Route::get('/area/agent/level/info', 'Admin\AreaAgent\AreaAgentLevelController@getInfo');//区代理等级查询
    Route::any('/area/agent/commission/list', 'Admin\AreaAgent\AreaAgentCommissionController@getList'); // 区域代理结算
    Route::any('/area/agent/commission/export', 'Admin\AreaAgent\AreaAgentCommissionController@export'); // 区域代理结算导出

    // 供应商
    Route::any('/supplier/count', "Admin\Supplier\SupplierController@getCountInfo"); // 供应商基本统计信息（会员详情用）
    Route::any('/supplier/basesetting/info', 'Admin\Supplier\SupplierBaseSettingController@getInfo'); //获取基本设置
    Route::any('/supplier/basesetting/edit', 'Admin\Supplier\SupplierBaseSettingController@edit'); //修改基本设置
    Route::get('/supplier/withdrawconfig/info', "Admin\Supplier\SupplierWithdrawConfigController@getInfo"); // 获取提现设置
    Route::get('/supplier/withdrawconfig/edit', "Admin\Supplier\SupplierWithdrawConfigController@edit"); // 编辑提现设置
    Route::post('/supplier/list', "Admin\Supplier\SupplierController@getList"); // 获取供应商列表
    Route::post('/supplier/add', "Admin\Supplier\SupplierController@add"); // 新增供应商
    Route::post('/supplier/cancel', "Admin\Supplier\SupplierController@cancel"); // 取消供应商资格
    Route::post('/supplier/recover', "Admin\Supplier\SupplierController@recover"); // 恢复供应商资格
    // 供应商审核商品相关
    Route::any('/supplier/product/verify/list', "Admin\Supplier\SupplierProductController@getWaitVerifyProductList"); // 获取待审核商品列表
    Route::any('/supplier/product/verify/info', "Admin\Supplier\SupplierProductController@getWaitVerifyProductInfo"); // 获取待审核商品数据
    Route::any('/supplier/product/verify', "Admin\Supplier\SupplierProductController@verifyProducts"); // 审核供应商商品
    // 供应商
    Route::any('/supplier/settle/list', "Admin\Supplier\SupplierSettleController@getList"); // 供应商结算列表
    Route::any('/supplier/settle/export', "Admin\Supplier\SupplierSettleController@export"); // 供应商结算导出

    // 快递
    Route::any('/express/setting/info', "Admin\Express\ExpressSettingController@getInfo"); // 获取快递设置信息
    Route::any('/express/setting/edit', "Admin\Express\ExpressSettingController@edit"); // 设置快递信息
    Route::any('/express/setting/authorize', "Admin\Express\ExpressSettingController@authorizeUrl"); // 获取授权地址
    Route::any('/express/setting/refresh/token', "Admin\Express\ExpressSettingController@refreshToken"); // 刷新token
    Route::any('/express/order/sync', "Admin\Order\OrderController@syncOrder"); // 订单同步
    //满额包邮
    Route::any('/activities/freefreight/info', "Admin\Activities\FreeFreightController@getInfo"); // 获取满额包邮信息
    Route::any('/activities/freefreight/edit', "Admin\Activities\FreeFreightController@edit"); // 修改满额包邮信息
});