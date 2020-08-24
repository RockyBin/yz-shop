<?php
//后台相关路由
Route::prefix('shop/supplier')->group(function () {

    Route::get('/info', "SupplierPlatform\IndexController@getSiteInfo"); // 获取网站信息
    Route::get('/wxapp/config/info', 'SupplierPlatform\IndexController@getWxappConfigInfo'); // 商城设置信息
    // 登录
    Route::any('/login', 'SupplierPlatform\LoginController@login'); // 登录
    Route::any('/logout', 'SupplierPlatform\LoginController@logout'); // 退出登录

    Route::any('/withdraw/account/info', 'SupplierPlatform\Withdraw\SupplierPlatformWithdrawConfigController@getInfo'); // 提现账号信息
    Route::any('/withdraw/account/edit', 'SupplierPlatform\Withdraw\SupplierPlatformWithdrawConfigController@edit'); // 编辑
    Route::any('/withdraw/list', 'SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@getList'); // 获取提现列表
    Route::any('/withdraw/count', 'SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@getCountInfo'); // 获取提现信息
    Route::any('/withdraw/info', 'SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@getInfo'); // 获取提现页面用到的信息，如提现配置，可提现余额等
    Route::any('/withdraw/add', 'SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@add'); // 新增提现
    Route::any('/withdraw/export', 'SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@export'); // 提现导出

    Route::any('/basesetting/info', 'SupplierPlatform\Setting\SupplierPlatformBaseSettingController@getInfo'); // 获取基础设置
    Route::any('/basesetting/edit', 'SupplierPlatform\Setting\SupplierPlatformBaseSettingController@edit'); // 编辑基础设置

    Route::any('/settle/list', 'SupplierPlatform\Settle\SupplierPlatformSettleController@getList'); // 获取结算列表
    Route::any('/settle/count', 'SupplierPlatform\Settle\SupplierPlatformSettleController@getCountInfo'); // 获取结算统计信息
    Route::any('/settle/export', 'SupplierPlatform\Settle\SupplierPlatformSettleController@export'); // 结算导出

    Route::any('/order/list', 'SupplierPlatform\Order\SupplierPlatformOrderController@getList'); // 订单列表
    Route::any('/order/info', 'SupplierPlatform\Order\SupplierPlatformOrderController@getInfo'); // 订单详情
    Route::any('/order/deliver', 'SupplierPlatform\Order\SupplierPlatformOrderController@deliver'); // 订单发货
    Route::any('/order/edit', 'SupplierPlatform\Order\SupplierPlatformOrderController@edit'); // 订单修改信息
    Route::any('/order/export', 'SupplierPlatform\Order\SupplierPlatformOrderController@export'); // 订单导出
    Route::any('/logistics/info', "SupplierPlatform\Order\SupplierPlatformLogisticsController@getInfo"); // 物流信息详情
    Route::any('/logistics/edit', "SupplierPlatform\Order\SupplierPlatformLogisticsController@edit"); // 物流信息修改

    // 商品相关
    Route::any('/product/list', "SupplierPlatform\Product\SupplierProductController@getList"); // 获取商品列表
    Route::any('/product/count', "SupplierPlatform\Product\SupplierProductController@getProductCount"); // 获取商品统计
    Route::any('/product/status/edit', "SupplierPlatform\Product\SupplierProductController@editProductStatus"); // 修改商品状态
    Route::any('/product/verify/status/edit', "SupplierPlatform\Product\SupplierProductController@editProductVerifyStatus"); // 修改商品审核状态
    Route::any('/product/check/serial/number', "SupplierPlatform\Product\SupplierProductController@checkSerialNumber"); // 检测编码是否重复
    Route::any('/product/data/{product}', "SupplierPlatform\Product\SupplierProductController@getProductData"); // 获取商品数据
    Route::any('/product/add/data', "SupplierPlatform\Product\SupplierProductController@getAddProductData"); // 获取新增商品所需数据
    Route::any('/product/image/upload', "SupplierPlatform\Product\SupplierProductController@uploadProductImage"); // 上传产品图片
    Route::any('/product/video/poster/upload', "SupplierPlatform\Product\SupplierProductController@uploadProductVideoPoster"); // 上传产品视频封面图片
    Route::any('/product/sku/image/upload', "SupplierPlatform\Product\SupplierProductController@uploadProductSkuImage"); // 上传产品sku图片
    Route::any('/product/save', "SupplierPlatform\Product\SupplierProductController@saveProductData"); // 保存商品数据
    Route::any('/product/list/export', "SupplierPlatform\Product\SupplierProductController@exportProductList"); // 导出商品列表

    Route::any('/aftersale/list', 'SupplierPlatform\Order\SupplierPlatformAfterSaleController@getList'); // 售后订单列表
    Route::any('/aftersale/info', 'SupplierPlatform\Order\SupplierPlatformAfterSaleController@getInfo'); // 售后订单详情
    Route::any('/aftersale/edit', 'SupplierPlatform\Order\SupplierPlatformAfterSaleController@editStatus'); // 售后订单改变状态
    Route::any('/aftersale/export', 'SupplierPlatform\Order\SupplierPlatformAfterSaleController@export'); // 售后订单改变状态

    Route::any('/password/check', 'SupplierPlatform\Supplier\SupplierController@passwordIsNull'); // 检测密码是否为空
    Route::any('/password/change', 'SupplierPlatform\Supplier\SupplierController@passwordChange'); // 修改旧密码
    Route::any('/password/set', 'SupplierPlatform\Supplier\SupplierController@passwordSet'); // 设置新密码
    Route::any('/staff/list', 'SupplierPlatform\Supplier\SupplierController@getList'); // 员工列表
    Route::any('/staff/info', 'SupplierPlatform\Supplier\SupplierController@getInfo'); // 员工列表
    Route::any('/staff/upload/headimg', 'SupplierPlatform\Supplier\SupplierController@uploadHeadImage'); // 上传员工头像
    Route::any('/staff/check/mobile', 'SupplierPlatform\Supplier\SupplierController@checkMobile'); // 检测手机号
    Route::any('/staff/save', 'SupplierPlatform\Supplier\SupplierController@save'); // 员工列表添加
    Route::any('/staff/edit', 'SupplierPlatform\Supplier\SupplierController@edit'); // 员工列表编辑
    Route::any('/staff/delete', 'SupplierPlatform\Supplier\SupplierController@delete'); // 员工列表刪除
    Route::any('/staff/status', 'SupplierPlatform\Supplier\SupplierController@editStatus'); // 员工禁用或恢复
    Route::any('/role/list', 'SupplierPlatform\Supplier\SupplierPlatformRoleController@getList'); // 角色列表
    Route::any('/role/info', 'SupplierPlatform\Supplier\SupplierPlatformRoleController@getInfo'); // 角色信息
    Route::any('/role/save', 'SupplierPlatform\Supplier\SupplierPlatformRoleController@save'); // 角色保存
    Route::any('/role/delete', 'SupplierPlatform\Supplier\SupplierPlatformRoleController@delete'); // 角色删除
    Route::any('/role/check/name', 'SupplierPlatform\Supplier\SupplierPlatformRoleController@checkRoleName'); // 角色名称是否重复
    // 文件管理器
    Route::any('/resource/folder/add',"SupplierPlatform\ResourceManage\SupplierFolderController@add"); //添加文件夹
    Route::any('/resource/folder/edit',"SupplierPlatform\ResourceManage\SupplierFolderController@rename"); //修改文件夹
    Route::any('/resource/folder/rename',"SupplierPlatform\ResourceManage\SupplierFolderController@rename"); //文件夹改名
    Route::any('/resource/folder/delete',"SupplierPlatform\ResourceManage\SupplierFolderController@delete"); //删除文件夹
    Route::any('/resource/folder/list',"SupplierPlatform\ResourceManage\SupplierFolderController@getList"); //列出文件夹

    Route::any('/resource/file/upload',"SupplierPlatform\ResourceManage\SupplierFileController@upload"); //上传文件
    Route::any('/resource/file/delete',"SupplierPlatform\ResourceManage\SupplierFileController@delete"); //删除文件
    Route::any('/resource/file/list',"SupplierPlatform\ResourceManage\SupplierFileController@getList"); //列出文件
});