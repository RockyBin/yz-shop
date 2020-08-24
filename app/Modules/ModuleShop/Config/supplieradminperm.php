<?php
return [
    // 登录页不检测安全
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\LoginController@login' => 'nocheck',

    // 后台提现设置
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw\SupplierPlatformWithdrawConfigController@getInfo' => 'withdraw.config.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw\SupplierPlatformWithdrawConfigController@edit' => 'withdraw.config.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@getList' => 'withdraw.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@getCountInfo' => 'withdraw.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@getInfo' => 'withdraw.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@add' => 'withdraw.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw\SupplierPlatformWithdrawController@export' => 'withdraw.view',

    // 商品列表
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@getList' => 'product.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@getProductCount' => 'product.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@editProductStatus' => 'product.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@editProductVerifyStatus' => 'product.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@getProductData' => 'product.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@getAddProductData' => 'product.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@exportProductList' => 'product.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@saveProductData' => 'product.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@uploadProductImage' => 'product.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@uploadProductVideoPoster' => 'product.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product\SupplierProductController@uploadProductSkuImage' => 'product.operate',


    // 订单
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformOrderController@getList' => 'order.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformOrderController@getInfo' => 'order.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformOrderController@deliver' => 'order.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformOrderController@edit' => 'order.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformOrderController@export' => 'order.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformLogisticsController@getInfo' => 'order.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformLogisticsController@edit' => 'order.operate',

    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformAfterSaleController@getList' => 'order.after-sale.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformAfterSaleController@getInfo' => 'order.after-sale.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformAfterSaleController@editStatus' => 'order.after-sale.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order\SupplierPlatformAfterSaleController@export' => 'order.after-sale.view',


    // 财务
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Settle\SupplierPlatformSettleController@getList' => 'finance.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Settle\SupplierPlatformSettleController@getCountInfo' => 'finance.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Settle\SupplierPlatformSettleController@export' => 'finance.view',


    // 员工相关
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierController@getList' => 'staff.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierController@add' => 'staff.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierController@edit' => 'staff.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierController@editStatus' => 'staff.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierPlatformRoleController@getList' => 'role.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierPlatformRoleController@getInfo' => 'role.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierPlatformRoleController@save' => 'role.operate',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier\SupplierPlatformRoleController@delete' => 'role.operate',

    //基础设置
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Setting\SupplierPlatformBaseSettingController@getInfo' => 'basesetting.view',
    'App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Setting\SupplierPlatformBaseSettingController@edit' => 'basesetting.operate',
];
