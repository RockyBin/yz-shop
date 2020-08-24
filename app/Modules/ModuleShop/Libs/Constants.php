<?php

namespace App\Modules\ModuleShop\Libs;

use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use Carbon\Carbon;
use PhpMyAdmin\SqlParser\Core;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Logger\Log;

/**
 * 分销商城的常量表
 * Class Constants
 * @package App\Modules\ModuleShop\Libs
 */
class Constants
{
    // 功能权限
    const FunctionPermission_BASE = 1;
    const FunctionPermission_ENABLE_DISTRIBUTION = 2; // 分销功能
    const FunctionPermission_ENABLE_AGENT = 3; // 代理功能
    const FunctionPermission_ENABLE_CLOUDSTOCK = 4; // 云仓功能
    const FunctionPermission_ENABLE_AUTHCERT = 5; // 经销商授权证书
    const FunctionPermission_ENABLE_DEALER_INVITE = 6; //经销商授权邀请
    const FunctionPermission_ENABLE_RECHARGE_BONUS = 7; //充值赠送功能
    const FunctionPermission_ENABLE_SECURITY_CODE = 8; // 防伪码功能
    const FunctionPermission_ENABLE_DEALER_HIDE_LEVEL = 9; // 经销商隐藏等级功能
    const FunctionPermission_INFINITE_STORE = 10; //无限店
    const FunctionPermission_ENABLE_LIVE = 11; //直播
    const FunctionPermission_ENABLE_WXAPP = 12; //微信小程序
    const FunctionPermission_ENABLE_WXWORK = 13; //企业微信
    const FunctionPermission_ENABLE_ADSCREEN = 14; //广告屏
    const FunctionPermission_ENABLE_GROUP_BUYING = 15; //拼团
    const FunctionPermission_ENABLE_CUSTOM_COPYRIGHT = 16; //自己定义版权设置(用户可在自行关闭或显示版权，更换LOGO等)
    const FunctionPermission_ENABLE_FORCE_HIDE_COPYRIGHT = 17; //强制隐藏版权（主要是云指过来的用户）
    const FunctionPermission_ENABLE_GRATITUDE_AWARD = 18; //感恩奖（合作定制）
    const FunctionPermission_ENABLE_AREA_AGENT = 19; // 区域代理功能
    const FunctionPermission_ENABLE_SUPPLIER = 20; // 供应商功能

    // 版本分类
    const License_FREE = 0; // 免费版
    const License_STANDARD = 1; // 标准版
    const License_DISTRIBUTION = 2; // 分销版(限500店)
    const License_AGENT_DISTRIBUTION = 3; // 3+3渠道版(代理分销)
    const License_GROUP = 4; // 直播分销版(分销+无限店)
    const License_MICRO_CLOUDSTOCK = 6; // 微商版 -- 基础+经销商云仓+附加功能
    const License_AGENT = 7; // 3级代理版 -- 基础+3级代理
    const License_SUPPLIER = 8; // 供应商版 -- 3级分销+3级代理+供应商

    // 优惠券发放记录状态
    const CouponStatus_Invalid = 0; // 失效
    const CouponStatus_Used = 1; // 已使用
    const CouponStatus_NoUse = 2; // 未使用
    const CouponStatus_Expiry = 3; // 已过期
    const CouponStatus_Locked = 4; // 已锁定(表示此优惠券已经分配给了某个订单)

    //优惠券状态
    const Coupon_Unactive = 0; // 禁用
    const Coupon_Active = 1; // 生效
    const Coupon_Expiry = 2; //已过期

    // 订单状态
    const OrderStatus_Deleted = -1; // 删除
    const OrderStatus_NoPay = 0; // 待付款
    const OrderStatus_OrderPay = 1; // 已付款，待发货
    const OrderStatus_OrderSend = 2; // 已发货，待收货
    const OrderStatus_OrderReceive = 3; // 已收货，可评价
    const OrderStatus_OrderSuccess = 4; // 交易成功(完成收货和评价)
    const OrderStatus_OrderFinished = 5; // 交易完成(过售后期进入此状态（除了6）)
    const OrderStatus_OrderClosed = 6; // 交易关闭(完全售后成功进入此状态)
    const OrderStatus_OrderRefund = 7; // 退款处理中
    const OrderStatus_Cancel = 8; // 订单取消

    // 订单取消原因
    const OrderCancelReason_TimeOut = 0; // 超时关闭
    const OrderCancelReason_NotLike = 1; // 不喜欢/不想要
    const OrderCancelReason_BuyError = 2; // 拍错了
    const OrderCancelReason_OtherBuyType = 3; // 有其他优惠购买方式

    // 云仓订单取消原因
    const CloudStock_OrderCancelReason_NotTake = 1; // 暂时不提货了
    const CloudStock_OrderCancelReason_AdressError = 2; // 地址填错了
    const CloudStock_OrderCancelReason_SellerNotEnought = 3; // 卖家缺货
    const CloudStock_OrderCancelReason_Other = 4; // 其他原因

    // 订单评论状态
    const OrderCommentStatus_CanComment = 0; // 可评论
    const OrderCommentStatus_AllComment = 1; // 已全部评论
    const OrderCommentStatus_ForbidComment = -1; // 禁止评论

    // 订单明细评论状态
    const OrderItemCommentStatus_NoComment = 0; // 未评论
    const OrderItemCommentStatus_HasComment = 1; // 已评论
    const OrderItemCommentStatus_ForbidComment = -1; // 禁止评论

    // 退款状态
    const RefundStatus_Apply = 0; // 申请中
    const RefundStatus_Agree = 1; // 同意
    const RefundStatus_Reject = 2; // 拒绝
    const RefundStatus_Shipped = 3; // 买家已发货
    const RefundStatus_Received = 4; // 卖家已收货
    const RefundStatus_Over = 5; // 完成
    const RefundStatus_Cancel = -1; // 取消 -1


    // 售后 是否收到货
    const AfterSale_ReceiveNo = 0; // 未收到货
    const AfterSale_ReceiveYes = 1; // 已收到货

