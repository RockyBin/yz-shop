<?php

namespace App\Modules\ModuleShop\Libs\Link;
/**
 * 链接的常量表
 * Class Constants
 * @package App\Modules\ModuleShop\Libs
 */
class LinkConstants
{
    //链接类型
    const LinkType_Home = 'home'; //首页
    const LinkType_Page = 'page'; //自定义页面
    const LinkType_ProductClass = 'product_class'; //产品分类
    const LinkType_ProductList = 'product_list'; //产品列表
    const LinkType_ProductDetail = 'product_detail'; //产品详情
    const LinkType_LiveList = 'live_list'; //直播列表
    const LinkType_LiveDetail = 'live_detail'; //直播详情
    const LinkType_GroupBuyingDetail = 'group_detail'; //拼团活动详情
    const LinkType_GroupBuyingProductDetail = 'group_product_detail'; //拼团商品详情
    const LinkType_External = 'external'; //外部链接
    const LinkType_None = 'none'; //无链接

    const LinkType_UserCenter = 'user_center'; //会员中心
    const LinkType_CouponCenter = 'coupon_center'; //领券中心
    const LinkType_DistributionCenter = 'distribution_center'; //分销中心
    const LinkType_AgentCenter = 'agent_center'; //分销中心
    const LinkType_AreaAgentCenter = 'area_agent_center'; //区域代理中心
    const LinkType_ShoppingCart = 'shopping_cart'; // 购物车
    const LinkType_CloudStockCenter = 'cloudstock_center'; // 云仓工作台
    const LinkType_AuthCertQuery = 'authcert_query'; // 授权证书查询
	const LinkType_AuthCert = 'authcert'; // 我的授权证书
	const LinkType_DealerInvite = 'dealer_invite'; // 授权邀请
	const LinkType_DealerVerify = 'dealer_verify'; // 审核管理
//    const LinkType_ReceivingAddress = 'receiving_address'; // 收货地址
//    const LinkType_Coupon = 'coupon'; // 优惠券
//    const LinkType_Point = 'point'; // 积分
//    const LinkType_ProductCollection = 'product_collection'; // 产品收藏
//    const LinkType_BrowseRecords = 'browse_records'; // 浏览记录
    const LinkType_OrderList = 'order_list'; // 订单列表
    const LinkType_AfterSaleList = 'after_sale_list'; // 售后列表
    const LinkType_SecurityCode = 'security_code'; // 防伪查询
    const LinkType_CustomerService = 'customer_service'; // 客服
}