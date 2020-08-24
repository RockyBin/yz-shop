<?php
//用户中心路由
Route::prefix('shop/member')->group(function () {
    Route::get('/', 'Front\Member\IndexController@index');

    Route::any('/login', 'Front\Member\LoginController@index'); // 登录页面
    Route::any('/login/info', "Front\Member\LoginController@getInfo"); //返回当前登录用户的信息
    Route::any('/login/register', 'Front\Member\LoginController@register'); // 注册页面
    Route::any('/login/mobile', "Front\Member\LoginController@mobileCodeLogin"); // 手机验证码登录或注册
    Route::any('/login/password', "Front\Member\LoginController@passwordLogin"); // 密码登录
    Route::any('/login/reset/password', "Front\Member\LoginController@resetPassword"); // 修改密码
    Route::any('/login/config', "Front\Member\LoginController@getConfig"); // 获取配置

    Route::any('/login/wxlogin', "Front\Member\LoginController@wxLogin"); // 微信授权登录
    Route::any('/login/wxlogincallback', "Front\Member\LoginController@wxLoginCallBack"); // 微信授权回调地址

    Route::any('/login/wxlogin/wxwork', "Front\Member\LoginController@wxWorkLogin"); //企业微信授权登录
    Route::any('/login/wxlogincallback/wxwork', "Front\Member\LoginController@wxWorkLoginCallBack"); //企业微信授权回调地址

    Route::any('/login/wxscanlogin', "Front\Member\LoginController@wxScanLogin"); // 获取微信扫码授权相关的二维码信息
    Route::any('/login/wxscancheck', "Front\Member\LoginController@wxCheckHasScan"); // 检测微信是否已经扫码
    Route::any('/login/wxscanlogincallback/{scanid}', "Front\Member\LoginController@wxScanLoginCallback"); // 微信扫码授权回调地址(手机微信用)

    Route::any('/login/qqlogin', "Member\LoginController@qqLogin"); //QQ 授权登录
    Route::any('/login/qqcallback', "Member\LoginController@qqLoginCallBack"); // QQ授权回调地址

    Route::any('/login/alipaylogin', "Front\Member\LoginController@alipayLogin"); // 支付宝授权登录
    Route::any('/login/alipaycallback', "Front\Member\LoginController@alipayLoginCallBack"); // 支付宝授权回调地址

    Route::any('/login/bind', "Front\Member\LoginController@showBind"); // 显示会员绑定公众号，支付宝等授权帐号的界面
    Route::any('/login/dobind', "Front\Member\LoginController@doBind"); // 会员绑定公众号，支付宝等授权帐号
    Route::any('/login/showinvite', "Front\Member\LoginController@showMemberInvite"); // 会员绑定公众号，支付宝等授权帐号

    // 购物车路由
    Route::any('/cart/num', "Front\ShoppingCart\ShoppingCartNumController@getShoppingCartNum");// 购物车中的商品数量
    Route::any('/cart/list', "Front\ShoppingCart\ShoppingCartController@getCartProductList");// 购物车产品列表
    Route::any('/cart/add', "Front\ShoppingCart\ShoppingCartController@addProductToCart"); // 添加产品到购物车
    Route::any('/cart/refresh', "Front\ShoppingCart\ShoppingCartController@cartRefresh"); // 刷新购物车 获取失效产品
    Route::any('/cart/num/change', "Front\ShoppingCart\ShoppingCartController@changeCartProductNum"); // 修改购物车产品数量
    Route::any('/cart/money', "Front\ShoppingCart\ShoppingCartController@calCartMoney"); // 计算购物车内选择的产品金额
    Route::any('/cart/delete', "Front\ShoppingCart\ShoppingCartController@deleteProductFromCart"); // 计算购物车内选择的产品金额
    //Route::any('/test', "Front\ShoppingCart\ShoppingCartController@test");
	
	//会员收货地址
	Route::any('/address/list', "Front\Member\AddressController@getAddressList"); // 获取地址列表
    Route::any('/address/edit', "Front\Member\AddressController@editAddress"); // 编辑/新建 地址
    Route::any('/address/delete', "Front\Member\AddressController@deleteAddress"); // 删除地址

    Route::any('/order', "Front\Order\OrderController@index"); // 进入生成订单界面
    Route::any('/order/set', "Front\Order\OrderController@setOrder"); // 设置订单相关信息
    Route::any('/order/create', "Front\Order\OrderController@createOrder"); // 创建订单
    Route::any('/order/getproductlimit', "Front\Order\OrderController@getProductLimitInfo"); // 获取商品的限购数量、起购量等
    Route::any('/order/pay', "Front\Order\OrderController@payOrder"); // 订单支付
    Route::any('/order/testPay', "Front\Order\OrderController@testPay");
    Route::any('/order/pay/result', "Front\Order\OrderController@orderPayResult"); // 订单支付结果页
    Route::any('/order/info', "Front\Order\OrderController@orderInfo"); // 获取订单详情
    Route::any('/order/list', "Front\Order\OrderController@getList"); // 获取订单列表
    Route::any('/order/cancel', "Front\Order\OrderController@cancelOrder"); // 获取订单详情
    Route::any('/order/pay/time', "Front\Order\OrderController@getPayOrderTime"); // 订单支付时间
    Route::any('/order/receipt', "Front\Order\OrderController@confirmReceipt"); // 订单确认收货
    Route::any('/order/address/list', "Front\Member\AddressController@getCreateOrderAddressList"); // 创建订单时的地址列表
    Route::any('/order/pay/info', "Front\Order\OrderController@orderPayInfo"); // 订单支付信息
    Route::any('/order/item/nocomment', "Front\Order\OrderController@getNoCommentItemList"); // 订单支付信息

    Route::any('/center/index', "Front\Member\MemberInfoController@index"); // 会员中心首页
    Route::any('/center/info', "Front\Member\MemberInfoController@getInfo"); // 会员中心会员详情
    Route::any('/center/mobile/check', "Front\Member\MemberInfoController@mobileCheck"); // 会员中心手机验证
    Route::any('/center/mobile/change', "Front\Member\MemberInfoController@mobileChange"); // 会员中心更新手机号
    Route::any('/center/password/change', "Front\Member\MemberInfoController@passwordChange"); // 会员中心修改登录密码
    Route::any('/center/paypassword/change', "Front\Member\MemberInfoController@payPasswordChange"); // 会员中心修改支付密码
    Route::any('/center/edit', "Front\Member\MemberInfoController@edit"); // 会员中心会员修改基本信息
    Route::any('/center/mobile/get', "Front\Member\MemberInfoController@getMobile"); // 会员中心修改登录密码
    Route::any('/center/withdrawaccount/info', "Front\Member\WithdrawAccountController@getInfo"); // 会员中心提现信息
    Route::any('/center/withdrawaccount/edit', "Front\Member\WithdrawAccountController@edit"); // 会员中心提现信息

    Route::any('/point/info', "Front\Point\PointController@getInfo"); // 积分信息
    Route::any('/point/list', "Front\Point\PointController@getList"); // 积分列表
    Route::any('/point/config', "Front\Point\PointController@getConfig"); // 积分规则
    Route::any('/point/give/search/member', "Front\Point\PointController@pointGiveSearchMember"); // 会员搜索
    Route::any('/point/give', "Front\Point\PointController@pointGive"); // 积分赠送

    Route::any('/coupon/list', 'Front\Member\Center\MemberCouponController@getCoupon');//优惠券列表

    Route::any('/product/collection/add', "Front\Product\ProductCollectionController@add"); // 收藏添加
    Route::any('/product/collection/list', "Front\Product\ProductCollectionController@getList"); // 收藏列表
    Route::any('/product/collection/delete', "Front\Product\ProductCollectionController@delete"); // 收藏删除

    Route::any('/logistics/info', "Front\Order\LogisticsController@getInfo"); // 单个物流信息
    Route::any('/logistics/order', "Front\Order\LogisticsController@getListByOrder"); // 拆单物流

    Route::any('/finance/balance', "Front\Member\FinanceController@balanceInfo"); // 会员余额
    Route::any('/finance/balance/list', "Front\Member\FinanceController@memberBalanceList"); // 会员余额列表
    Route::any('/finance/balance/recharge', "Front\Member\FinanceController@memberBalanceRecharge"); // 会员余额列表
    Route::any('/finance/withdraw/info', "Front\Member\WithdrawController@getInfo"); // 会员提现信息
    Route::any('/finance/withdraw/add', "Front\Member\WithdrawController@addWithdrawFinance"); // 提现
    Route::any('/finance/withdraw/checkdata', "Front\Member\WithdrawController@checkData"); // 提现检测信息
    Route::any('/finance/balance/frozen/list', "Front\Member\FinanceController@memberBalanceFrozenList"); // 会员余额冻结列表
    Route::any('/finance/balance/list/withdrawing', "Front\Member\FinanceController@memberBalanceWithdrawList"); // 会员余额提现列表
    Route::any('/finance/balance/list/withdraw', "Front\Member\FinanceController@memberBalanceWithdrawList"); // 会员余额提现列表
    Route::any('/finance/config', "Front\Member\FinanceController@config"); // 财务相关配置
    Route::any('/finance/balance/give/search/member', "Front\Member\FinanceController@balanceGiveSearchMember"); // 转现会员搜索
    Route::any('/finance/balance/give', "Front\Member\FinanceController@balanceGive"); // 余额赠送
    Route::any('/finance/balance/jump', "Front\Member\FinanceController@balanceJump"); // 会员余额
    Route::any('/finance/balance/dealer/recharge', "Front\Member\FinanceController@dealerBalanceRecharge"); // 向上级充值

    Route::any('/aftersale/apply', "Front\Order\AfterSaleController@applyAfterSale"); // 申请售后数据
    Route::any('/aftersale/create', "Front\Order\AfterSaleController@createAfterSale"); // 创建售后订单
    Route::any('/aftersale/edit', "Front\Order\AfterSaleController@editAfterSale"); // 编辑售后订单
    Route::any('/aftersale/cancel', "Front\Order\AfterSaleController@cancelAfterSale"); // 关闭售后订单
    Route::any('/aftersale/info', "Front\Order\AfterSaleController@getAfterSaleInfo"); // 售后订单详情
    Route::any('/aftersale/logistics/edit', "Front\Order\AfterSaleController@editLogisticsInfo"); // 填写售后物流
    Route::any('/aftersale/list', "Front\Order\AfterSaleController@getList"); // 获取售后列表
    Route::any('/aftersale/batchlist', "Front\Order\AfterSaleController@getAfterSaleBatchList"); // 获取售后列表
    Route::any('/aftersale/product/list', "Front\Order\AfterSaleController@getCanAfterSaleProductList"); // 获取可售后产品列表
    Route::any('/aftersale/upload/image', "Front\Order\AfterSaleController@uploadAfterSaleImage"); // 上传售后图片

    Route::any('/distribution/index', "Front\Member\DistributionController@index"); // 分销中心首页
    Route::any('/distribution/team/list', "Front\Member\DistributionController@getSubTeamList"); // 团队列表
    Route::any('/distribution/distributor/info', "Front\Member\DistributionController@getInfo"); // 分销商资料
    Route::any('/distribution/distributor/list', "Front\Member\DistributionController@getDistributorList"); // 下级分销商列表
    Route::any('/distribution/member/list', "Front\Member\DistributionController@getMemberList"); // 下级会员列表
    Route::any('/distribution/commission/list', "Front\Member\DistributionController@getCommissionList"); // 佣金列表
    Route::any('/distribution/distributor/apply', "Front\Member\DistributionController@apply"); // 分销商申请
    Route::any('/distribution/distributor/apply/form', "Front\Member\DistributionController@form"); // 分销商表单申请
    Route::any('/distribution/distributor/apply/file', "Front\Member\DistributionController@file"); // 分销商表单申请提交文件
    Route::any('/distribution/config', "Front\Member\DistributionController@config"); // 分销商设置

    Route::any('/product/comment/add', "Front\Member\ProductCommentController@add"); // 发布评论
    Route::any('/product/comment/list', "Front\Member\ProductCommentController@getList"); // 评论列表

    Route::any('/apply/agent/form', "Front\Member\Agent\AgentController@getAgentApplyForm"); // 获取申请代理表单
    Route::any('/apply/agent/upload', "Front\Member\Agent\AgentController@applyAgentFile"); // 申请代理上传文件
    Route::any('/apply/agent/save', "Front\Member\Agent\AgentController@applyAgentForm"); // 申请代理保存表单数据
    Route::any('/apply/agent/checkpay', "Front\Member\Agent\AgentController@checkPay"); // 检测申请表单支付状态

    Route::any('/agent/team/info', "Front\Member\Agent\AgentController@getAgentTeamInfo"); // 代理代理概况
    Route::any('/agent/performance/info', "Front\Member\Agent\AgentRewardController@performanceCount"); // 业绩统计
    Route::any('/agent/members/list', "Front\Member\Agent\AgentMembersController@getList"); // 团队成员列表
    Route::any('/agent/members/index', "Front\Member\Agent\AgentMembersController@index"); // 团队成员情况
    Route::any('/agent/reward/index', "Front\Member\Agent\AgentRewardController@index"); // 团队分红
    Route::any('/agent/reward/list', "Front\Member\Agent\AgentRewardController@getRewardList"); // 团队分红列表
    Route::any('/agent/reward/withdraw/list', "Front\Member\Agent\AgentRewardController@getRewardWithdrawList"); // 团队分红提现列表
    Route::any('/agent/recommend/info', "Front\Member\Agent\AgentRewardController@recommendCount"); // 推荐奖统计
    Route::any('/agent/recommend/list', "Front\Member\Agent\AgentRewardController@recommendList"); // 推荐奖列表

    /** 经销商云仓相关 */
    Route::any('/apply/dealer/form', "Front\Member\Dealer\DealerApplyController@getDealerApplyForm"); // 获取经销商申请表单
    Route::any('/apply/dealer/upload', "Front\Member\Dealer\DealerApplyController@applyDealerFile"); // 经销商申请上传文件
    Route::any('/apply/dealer/save', "Front\Member\Dealer\DealerApplyController@applyDealerForm"); // 经销商申请保存表单数据
    Route::any('/apply/dealer/checkpay', "Front\Member\Dealer\DealerApplyController@checkPay"); // 检测经销商申请表单支付状态
    Route::any('/apply/dealer/getparentpayconfig', "Front\Member\Dealer\DealerApplyController@getParentPayConfig"); // 获取上家的收款方式设置

    Route::any('/dealer/authcert/info', "Front\Member\Dealer\AuthCertController@getInfo"); // 获取代理授权证书信息
    Route::any('/dealer/authcert/create', "Front\Member\Dealer\AuthCertController@create"); // 生成代理授权证书
    Route::any('/apply/dealer/invite/config', "Front\Member\Dealer\DealerApplyController@getInviteConfig"); // 获取授权邀请时使用的配置信息

    Route::any('/dealer/account/list', "Front\Member\Dealer\DealerAccountController@getList"); // 获取经销商收款帐号信息
    Route::any('/dealer/account/edit', "Front\Member\Dealer\DealerAccountController@edit"); // 修改经销商收款帐号信息
    Route::any('/dealer/account/delete', "Front\Member\Dealer\DealerAccountController@delete"); // 删除经销商收款帐号信息

    Route::any('/dealer/verify/list', "Front\Member\Dealer\DealerVerifyController@getList"); // 获取审核列表
    Route::any('/dealer/verify/from/list', "Front\Member\Dealer\DealerVerifyController@getFromMemberList"); //获取被审核人相关审核列表
    Route::any('/dealer/verify', "Front\Member\Dealer\DealerVerifyController@verify"); // 前台审核
    Route::any('/dealer/verify/info', "Front\Member\Dealer\DealerVerifyController@getInfo"); // 前台审核详情
    Route::any('/dealer/verify/from/info', "Front\Member\Dealer\DealerVerifyController@getFromMemberInfo"); // 前台被审核人相关审核详情

    Route::any('/dealer/team/list', "Front\Member\Dealer\DealerTeamManageController@getList"); // 前台团队管理列表
    Route::any('/dealer/team/info', "Front\Member\Dealer\DealerTeamManageController@getInfo"); // 前台团队管理列表详细

    Route::any('cloudstock/product/list', 'Front\Member\CloudStock\ProductListController@getList'); // 列出商品，云仓进货时用
    Route::any('cloudstock/shopcart/setskunum', 'Front\Member\CloudStock\ShopCartController@setSkuNum'); // 设置商品数量，云仓进货时用
    Route::any('cloudstock/shopcart/setskunumbatch', 'Front\Member\CloudStock\ShopCartController@setSkuNumBatch'); // 批量设置商品数量，云仓进货时用
    Route::any('cloudstock/shopcart/list', 'Front\Member\CloudStock\ShopCartController@getList'); //获取购物车商品列表
    Route::any('cloudstock/shopcart/money', 'Front\Member\CloudStock\ShopCartController@getProductMoney'); //获取购物车选定商品的总价
    Route::any('cloudstock/shopcart/removesku', 'Front\Member\CloudStock\ShopCartController@removeSku'); //删除购物车中指定的商品
    Route::any('cloudstock/shoporder/goodslist', 'Front\Member\CloudStock\ShopOrderController@getGoodsList'); //在确认下单前，获取订单的结算商品列表
    Route::any('cloudstock/shoporder/create', 'Front\Member\CloudStock\ShopOrderController@create'); //创建进货单
    Route::any('cloudstock/mypurchaseorder/list', 'Front\Member\CloudStock\PurchaseOrderController@getList'); //我的进货单列表
    Route::any('cloudstock/mypurchaseorder/info', 'Front\Member\CloudStock\PurchaseOrderController@getInfo'); //我的进货单详情
    Route::any('cloudstock/mypurchaseorder/cancel', 'Front\Member\CloudStock\PurchaseOrderController@cancel'); //取消进货单
    Route::any('cloudstock/mypurchaseorder/payconfig', 'Front\Member\CloudStock\PurchaseOrderController@getPayConfig'); //获取进货单的支付配置信息
    Route::any('cloudstock/mypurchaseorder/checkpay', 'Front\Member\CloudStock\PurchaseOrderController@checkPay'); //检测进货单是否已支付，在线上支付时用来确认支付结果
    Route::post('cloudstock/mypurchaseorder/pay', 'Front\Member\CloudStock\PurchaseOrderController@payOrder'); //支付进货单
    Route::post('cloudstock/mypurchaseorder/deliver', 'Front\Member\CloudStock\PurchaseOrderController@orderStockDeliver'); //下级进货单配仓
    Route::post('cloudstock/mypurchaseorder/inventory', 'Front\Member\CloudStock\PurchaseOrderController@checkInventory'); //下级进货单配仓库存检测
    Route::any('cloudstock/mypurchaseorder/verify', 'Front\Member\CloudStock\PurchaseOrderController@verify'); //下级进货单审核
    Route::any('cloudstock/shopcart/replenish', 'Front\Member\CloudStock\ShopCartController@onceReplenish'); //下级进货单审核
    Route::any('cloudstock/balance/payconfig', 'Front\Member\CloudStock\CloudStockCenterController@getBalanceConfig'); //经销商余额中心支付配置

    Route::any('cloudstock/member/info', 'Front\Member\CloudStock\CloudStockCenterController@index'); //云仓工作台
    Route::any('cloudstock/member/skulog', 'Front\Member\CloudStock\CloudStockCenterController@getSkuLog'); //云仓工作台
    Route::any('cloudstock/member/product/list', 'Front\Member\CloudStock\ProductListController@getCloudStockProductList'); //云仓商品列表 提货用
    Route::any('cloudstock/member/product/count', 'Front\Member\CloudStock\ProductListController@getProductsCount'); //云仓商品统计
    Route::any('cloudstock/member/product/skus', 'Front\Member\CloudStock\ProductListController@getCloudStockProductSkuList'); //云仓某一商品sku
    Route::any('cloudstock/member/general/situation', 'Front\Member\CloudStock\CloudStockGeneralSituationController@index'); //云仓概况
    Route::any('cloudstock/member/supplier', 'Front\Member\CloudStock\CloudStockCenterController@getDirectlyUnderSupplier'); //云仓直属供货商
    Route::any('cloudstock/member/team', 'Front\Member\CloudStock\CloudStockCenterController@getCloudStockTeam'); //云仓直属供货商
    Route::any('cloudstock/member/replenish', 'Front\Member\CloudStock\CloudStockReplenishProductController@getReplenishProduct'); //云仓补货提醒
    Route::any('cloudstock/takedelivery/shopcart/num', 'Front\Member\CloudStock\ProductListController@getTakeDeliveryShoppingCartNum'); //云仓购物车商品数量
    Route::post('cloudstock/takedelivery/shopcart/add', 'Front\Member\CloudStock\TakeDeliveryShoppingCartController@addToCart'); //添加商品到云仓提货购物车
    Route::any('cloudstock/takedelivery/shopcart/list', 'Front\Member\CloudStock\TakeDeliveryShoppingCartController@getProductList'); //添加商品到云仓提货购物车
    Route::post('cloudstock/takedelivery/shopcart/decrement', 'Front\Member\CloudStock\TakeDeliveryShoppingCartController@ShoppingCartDecrementProduct'); //提货购物车商品减少数量
    Route::post('cloudstock/takedelivery/shopcart/increment', 'Front\Member\CloudStock\TakeDeliveryShoppingCartController@ShoppingCartIncrementProduct'); //提货购物车商品增加数量
    Route::post('cloudstock/takedelivery/shopcart/remove', 'Front\Member\CloudStock\TakeDeliveryShoppingCartController@ShoppingCartRemoveProduct'); //删除提货购物车商品
    Route::any('cloudstock/takedelivery/order/create/data', 'Front\Member\CloudStock\TakeDeliveryOrderController@getCreateOrderData'); //获取创建提货订单所需要的数据
    Route::post('cloudstock/takedelivery/order/create', 'Front\Member\CloudStock\TakeDeliveryOrderController@createOrder'); //创建提货订单
    Route::post('cloudstock/takedelivery/order/receipt', 'Front\Member\CloudStock\TakeDeliveryOrderController@orderReceipt'); //提货订单确认收货
    Route::any('cloudstock/takedelivery/order/list', 'Front\Member\CloudStock\TakeDeliveryOrderController@getOrderList'); //提货订单列表
    Route::any('cloudstock/takedelivery/order/info', 'Front\Member\CloudStock\TakeDeliveryOrderController@getOrderInfo'); //提货订单详情
    Route::any('cloudstock/takedelivery/logistics/order', "Front\Order\LogisticsController@getListByCloudStockTakeDeliveryOrder"); // 拆单物流
    Route::any('cloudstock/settle/info', 'Front\Member\CloudStock\CloudStockSettleController@getSettleFinanceInfo'); //获取云仓收入详情
    Route::any('cloudstock/settle/list', 'Front\Member\CloudStock\CloudStockSettleController@getSettleList'); //获取云仓收入列表
    Route::any('cloudstock/reward/withdraw/list', 'Front\Member\CloudStock\CloudStockSettleController@getRewardWithdrawList'); //获取云仓收入列表
    Route::post('cloudstock/takedelivery/pay', 'Front\Member\CloudStock\TakeDeliveryOrderController@payOrder'); //支付提货单运费
    Route::any('cloudstock/takedelivery/payconfig', 'Front\Member\CloudStock\TakeDeliveryOrderController@getPayConfig'); //获取进货单的支付配置信息
    Route::any('cloudstock/takedelivery/checkpay', 'Front\Member\CloudStock\TakeDeliveryOrderController@checkPay'); //检测进货单是否已支付，在线上支付时用来确认支付
    Route::any('cloudstock/takedelivery/cancel', 'Front\Member\CloudStock\TakeDeliveryOrderController@cancel'); //取消进货单
    Route::any('dealer/performance/reward/list', 'Front\Member\CloudStock\CloudStockSettleController@getPerformanceRewardList'); //经销商业绩列表
    Route::any('dealer/sub/performance', 'Front\Member\Dealer\DealerPerformanceController@getSubPerformanceList'); //经销商下级业绩列表
    Route::any('dealer/performance/personal', 'Front\Member\Dealer\DealerPerformanceController@getPerformance'); //经销商个人业绩
    Route::any('cloudstock/skustock/edit', 'Front\Member\CloudStock\CloudStockCenterController@editCloudstockSkuProduct'); //云仓转移
    Route::any('cloudstock/dealer/sub', "Front\Member\CloudStock\CloudStockCenterController@getDealerSubList"); // 获取下级经销商列表
    Route::any('dealer/reward/my/list', "Front\Dealer\DealerRewardController@getMyRewardList"); // 获取我的奖金列表
    Route::any('dealer/reward/exchange', "Front\Dealer\DealerRewardController@exchange"); // 兑换我的奖金
    Route::any('dealer/reward/pass', "Front\Dealer\DealerRewardController@pass"); // 奖金审核通过
    Route::any('dealer/reward/reject', "Front\Dealer\DealerRewardController@reject"); // 拒绝奖金
    Route::any('dealer/reward/info', "Front\Dealer\DealerRewardController@getInfo"); // 拒绝奖金
    Route::any('dealer/reward/in/list', "Front\Dealer\DealerRewardController@getInRewardList"); // 获取奖金收入列表
    Route::any('dealer/reward/out/list', "Front\Dealer\DealerRewardController@getOutRewardList"); // 获取奖金支出列表

    //小店(会员中心)
    Route::any('smallshop/edit', "Front\Member\SmallShop\SmallShopController@edit"); // 保存设置
    Route::any('smallshop/upload', "Front\Member\SmallShop\SmallShopController@upload"); // 上传图片
    Route::any('smallshop/add', "Front\Member\SmallShop\SmallShopController@add"); // 申请小店
    Route::any('smallshop/product/edit', "Front\Member\SmallShop\SmallShopController@editSmallShopProduct"); // 添加小店产品信息
    Route::any('smallshop/info', "Front\Member\SmallShop\SmallShopController@getInfo"); // 小店信息
    Route::any('smallshop/product/list', "Front\Member\SmallShop\SmallShopController@getSmallShopProductList"); // 小店产品信息

    //粉丝相关
    Route::any('fans/fans', "Front\Member\FansController@getFansList"); // 列出我推荐的粉丝
    Route::any('fans/member', "Front\Member\FansController@getMemberList"); // 列出我推荐的会员

    // 拼团相关
    Route::any('/group/buying/order', "Front\Order\GroupBuyingOrderController@index"); // 进入生成订单界面
    Route::any('/group/buying/order/set', "Front\Order\GroupBuyingOrderController@setOrder"); // 设置订单相关信息
    Route::any('/group/buying/order/create', "Front\Order\GroupBuyingOrderController@createOrder"); // 创建订单
    Route::any('/group/buying/order/pay/result', "Front\Order\GroupBuyingOrderController@orderPayResult"); // 订单支付结果页
    Route::any('/group/buying/order/getproductlimit', "Front\Order\GroupBuyingOrderController@getGroupBuyingProductLimitInfo"); // 拼团限购

    // 区域代理
    Route::any('/area/agent/check', 'Front\Member\AreaAgent\AreaAgentController@check'); //检测
    Route::any('/area/agent/apply', 'Front\Member\AreaAgent\AreaAgentController@apply'); //申请页面
    Route::any('/area/agent/apply/from', 'Front\Member\AreaAgent\AreaAgentController@getAreaAgentApplyForm'); //获取申请表单
    Route::any('/area/agent/apply/district', 'Front\Member\AreaAgent\AreaAgentController@getUsedDistrict'); // 获取已使用的省市区
    Route::any('/area/agent/apply/upload', 'Front\Member\AreaAgent\AreaAgentController@applyAreaAgentFile'); // 上传申请时的文件
    Route::any('/area/agent/apply/save', 'Front\Member\AreaAgent\AreaAgentController@applyAreaAgentSaveForm'); // 保存表单
    Route::any('/area/agent/withdraw/list', "Front\Member\AreaAgent\AgentCommissionController@getCommissionWithdrawList"); // 区代返佣提现列表
    Route::any('/area/agent/commission/index', "Front\Member\AreaAgent\AgentCommissionController@index"); // 区代返佣首页
    Route::any('/area/agent/commission/list', "Front\Member\AreaAgent\AgentCommissionController@getList"); // 区代返佣列表
    Route::any('/area/agent/member/index', "Front\Member\AreaAgent\AreaAgentMemberController@index"); // 区代成员基本信息，包括自身信息和下级成员统计信息
    Route::any('/area/agent/member/list', "Front\Member\AreaAgent\AreaAgentMemberController@getList"); // 下级区代成员列表
    Route::any('/area/agent/list', "Front\Member\AreaAgent\AreaAgentMemberController@getAreaAgentList"); // 获取此会员代理的所有地区列表
    Route::any('/area/agent/center', "Front\Member\AreaAgent\AreaAgentMemberController@center"); // 区域代理中心
    Route::any('/area/agent/performance', "Front\Member\AreaAgent\AreaAgentPerformanceController@getPerformance"); // 区域代理业绩
});