    // 售后类型
    const AfterSaleType_Refund = 0; // 只退款
    const AfterSaleType_ReturnProduct = 1; // 退货退款

    // 售后原因
    const AfterSaleReason_ProductError = 0; // 发错货/少件/空包/包装破损
    const AfterSaleReason_ProductQuality = 1; // 商品质量问题
    const AfterSaleReason_ProductDescError = 2; // 实物与商品描述不符
    const AfterSaleReason_Consensus = 3; // 买卖双方协商一致
    const AfterSaleReason_NotLike = 4; // 不喜欢/不想要
    const AfterSaleReason_TimeoutDelivery = 5; // 未按约定时间发货
    const AfterSaleReason_NotDelivered = 6; // 快递/物流一直未送到
    const AfterSaleReason_Refusal = 7; // 货物破损已拒签
    const AfterSaleReason_QualityProblems = 8; // 产品质量存在问题
    const AfterSaleReason_EffectNotMatch = 9; // 效果与描述不符

    // 退款方式 现在只有一种 原路退回
    const AfterSaleRefundType_Original = 1;

    // 订单发货状态
    const OrderDeliveryStatus_Yes = 2; // 已全部发货
    const OrderDeliveryStatus_Part = 1; // 部分发货
    const OrderDeliveryStatus_No = 0; // 未发货

    // 订单中产品发货状态
    const OrderProductDeliveryStatus_Yes = 1; // 已发货
    const OrderProductDeliveryStatus_No = 0; // 未发货

    // 订单类型
    const OrderType_Normal = 0; // 普通订单
    const OrderType_GroupBuying = 1; // 拼团订单

    // 团购订单子状态
    const OrderType_GroupBuyingStatus_Yes = 101; //成团
    const OrderType_GroupBuyingStatus_No = 100; //未成团
    const OrderType_GroupBuyingStatus_Fail = 102; //拼团失败

    // 订单运费计算规则类型  目前只有一种
    const OrderFreightCal_Default = 1;
    const OrderFreightCal_Template = 2; // 按照规定某种模板进行计算

    // 订购商品类型，用于 IShopProduct 相关类
    const ShopProductType_Normal = 0; // 普通商品
    const ShopProductType_SecKill = 1; // 秒杀商品

    // 分销商审核方式
    const DistributionReviewType_Manual = 0; // 手动审核
    const DistributionReviewType_Auto = 1; // 自动审核

    // 分销商审核是否显示
    const DistributionReviewShow_Yes = 1; // 手动审核
    const DistributionReviewShow_No = 0; // 自动审核

    // 分销商绑定方式
    const DistributionBindType_Reg = 0; // 注册后
    const DistributionBindType_Buy = 1; // 首次付款

    // 成为分销商条件
    const DistributionCondition_Error = -1; // 错误类型
    const DistributionCondition_None = 0; // 无
    const DistributionCondition_Apply = 1; // 申请
    const DistributionCondition_BuyTimes = 2; // 消费笔数
    const DistributionCondition_BuyMoney = 3; // 消费金额
    const DistributionCondition_BuyProduct = 4; // 购买指定商品
    const DistributionCondition_DirectlyMember = 5;//直推人员

    // 分销商状态
    const DistributorStatus_WaitReview = 0; // 待审
    const DistributorStatus_Active = 1; // 生效
    const DistributorStatus_RejectReview = -1; // 审核不通过
    const DistributorStatus_DeActive = -2; // 取消资格
    const DistributorStatus_Null = -9; // 不存在

    // 分销商是否删除
    const DistributorIsDel_Yes = 1; // 已删除
    const DistributorIsDel_No = 0; // 正常

    // 分销商等级升级条件

    const DistributionLevelUpgradeCondition_TotalCommission = 1; // 佣金总收入
    const DistributionLevelUpgradeCondition_SelfDealTimes = 2; // 自购交易次数
    const DistributionLevelUpgradeCondition_SelfDealMoney = 3; // 自购交易金额
    const DistributionLevelUpgradeCondition_DirectlyUnderDealTimes = 4; // 一级分销团队交易次数
    const DistributionLevelUpgradeCondition_DirectlyUnderDealMoney = 5; // 一级分销团队交易金额
    const DistributionLevelUpgradeCondition_SubordinateDealTimes = 6; // 团队总交易次数
    const DistributionLevelUpgradeCondition_SubordinateDealMoney = 7; // 团队总交易金额
    const DistributionLevelUpgradeCondition_TotalTeam = 8; // 团队总数量
    const DistributionLevelUpgradeCondition_DirectlyUnderDistributor = 9; // 一级分销商数量
    const DistributionLevelUpgradeCondition_DirectlyUnderMember = 10; // 一级会员数量
    const DistributionLevelUpgradeCondition_SubordinateDistributor = 11; // 分销商总数量
    const DistributionLevelUpgradeCondition_SubordinateMember = 12; // 会员总数量
    const DistributionLevelUpgradeCondition_TeamBuyProduct = 13; // 团队指定商品
    const DistributionLevelUpgradeCondition_SelfBuyProduct = 14; // 自购指定商品
    const DistributionLevelUpgradeCondition_DirectlyBuyProduct = 15; // 直推指定商品
    const DistributionLevelUpgradeCondition_IndirectBuyProduct = 16; // 间推指定商品
    const DistributionLevelUpgradeCondition_DirectlyAllUnderMember = 17; // 直推成员人数
    const DistributionLevelUpgradeCondition_IndirectAllUnderMember = 18; // 间推成员人数
    const DistributionLevelUpgradeCondition_IndirectUnderMember = 19; // 间推会员人数
    const DistributionLevelUpgradeCondition_IndirectUnderDistributor = 20; // 间推分销商合计数量
    const DistributionLevelUpgradeCondition_IndirectDealTimes = 21; // 间推订单笔数
    const DistributionLevelUpgradeCondition_IndirectDealMoney = 22; // 间推订单金额
    const DistributionLevelUpgradeCondition_TotalChargeMoney = 23; // 累计充值金额
    const DistributionLevelUpgradeCondition_OnceChargeMoney = 24; // 一次性充值金额

    // 代理商等级升级条件
    const AgentLevelUpgradeCondition_SelfAgentLevel = 1;//自身代理等级
    const AgentLevelUpgradeCondition_SelfDistributionLevel = 2;//自身分销商等级
    const AgentLevelUpgradeCondition_AgentTeamMember = 3;//团队成员
    const AgentLevelUpgradeCondition_RecommendThreeLevelAgentNum = 4;//直推三级代理人数
    const AgentLevelUpgradeCondition_DirectlyDistributionMember = 5;//直推分销商人数
    const AgentLevelUpgradeCondition_IndirectDistributionMember = 6;//间推分销商人数
    const AgentLevelUpgradeCondition_DirectlyMember = 7;//直推成员人数
    const AgentLevelUpgradeCondition_IndirectMember = 8;//间推成员人数
    const AgentLevelUpgradeCondition_TeamArbitrarilyLevelDistributionMember = 9;//团队中任意等级分销商合计数量
    const AgentLevelUpgradeCondition_SelfOrderMoney = 10;//自购订单金额
    const AgentLevelUpgradeCondition_DirectlyOrderMoney = 11;//直推订单金额
    const AgentLevelUpgradeCondition_IndirectOrderMoney = 12;//间推订单金额
    const AgentLevelUpgradeCondition_TeamOrderMoney = 13;//团队订单金额
    const AgentLevelUpgradeCondition_SelfBuyDesignatedProduct = 14;//自购指定商品
    const AgentLevelUpgradeCondition_DirectlyBuyDesignatedProduct = 15;//直推指定商品
    const AgentLevelUpgradeCondition_IndirectBuyDesignatedProduct = 16;//间推指定商品
    const AgentLevelUpgradeCondition_TeamBuyDesignatedProduct = 17;//团队指定商品
    const AgentLevelUpgradeCondition_RecommendOneLevelAgentNum = 18;// 直推一级代理人数
    const AgentLevelUpgradeCondition_RecommendTwoLevelAgentNum = 19;// 直推二级代理人数
    const AgentLevelUpgradeCondition_SelfBuyAllDesignatedProduct = 20;//自购所有指定商品
    const AgentLevelUpgradeCondition_TotalChargeMoney = 21; // 累计充值金额
    const AgentLevelUpgradeCondition_OnceChargeMoney = 22; // 一次性充值金额

    // 经销商等级升级条件
    const DealerLevelUpgradeCondition_TeamDealerNum = 1; // 团队经销商人数
    const DealerLevelUpgradeCondition_DirectlyDealerNum = 2; // 直推经销商人数
    const DealerLevelUpgradeCondition_IndirectDealerNum = 3; // 间推经销商人数
    const DealerLevelUpgradeCondition_SelfBuyMoney = 4; // 自购云仓订单金额
    const DealerLevelUpgradeCondition_SelfBuyProduct = 5; // 自购云仓商品数量
    const DealerLevelUpgradeCondition_OneReChargeMoney = 6; // 一次性充值金额
    const DealerLevelUpgradeCondition_TotalReChargeMoney = 7; // 总充值金额

    //统计数据类型
    const  Statistics_member_tradeMoney = 1; //总交易金额
    const  Statistics_member_tradeTime = 2;   //总交易次数
    const  Statistics_MemberCloudStockPerformancePaid = 3;   //云仓进货总业绩(付款后)
    const  Statistics_MemberCloudStockPerformancePaidMonth = 3001; //云仓进货月业绩(付款后)
    const  Statistics_MemberCloudStockPerformancePaidQuarter = 3002; //云仓进货季度业绩(付款后)
    const  Statistics_MemberCloudStockPerformancePaidYear = 3003; //云仓进货年业绩(付款后)
    const  Statistics_MemberCloudStockPerformanceFinished = 4;   //云仓进货总业绩(订单完成后)
    const  Statistics_MemberCloudStockPerformanceFinishedMonth = 4001; //云仓进货月业绩(订单完成后)
    const  Statistics_MemberCloudStockPerformanceFinishedQuarter = 4002; //云仓进货季度业绩(订单完成后)
    const  Statistics_MemberCloudStockPerformanceFinishedYear = 4003; //云仓进货年业绩(订单完成后)

    // 会员状态
    const MemberStatus_Active = 1; // 活跃的
    const MemberStatus_UnActive = 0; // 封号的

    // 会员等级升级方式
    const MemberLevelUpgradeCondition_OrderMoney = 0; // 累计消费金额

    // 会员等级是否对新会员开放
    const MemberLevelForNew_Yes = 1; // 开放
    const MemberLevelForNew_No = 0; // 不开放

    // 会员升级条件
    const MemberLevelUpgradeCondition_TotalOrderMoney = 0; // 累计消费金额
    const MemberLevelUpgradeCondition_OneReChargeMoney = 1; // 一次性充值金额
    const MemberLevelUpgradeCondition_TotalReChargeMoney = 2; // 累计充值金额

    // 通用状态（生效或不生效）
    const CommonStatus_Unactive = 0;
    const CommonStatus_Active = 1;

    // 快递公司编号
    const ExpressCompanyCode_Other = 0; // 其他
    const ExpressCompanyCode_ZhongTong = 1; // 中通
    const ExpressCompanyCode_YuanTong = 2; // 圆通
    const ExpressCompanyCode_YunDa = 3; // 韵达
    const ExpressCompanyCode_PingYou = 4; // 中国邮政
    const ExpressCompanyCode_HuiTong = 5; // 百世汇通
    const ExpressCompanyCode_ShunFeng = 6; // 顺丰
    const ExpressCompanyCode_YouSu = 7; // 优速
    const ExpressCompanyCode_TianTian = 8; // 天天
    const ExpressCompanyCode_DeBang = 9; // 德邦
    const ExpressCompanyCode_GuoTong = 10; // 国通
    const ExpressCompanyCode_EMS = 11; // EMS
    const ExpressCompanyCode_WanXiang = 12; // 万象
    const ExpressCompanyCode_JT = 13; // 极兔速递

    // 时期
    const Period_Error = -1; // 错误
    const Period_OrderPay = 0; // 付费后
    const Period_OrderFinish = 1; // 维权期后

    // 产品分销 积分 会员价规则表
    const ProductPriceRuleType_Distribution = 0; // 分销规则
    const ProductPriceRuleType_MemberLevel = 1; // 会员等级规则
    const ProductPriceRuleType_Point = 2; // 积分规则
    const ProductPriceRuleType_AgentOrderCommision = 3; // 代理订单分红规则
    const ProductPriceRuleType_AgentSaleReward = 4; // 代理销售奖规则
    const ProductPriceRuleType_CloudStock = 5; // 云仓规则
    const ProductPriceRuleType_DealerSaleReward = 6; // 经销商销售奖规则
    const ProductPriceRuleType_AreaAgent = 7; // 区域代理返佣规则

    // 手机端页面类型
    const PageMobiType_Home = 1;    // 首页
    const PageMobiType_MemberCenter = 2; // 会员中心
    const PageMobiType_Custom = 99; // 自定义页面

    // 分享海报类型
    const SharePaperType_Home = 1; // 店铺海报，连接到店铺首页的
    const SharePaperType_MemberCenter = 0; // 海报显示的位置，会员中心
    const SharePaperType_DistributorCenter = 1; // 海报显示的位置，分销中心
    const SharePaperType_AgentCenter = 2; // 海报显示的位置，代理中心
    const SharePaperType_DealerCenter = 3; // 海报显示的位置，经销商中心
    const SharePaperType_StaffCenter = 4; // 海报显示的位置，员工
    const SharePaperType_AreaAgentCenter = 5; // 海报显示的位置，区域代理

    // 评论类型
    const ProductCommentType_Comment = 0; // 评论、追评
    const ProductCommentType_Reply = 1; // 用户回复（预留，不是商家回复）

    // 评论状态
    const ProductCommentStatus_WaitCheck = 0; // 待审核
    const ProductCommentStatus_Active = 1; // 审核通过
    const ProductCommentStatus_Inactive = -1; // 审核不通过

    // 评论是否删除
    const ProductCommentIsDel_No = 0; // 否
    const ProductCommentIsDel_Yes = 1; // 是

    // 评论是否匿名
    const ProductCommentIsAnonymous_No = 0; // 否
    const ProductCommentIsAnonymous_Yes = 1; // 是

    // 数据统计类型
    const StatisticsType_AgentMonthAchievement = 1; //团队代理月度业务业绩统计
    const StatisticsType_AgentQuarterAchievement = 2; //团队代理季度业务业绩统计
    const StatisticsType_AgentYearAchievement = 3; //团队代理年度业务业绩统计

    //团队代理的申请/升级条件
    const AgentCondition_TotalTeam = 1; //团队总人数
    const AgentCondition_TotalAchievement = 2; //团队总业绩

    // 代理申请状态
    const AgentApplyStatus_Open = 1; // 开启申请
    const AgentApplyStatus_Close = 0; // 关闭申请

    // 代理状态
    const AgentStatus_WaitReview = 0; // 等待审核
    const AgentStatus_Active = 1; // 生效中
    const AgentStatus_RejectReview = -1; // 拒绝申请
    const AgentStatus_Cancel = -2; // 取消资格
    const AgentStatus_Applying = -3; //申请进行中，未完成申请（如未支付等情况）

    // 团队奖励通用状态
    const AgentRewardStatus_Freeze = 0; // 冻结
    const AgentRewardStatus_Active = 1; // 正常生效
    const AgentRewardStatus_Invalid = -1; // 作废

    // 经销商申请状态
    const DealerApplyStatus_Open = 1; // 开启申请
    const DealerApplyStatus_Close = 0; // 关闭申请

    // 经销商状态
    const DealerStatus_WaitReview = 0; // 等待审核
    const DealerStatus_Active = 1; // 生效中
    const DealerStatus_RejectReview = -1; // 拒绝申请
    const DealerStatus_Cancel = -2; // 取消资格
    const DealerStatus_Applying = -3; //申请进行中，未完成申请（如未支付等情况）

    // 经销商奖励通用状态
    const DealerRewardStatus_Freeze = 0; // 冻结
    const DealerRewardStatus_Invalid = -1; // 作废
    const DealerRewardStatus_WaitExchange = 0; // 待兑换
    const DealerRewardStatus_WaitReview = 1; // 待审核
    const DealerRewardStatus_Active = 2; // 已发放
    const DealerRewardStatus_RejectReview = 3; // 拒绝

    // 订单会员关系类型
    const OrderMembersHistoryType_Member = 0; // 推荐关系
    const OrderMembersHistoryType_Agent = 1; // 代理关系
    const OrderMembersHistoryType_Dealer = 2; // 经销商关系

    // 业绩统计计算方式
    const AgentPerformanceCountType_Month = 0; // 按月度
    const AgentPerformanceCountType_Season = 1; // 按季度
    const AgentPerformanceCountType_Year = 2; // 按年度

    // 订单是否分红状态
    const OrderAgentOrderCommissionStatus_No = 0; // 没有分红
    const OrderAgentOrderCommissionStatus_YesButFreeze = 1; // 有分红（未结算）
    const OrderAgentOrderCommissionStatus_Yes = 2; // 有分红（结算成功）
    const OrderAgentOrderCommissionStatus_YesButInvalid = 3; // 有分红（结算失败）

    // 订单是否区域分红状态
    const OrderAreaAgentOrderCommissionStatus_No = 0; // 没有分红
    const OrderAreaAgentOrderCommissionStatus_YesButFreeze = 1; // 有分红（未结算）
    const OrderAreaAgentOrderCommissionStatus_Yes = 2; // 有分红（结算成功）
    const OrderAreaAgentOrderCommissionStatus_YesButInvalid = 3; // 有分红（结算失败）

    // 云仓订单类型
    const CloudStockOrderType_Unknow = 0; // 未知类型
    const CloudStockOrderType_Retail = 1; // 零售订单
    const CloudStockOrderType_Purchase = 2; // 代理的进货单
    const CloudStockOrderType_TakeDelivery = 3; // 代理的提货单
    const CloudStockOrderType_RefundTakeDelivery = 4; // 代理的提货单（退库存）
    const CloudStockOrderType_Manual = 5; // 后台手动添加


    // 云仓进货单状态
    const CloudStockPurchaseOrderStatus_NoPay = 0; // 未支付
    const CloudStockPurchaseOrderStatus_Pay = 1;    // 已支付 待审核
    const CloudStockPurchaseOrderStatus_Reviewed = 2;  // 已审核 待配仓
    const CloudStockPurchaseOrderStatus_Finished = 3;   // 完成
    const CloudStockPurchaseOrderStatus_Cancel = 4;     // 取消
    // 云仓进货单支付审核状态
    const CloudStockPurchaseOrderPaymentStatus_No = 0;     // 未审核
    const CloudStockPurchaseOrderPaymentStatus_Yes = 1;     // 审核通过
    const CloudStockPurchaseOrderPaymentStatus_Refuse = 2;     // 审核不通过


    // 云仓进货订单商品配仓状态
    const CloudStockPurchaseOrderItemStatus_No = 0;     // 未配仓
    const CloudStockPurchaseOrderItemStatus_Yes = 1;     // 已配仓

    // 云仓提货订单状态
    const CloudStockTakeDeliveryOrderStatus_NoDeliver = 0; // 已付款 待发货
    const CloudStockTakeDeliveryOrderStatus_Delivered = 1; // 待收货
    const CloudStockTakeDeliveryOrderStatus_Finished = 2; // 已完成
    const CloudStockTakeDeliveryOrderStatus_Cancel = 3; // 已关闭
    const CloudStockTakeDeliveryOrderStatus_Nopay = 4; // 未付款

    // 云仓提货订单发货状态
    const CloudStockTakeDeliveryOrderDeliverStatus_No = 0; // 未发货
    const CloudStockTakeDeliveryOrderDeliverStatus_PartYes = 1; // 部分发货
    const CloudStockTakeDeliveryOrderDeliverStatus_AllYes = 2; // 全部发货

    // 云仓提货订单商品发货状态
    const CloudStockTakeDeliveryOrderItemDeliveryStatus_No = 0; // 未发货
    const CloudStockTakeDeliveryOrderItemDeliveryStatus_Yes = 1; // 已发货

    // 云仓进仓类型
    const CloudStockInType_Unknow = 0; // 未知类型
    const CloudStockInType_Purchase = 1; // 进货
    const CloudStockInType_FirstGift = 2; // 首次开仓赠送
    const CloudStockInType_Return = 3; // 退货入仓
    const CloudStockInType_TakeDelivery_Return = 4; // 提货退货入仓
    const CloudStockInType_Manual = 9; // 后台手工操作

    // 云仓出仓类型
    const CloudStockOutType_Unknow = 0; // 未知类型
    const CloudStockOutType_Sale = 1; // 零售出仓
    const CloudStockOutType_SubSale = 2; // 下级代理进货出仓
    const CloudStockOutType_Take = 3; // 提货出仓
    const CloudStockOutType_Manual = 9; // 后台手工操作

    // 发货物流类型
    const LogisticsType_Normal = 0;     // 普通订单
    const LogisticsType_CloudStockTake = 1; // 云仓提货订单
    // 支付设置类型
    const PayConfigType_WxPay = 1; // 微信支付配置
    const PayConfigType_AliPay = 2; // 支付宝支付配置
    const PayConfigType_BankPay = 3; //银行卡支付设置
    const PayConfigType_OfflinePay = 4; // 所有线下支付设置
    const PayConfigType_TongLian = 5; // 通联支付配置

    // 积分转赠对象
    const PointConfig_GiveTarget_Everyone = 1; //任何对象
    const PointConfig_GiveTarget_SubMember = 2; //仅限下级会员
    const PointConfig_GiveTarget_directlyMember = 3; //仅限直属下级会员

    // 全局变量名定义
    const GlobalsKey_PointAtAdmin = 'point_at_admin'; // 在后台产生积分


    //操作日志类型
    const  OpLogType_DistributorUpperChange = 1;// 1:分销上下级改变
    const  OpLogType_DistributorLevelChange = 2;// 2:分销等级改变
    const  OpLogType_AgentUpperChange = 3;// 3:代理上下级更改
    const  OpLogType_AgentLevelChange = 4;// 4:代理等级更改
    const  OpLogType_MemberLevelChange = 5;// 5:会员等级更改
    const  OpLogType_DealerLevelChange = 6;// 6:经销商等级更改
	const  OpLogType_MemberMerge = 7;// 7:会员合并更改
    const  OpLogType_OrderMoneyChange = 8;// 8:后台手工更改订单金额
    const  OpLogType_OrderFreightChange = 9;// 9:后台手工更改订单运费
    const  OpLogType_OrderAddressChange = 10;// 10:后台手工更改订单地址

    //审核日志的审核类型
    const  VerifyLogType_DealerVerify = 1;// 经销商审核
    const  VerifyLogType_CloudStockPurchaseOrderFinanceVerify = 2;// 云仓进货财务审核
    const  VerifyLogType_BalanceVerify = 3;// 余额审核
    const  VerifyLogType_DealerPerformanceReward = 4;// 经销商业绩奖审核
    const  VerifyLogType_DealerRecommendReward = 5;// 经销商推荐奖审核
    const  VerifyLogType_DealerSaleReward = 6;// 经销商销售奖审核
    const  VerifyLogType_DealerOrderReward = 7;// 经销商订单返现奖审核

    // 经销商等级状态
    const DealerLevelStatus_Active = 1; // 生效
    const DealerLevelStatus_Unactive = 0; // 禁用

    // 经销商奖金类型
    const DealerRewardType_Performance = 1;
    const DealerRewardType_Recommend = 2;
    const DealerRewardType_Sale = 3;
    const DealerRewardType_Order = 4;

    // 经销商业绩奖周期
    const DealerPerformanceRewardPeriod_Month = 0; // 月
    const DealerPerformanceRewardPeriod_Quarter = 1; // 季度
    const DealerPerformanceRewardPeriod_Year = 2; // 年

    //等级类型（用于会员列表）
    const LevelType_Member = 1; // 会员等级
    const LevelType_Distributor = 2; // 分销商等级
    const LevelType_Agent = 3; // 代理等级
    const LevelType_Dealer = 4; // 经销商等级
    const LevelType_AreaAgent = 5; // 区代等级
    const LevelType_Supplier = 6; // 供应商等级

    // 代理其他奖
    const AgentOtherRewardType_Grateful = 1; // 感恩奖

    // 供应商结算状态
    const SupplierSettleStatus_NoActive = 0; //未结算
    const SupplierSettleStatus_Active = 1; //结算成功
    const SupplierSettleStatus_Fail = 2; //结算失败

    /**
     * 获取所有的产品版本列表
     * @return array
     * @throws \ReflectionException
     */
    public static function getLicenseVersions()
    {
        $rc = new \ReflectionClass(static::class);
        $const = $rc->getConstants();
        $ret = [];
        foreach ($const as $varname => $val) {
            if (preg_match("/^License_(\w+)/", $varname, $m)) {
                $ret[] = [$varname => $val];
            }
        }
        return $ret;
    }

    /**
     * 获取所有的版本权限信息
     * @return array
     * @throws \ReflectionException
     */
    public static function getFunctionPermissions()
    {
        $rc = new \ReflectionClass(static::class);
        $const = $rc->getConstants();
        $ret = [];
        foreach ($const as $varname => $val) {
            if (preg_match("/^FunctionPermission_(\w+)/", $varname, $m)) {
                $ret[$varname] = $val;
            }
        }
        return $ret;
    }

    /**
     * 返回订单的文本表示形式
     * @param int $orderStatus
     * @param int $orderTypeStatus
     * @return string
     */
    public static function getOrderStatusText(int $orderStatus, int $orderTypeStatus = 0)
    {
        if ($orderStatus == static::OrderStatus_NoPay) return '待付款';
        else if ($orderStatus == static::OrderStatus_OrderPay) {
            // 拼团的未成团状态
            if ($orderTypeStatus == 100) {
                return "已付款";
            }
            return '待发货';
        } else if ($orderStatus == static::OrderStatus_OrderSend) return '待收货';
        else if ($orderStatus == static::OrderStatus_OrderReceive) return '待评价';
        else if ($orderStatus == static::OrderStatus_OrderSuccess) return '交易成功';
        else if ($orderStatus == static::OrderStatus_OrderFinished) return '交易完成';
        else if ($orderStatus == static::OrderStatus_OrderClosed) return '已关闭';
        else if ($orderStatus == static::OrderStatus_OrderRefund) return '售后中';
        else if ($orderStatus == static::OrderStatus_Cancel) return '已取消';
        else if ($orderStatus == static::OrderStatus_Deleted) return '已删除';
        else return '未知';
    }

    public static function getSupplierSettleStatusText($status){
        if ($status == static::SupplierSettleStatus_NoActive) return '待结算';
        else if ($status == static::SupplierSettleStatus_Active) return '已结算';
        else if ($status == static::SupplierSettleStatus_Fail) return '结算失败';
        else return '未知状态';
    }

    /**
     * 返回订单的文本表示形式（后台用的）
     * @param int $orderStatus
     * @return string
     */
    public static function getOrderStatusTextForAdmin(int $orderStatus, int $orderTypeStatus = 0)
    {
        if ($orderStatus == static::OrderStatus_NoPay) return '待付款';
        else if ($orderStatus == static::OrderStatus_OrderPay) {
            if ($orderTypeStatus == 0 || $orderTypeStatus == 101) return '待发货';
            elseif ($orderTypeStatus == 100) return '待成团';
//            elseif ($orderTypeStatus == 101) return '拼团成功';
            elseif ($orderTypeStatus == 102) return '拼团失败';
        } else if ($orderStatus == static::OrderStatus_OrderSend) return '待收货';
        else if ($orderStatus == static::OrderStatus_OrderReceive) return '待评价';
        else if ($orderStatus == static::OrderStatus_OrderSuccess) return '已完成';
        else if ($orderStatus == static::OrderStatus_OrderFinished) return '已完成';
        else if ($orderStatus == static::OrderStatus_OrderClosed) return '交易关闭';
        else if ($orderStatus == static::OrderStatus_OrderRefund) return '售后中';
        else if ($orderStatus == static::OrderStatus_Cancel) return '订单取消';
        else if ($orderStatus == static::OrderStatus_Deleted) return '已删除';
        else return '';
    }

    /**
     * 获取售后原因文本
     * @param int $reason
     * @return string
     */
    public static function getAfterSaleReasonText(int $reason)
    {
        switch ($reason) {
            case self::AfterSaleReason_ProductError:
                return "发错货/少件/空包/包装破损";
            case self::AfterSaleReason_ProductQuality:
                return "商品质量问题";
            case self::AfterSaleReason_ProductDescError:
                return "实物与商品描述不符";
            case self::AfterSaleReason_Consensus:
                return "买卖双方协商一致";
            case self::AfterSaleReason_NotLike:
                return "不喜欢/不想要";
            case self::AfterSaleReason_TimeoutDelivery:
                return "未按约定时间发货";
            case self::AfterSaleReason_NotDelivered:
                return "快递/物流一直未送到";
            case self::AfterSaleReason_Refusal:
                return "货物破损已拒签";
            case self::AfterSaleReason_QualityProblems:
                return "产品质量存在问题";
            case self::AfterSaleReason_EffectNotMatch:
                return "效果与描述不符";
            default:
                return "未知原因";
        }
    }

    /**
     * 获取售后收货状态文本
     * @param int $receive
     * @return string
     */
    public static function getAfterSaleReceiveText(int $receive)
    {
        switch ($receive) {
            case self::AfterSale_ReceiveNo:
                return '未收到货';
            case self::AfterSale_ReceiveYes:
                return '已收到货';
            default:
                return '未知';
        }
    }

    /**
     * 获取售后类型文本
     * @param int $type
     * @return string
     */
    public static function getAfterSaleTypeText(int $type)
    {
        switch ($type) {
            case self::AfterSaleType_Refund:
                return '只退款';
            case self::AfterSaleType_ReturnProduct:
                return '退货退款';
            default:
                return '未知';
        }
    }

    /**
     * 获取售后退款方式文本
     * @param int $type
     * @return string
     */
    public static function getAfterSaleRefundTypeText(int $type)
    {
        switch ($type) {
            case self::AfterSaleRefundType_Original:
                return '原路退回';
            default:
                return '未知';
        }
    }

    /**
     * 订单取消原因文本
     * @param int $reason
     * @return string
     */
    public static function getOrderCancelReasonText(int $reason)
    {
        switch ($reason) {
            case self::OrderCancelReason_NotLike:
                return '不喜欢/不想要';
            case self::OrderCancelReason_BuyError:
                return '拍错了';
            case self::OrderCancelReason_OtherBuyType:
                return '有其他优惠购买方式';
            default:
                return '未知';
        }
    }

    /**
     * 云仓订单取消原因文本
     * @param int $reason
     * @return string
     */
    public static function getCloudStockOrderCancelReasonText(int $reason)
    {
        switch ($reason) {
            case self::CloudStock_OrderCancelReason_NotTake:
                return '暂时不提货了';
            case self::CloudStock_OrderCancelReason_AdressError:
                return '地址填错了';
            case self::CloudStock_OrderCancelReason_SellerNotEnought:
                return '卖家缺货';
            case self::CloudStock_OrderCancelReason_Other:
                return '其他原因';
            default:
                return '未知';
        }
    }

    /**
     * 获取快递名称
     * @param int|null $code code为空则返回所有列表
     * @return array|string
     */
    public static function getExpressCompanyText(int $code = null)
    {
        $codeText = [
            self::ExpressCompanyCode_Other => '其他',
            self::ExpressCompanyCode_ZhongTong => '中通快递',
            self::ExpressCompanyCode_YuanTong => '圆通快递',
            self::ExpressCompanyCode_YunDa => '韵达快递',
            self::ExpressCompanyCode_PingYou => '中国邮政',
            self::ExpressCompanyCode_HuiTong => '百世汇通',
            self::ExpressCompanyCode_ShunFeng => '顺丰快递',
            self::ExpressCompanyCode_YouSu => '优速快递',
            self::ExpressCompanyCode_TianTian => '天天快递',
            self::ExpressCompanyCode_DeBang => '德邦物流',
            self::ExpressCompanyCode_GuoTong => '国通快递',
            self::ExpressCompanyCode_EMS => 'EMS',
            self::ExpressCompanyCode_WanXiang => '万象快递',
            self::ExpressCompanyCode_JT => '极兔速递'
        ];
        if ($code === null) {
            return $codeText;
        } else {
            return $codeText[$code] ?: '未知';
        }
    }

    /**
     * 获取售后状态文案
     * @param int|null $status 为空则返回所有
     * @return array|string
     */
    public static function getAfterSaleStatusText(int $status = null)
    {
        $statusText = [
            self::RefundStatus_Apply => '申请中',
            self::RefundStatus_Agree => '同意申请',
            self::RefundStatus_Reject => '拒绝申请',
            self::RefundStatus_Shipped => '买家已发货',
            self::RefundStatus_Received => '卖家已收货',
            self::RefundStatus_Over => '售后完成',
            self::RefundStatus_Cancel => '撤销',
        ];
        if ($status === null) {
            return $statusText;
        } else {
            return $statusText[$status] ? $statusText[$status] : '未知';
        }
    }

    /**
     * 获取RP中需要的售后状态文案（前台列表 后台列表 后台详情需要）
     * @param int|null $status 为空则返回所有
     * @return array|string
     */
    public static function getFrontAfterSaleStatusText(int $status = null)
    {
        $statusText = [
            1 => '申请退款',
            2 => '申请退货',
            3 => '等待买家退货',
            4 => '等待卖家收货',
            5 => '退款成功',
            6 => '已收货待退款',
            7 => '审核不通过',
            8 => '退款关闭',
        ];
        if ($status === null) {
            return $statusText;
        } else {
            return $statusText[$status] ?: '未知';
        }
    }

    /**
     * 获取RP中需要的售后状态文案（前台详情需要）
     * @param int|null $status 为空则返回所有
     * @return array|string
     */
    public static function getDetailAfterSaleStatusText(int $status = null, $refuse_msg = '')
    {
        $statusText = [
            1 => ['等待卖家处理中', '您已成功发起退款申请，请耐心等待'],
            2 => ['等待卖家处理中', '您已成功发起退货申请，请耐心等待'],
            3 => ['卖家同意退货', '请及时寄回商品'],
            4 => ['等待卖家收货', '请耐心等待卖家收货'],
            5 => ['退款成功', '退款成功，请注意查收'],
            6 => ['已收货待退款', '卖家已收货，请耐心等待'],
            7 => ['拒绝退款', '拒绝原因：' . $refuse_msg ? $refuse_msg : '无'],
            8 => ['退款关闭', '撤销售后申请，退款关闭，交易恢复正常'],
        ];
        if ($status === null) {
            return $statusText;
        } else {
            return $statusText[$status] ?: '未知';
        }
    }

    /**
     * 代理等级中文名称（前台用）
     * @param $agentLevel
     * @param string $defaultLevelText
     * @return string
     */
    public static function getAgentLevelTextForFront($agentLevel, $defaultLevelText = '')
    {
        $agentLevel = intval($agentLevel);
        if ($agentLevel == 1) return trans('shop-front.diy_word.team_agent_level_1');
        else if ($agentLevel == 2) return trans('shop-front.diy_word.team_agent_level_2');
        else if ($agentLevel == 3) return trans('shop-front.diy_word.team_agent_level_3');
        else {
            if ($defaultLevelText) return $defaultLevelText;
            else {
                $chinese = [
                    '1' => '一',
                    '2' => '二',
                    '3' => '三',
                    '4' => '四',
                    '5' => '五',
                    '6' => '六',
                    '7' => '七',
                    '8' => '八',
                    '9' => '九',
                    '10' => '十',
                ];
                return ($chinese[$agentLevel] ? $chinese[$agentLevel] : $agentLevel) . '级代理';
            }
        }
    }

    /**
     * 云仓等级中文名称（前台用）
     * @param $cloudStockLevel
     * @param string $defaultLevelText
     * @return string
     */
    public static function getCloudStockLevelTextForFront($cloudStockLevel, $defaultLevelText = '')
    {
        $cloudStockLevel = intval($cloudStockLevel);
        if ($cloudStockLevel == 1) return trans('shop-front.diy_word.team_cloudstock_level_1');
        else if ($cloudStockLevel == 2) return trans('shop-front.diy_word.team_cloudstock_level_2');
        else if ($cloudStockLevel == 3) return trans('shop-front.diy_word.team_cloudstock_level_3');
        else return $defaultLevelText;
    }

    /**
     * 代理等级中文名称（后台用）
     * @param $agentLevel
     * @param string $defaultLevelText
     * @return string
     */
    public static function getAgentLevelTextForAdmin($agentLevel, $defaultLevelText = '')
    {
        $agentLevel = intval($agentLevel);
        if ($agentLevel == 1) return '一级代理';
        else if ($agentLevel == 2) return '二级代理';
        else if ($agentLevel == 3) return '三级代理';
        else return $defaultLevelText;
    }

    /**
     * 获取付款后的订单状态
     * @return array
     */
    public static function getPaymentOrderStatus()
    {
        return [
            self::OrderStatus_OrderPay,
            self::OrderStatus_OrderSend,
            self::OrderStatus_OrderReceive,
            self::OrderStatus_OrderSuccess,
            self::OrderStatus_OrderFinished,
            self::OrderStatus_OrderClosed,
            self::OrderStatus_OrderRefund
        ];
    }

    /**
     * 获取订单付款 并且没有售后的状态
     * @return array
     */
    public static function getOrderSuccessPaymentStatus()
    {
        return [
            self::OrderStatus_OrderPay,
            self::OrderStatus_OrderSend,
            self::OrderStatus_OrderReceive,
            self::OrderStatus_OrderSuccess,
            self::OrderStatus_OrderFinished
        ];
    }

    /**
     * 获取云仓进货订单log文案
     * @param int $status 步骤状态
     * @param string $text 需要拼接的文案
     * @param string $reason 取消时的原因
     * @param int $$order_pay_type 订单付款方式
     * @return string
     */
    public static function getCloudStockOrderLogText(int $status, $text = '', $reason = '', $order_pay_type = null)
    {
        //云仓进货使用线上支付，自动审核
        switch ($status) {
            case 0:
                return '["待买家完成付款..."]';
            case 1:
                return '["' . Carbon::now() . '  财务审核不通过", "待买家重新付款..."]';
            case 2:
                return '["待财务审核..."]';
            case 3:
                $text = json_decode($text, true);
                $text = $text && count($text) ? $text[0] : Carbon::now() . '  财务审核不通过';
                return '["' . $text . '", "待重新财务审核..."]';
            case 4:
                return '["' . Carbon::now() . '  财务审核通过", "待仓库配仓中..."]';
            case 5:
                $text = json_decode($text, true);
                $text = $text && count($text) ? $text[0] : Carbon::now() . '  财务审核通过';
                return '["' . $text . '", "' . Carbon::now() . '  完成仓库配仓"]';
            case 6:
                // 此时应该传入原因
                return '["' . Carbon::now() . '  订单取消（原因：' . $reason . '）"]';
            case 7:
                $text = json_decode($text, true);
                $text = $text && count($text) ? $text[0] : Carbon::now() . '  财务审核不通过';
                return '["' . $text . '", "' . Carbon::now() . '  订单取消（原因：' . $reason . '）"]';
        }
    }

    public static function getCloudStockLogText($inType, $outType)
    {
        switch (true) {
            case $inType == Constants::CloudStockInType_Purchase:
                return '进货增加库存';
                break;
            case $inType == Constants::CloudStockInType_FirstGift:
                return '首次开通云仓赠送';
                break;
            case $inType == Constants::CloudStockInType_Return:
                return '取消零售订单返还库存';
                break;
            case $inType == Constants::CloudStockInType_Manual:
                return '后台手工操作';
                break;
            case $outType == Constants::CloudStockOutType_Sale:
                return '零售订单扣减库存';
                break;
            case $outType == Constants::CloudStockOutType_SubSale:
                return '下级代理进货扣减库存';
                break;
            case $outType == Constants::CloudStockOutType_Take:
                return '提货扣减库存';
                break;
            case $outType == Constants::CloudStockOutType_Manual:
                return '后台手工操作';
                break;
        }
        return '未知操作类型';
    }

    /**
     * 获取云仓进货订单已支付状态列表
     * @return array
     */
    public static function getCloudStockPurchaseOrderPayStatus()
    {
        return [
            self::CloudStockPurchaseOrderStatus_Reviewed,
            self::CloudStockPurchaseOrderStatus_Finished
        ];
    }

    /**
     * 获取代理等级名字
     * @return array
     */
    public static function getAgentLevelList()
    {
        $data = [];
        $agentSetting = AgentBaseSetting::getCurrentSiteSetting();
        $agentLevel = $agentSetting->level;
        // id就是等级
        if ($agentLevel == 3) $data = [['id' => 1, 'name' => '一级代理'], ['id' => 2, 'name' => '二级代理'], ['id' => 3, 'name' => '三级代理']];
        if ($agentLevel == 2) $data = [['id' => 1, 'name' => '一级代理'], ['id' => 2, 'name' => '二级代理']];
        if ($agentLevel == 1) $data = [['id' => 1, 'name' => '一级代理']];
        return $data;
    }
}