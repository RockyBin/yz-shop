<?php

namespace YZ\Core;

class Constants
{
    // 推荐/分销的最大层级
    const MaxInviteLevel = 10;

    // 终端/浏览器类型
    const TerminalType_Unknown = 0; // 未知
    const TerminalType_PC = 1; // 电脑端
    const TerminalType_Mobile = 2; // 移动端网页（包括手机和平板）
    const TerminalType_WxOfficialAccount = 3; // 微信公众号
    const TerminalType_WxApp = 4; // 微信小程序
    const TerminalType_Manual = 5; // 手工录入
    const TerminalType_WxWork = 6; // 企业微信
    const TerminalType_WxAppCrm = 7; // 小程序crm端

    // 网站状态
    const SiteStatus_NoActive = 0;
    const SiteStatus_Try = 1;
    const SiteStatus_TryStop = 2;
    const SiteStatus_Active = 3;
    const SiteStatus_Stop = 4;
    const SiteStatus_UserTryStop = 5;
    const SiteStatus_UserStop = 6;

    // 网站备份类型
    const SiteBackupType_DayAuto = 1; // 日自动备份
    const SiteBackupType_Manual = 9; // 用户手工备份

    // 会员帐号体系类型
    const AccountFlag_Single = 0; // 单一帐号体系，使用不同
    const AccountFlag_Unified = 9; // 通过手机号打通

    // 会员授权类型
    const MemberAuthType_Manual = 0; // 手工
    const MemberAuthType_WxOficialAccount = 1; // 微信公众号
    const MemberAuthType_Alipay = 2; // 支付宝
    const MemberAuthType_QQ = 3; // QQ
    const MemberAuthType_Weibo = 4; // 微博
    const MemberAuthType_WxWork = 5; // 企业微信
    const MemberAuthType_WxApp = 6; // 微信小程序

    // 支付方式
    const PayType_Unknow = 0; // 未定义
    const PayType_Balance = 1; // 余额
    const PayType_Weixin = 2; // 微信
    const PayType_Alipay = 3; // 支付宝
    const PayType_Paypal = 4; // Paypal
    const PayType_Manual = 5; // 线下支付（手工入款、扣款）
    const PayType_WeixinQrcode = 6; // 微信收款码
    const PayType_AlipayQrcode = 7; // 支付宝收款码
    const PayType_AlipayAccount = 8; // 支付宝账户
    const PayType_Bank = 9; // 银行账户
    const PayType_Bonus = 10; // 平台返款
    const PayType_TongLian = 11; // 通联支付
    const PayType_Supplier = 98; // 供应商货款 (转余额)
    const PayType_Commission = 99; // 佣金（分销转余额）
    const PayType_CloudStockGoods = 79; // 云仓货款 (转余额)

    // 财务类型
    const FinanceType_Normal = 0; // 余额帐户
    const FinanceType_Gift = 1; // 赠金帐户
    const FinanceType_Transfer = 2; // 主要用于系统与第三方账目进出时只产生一条记录
    const FinanceType_CloudStockPurchase = 5; // 云仓货款（记录向平台进货时收到的货款）
    const FinanceType_AgentInitial = 6; // 代理商加盟费
    const FinanceType_CloudStock = 7; // 云仓相关财务记录
    const FinanceType_AgentCommission = 8; // 代理佣金账户
    const FinanceType_Commission = 9; // 分销佣金账户
    const FinanceType_DealerInitial = 10; // 经销商加盟费
    const FinanceType_AreaAgentCommission = 11; // 区域代理佣金
    const FinanceType_Supplier = 12; // 供应商货款

    // 财务子类型(关联FinanceType_XXX一起使用)
    const FinanceSubType_NoType = 0; // 无子类型
    const FinanceSubType_AgentCommission_Order = 801; // 代理正常订单佣金
    const FinanceSubType_AgentCommission_SaleReward = 802; // 代理销售奖(平级/越级奖)佣金
    const FinanceSubType_AgentCommission_Recommend = 803; // 代理推荐奖佣金
    const FinanceSubType_AgentCommission_Performance = 804; // 代理业绩奖佣金
    const FinanceSubType_AgentCommission_OtherReward = 805; // 代理其他奖佣金

    const FinanceSubType_CloudStock_Goods = 701; // 云仓货款
    const FinanceSubType_DealerCommission_Performance = 702; // 经销商业绩奖
    const FinanceSubType_DealerCommission_Recommend = 703; // 经销商推荐奖
    const FinanceSubType_DealerCommission_SubPerformance = 705; // 经销商支付给下级的业绩奖
    const FinanceSubType_DealerCommission_SubRecommend = 706; // 经销商支付给下级的推荐奖
    const FinanceSubType_DealerCommission_SubSale = 707; // 经销商支付给下级的销售奖
    const FinanceSubType_DealerCommission_Sale = 704; // 经销商销售奖
    const FinanceSubType_DealerCommission_Order = 708; // 经销商订货返现奖

    const FinanceSubType_AreaAgentCommission_Order = 601; // 区域代理订单佣金

    const FinanceSubType_Supplier_Goods = 801; // 供应商货款

    // 财务方向为支出时的类型
    const FinanceOutType_Unknow = 0; // 未定义
    const FinanceOutType_PayOrder = 1; // 购物
    const FinanceOutType_ServiceFee = 2; // 提现等手续费（已作废）
    const FinanceOutType_Reverse = 3; // 冲帐
    const FinanceOutType_Refund = 4; // 退款到外部，如支付宝、微信等
    const FinanceOutType_Withdraw = 5; // 提现到外部，如支付宝、微信等
    const FinanceOutType_Manual = 6;   // 手动扣款
    const FinanceOutType_AgentInitial = 7;   // 支付代理加盟费
    const FinanceOutType_Give = 8;   // 转现支出
    const FinanceOutType_CloudStock_PayOrder = 9; // 云仓进货支付
    const FinanceOutType_CloudStock_TakeDeliver_Fright = 10; // 云仓提货运费
    const FinanceOutType_DealerInitial = 11;   // 支付经销商加盟费
    const FinanceOutType_DealerPerformanceReward = 12; // 经销商支付下级业绩奖
    const FinanceOutType_DealerRecommendReward = 13; // 经销商支付下级推荐奖
    const FinanceOutType_DealerSaleReward = 14; // 经销商支付下级推荐奖
    const FinanceOutType_DealerOrderReward = 15; // 经销商支付下级订货返现奖
    const FinanceOutType_CloudStockGoodsToBalance = 79; //云仓收入提现到余额
    const FinanceOutType_SupplierToBalance = 96; // 供应商货款转余额
    const FinanceOutType_AreaAgentCommissionToBalance = 97; // 区域代理佣金转余额
    const FinanceOutType_CommissionToBalance = 98; // 佣金或分红转余额

    // 财务方向为入款时的类型
    const FinanceInType_Unknow = 0; // 未定义
    const FinanceInType_Recharge = 1; // 充值
    const FinanceInType_Reverse = 2; // 冲帐
    const FinanceInType_Refund = 3; // 退款到余额
    const FinanceInType_Trade = 4; // 支付（不产生余额、赠金、佣金）
    const FinanceInType_Manual = 5;   // 手动充值
    const FinanceInType_Give = 8;   // 转现收入
    const FinanceInType_Bonus = 10;   // 平台返款
    const FinanceInType_AgentInitial = 60; //代理加盟费
    const FinanceInType_DealerInitial = 65; //经销商加盟费
    const FinanceInType_CloudStockGoods = 70; //云仓货款
    const FinanceInType_CloudStockGoodsToBalance = 79; //云仓收入提现到余额
    const FinanceInType_SupplierGoods = 95; // 供应商货款
    const FinanceInType_SupplierToBalance = 96; // 供应商货款转余额
    const FinanceInType_AreaAgentCommissionToBalance = 97; // 区域代理佣金转余额
    const FinanceInType_CommissionToBalance = 98; // 佣金或分红转余额
    const FinanceInType_Commission = 99; // 佣金、或分红收入

    // 财务订单类型
    const FinanceOrderType_Unknow = 0; // 未定义
    const FinanceOrderType_Normal = 1; // 普通零售订单
    const FinanceOrderType_CloudStock_Purchase = 2; // 代理进货单
    const FinanceOrderType_CloudStock_TakeDelivery = 3; // 代理提货单运费

    // 财务状态
    const FinanceStatus_Freeze = 0; // 冻结
    const FinanceStatus_Active = 1; // 正常生效
    const FinanceStatus_Invalid = 2; // 作废

    // 财务是否订单交费充值（与余额充值作区分）
    const FinanceIsOrder_No = 0;
    const FinanceIsOrder_Yes = 1;

    // 财务标志，是否有真正的资金来往
    const FinanceIsReal_No = 0;
    const FinanceIsReal_Yes = 1;

    // 出入账标志
    const FinanceAccountType_Flat = 0; // 平账
    const FinanceAccountType_Out = -1; // 出账
    const FinanceAccountType_In = 1; // 入账

    // 微信公众号菜单
    const Weixin_Menu_Url = 0; // 跳转url
    const Weixin_Menu_Text = 1; // 返回文本消息
    const Weixin_Menu_Rich = 2; // 返回图文
    const Weixin_Menu_Image = 3; // 返回图片
	const Weixin_Menu_MiniApp = 4; // 跳转小程序
    const Weixin_Menu_Callback = 99; // 自定义回调

    // 微信公众号自定义回复类型
    const Weixin_Callback_Poster = 1; // 分享海报

    // 微信自动回复类型
    const Weixin_AutoReply_Subscribe = 0; // 关注自动回复
    const Weixin_AutoReply_Notmatch = 1; // 消息不匹配回复
    const Weixin_AutoReply_Keyword = 2; // 关键词回复

    // 微信自动回复的回复数据类型
    const Weixin_AutoReplyType_Text = 0; // 回复文本
    const Weixin_AutoReplyType_Rich = 1; // 回复图文消息
    const Weixin_AutoReplyType_Image = 2; // 回复图片
    const Weixin_AutoReplyType_Callback = 99; // 自定义回调

    // 微信关键字匹配类型
    const Weixin_MatchType_Exact = 0; // 精确匹配
    const Weixin_MatchType_Vague = 1; // 模糊
    const Weixin_MatchType_Regex = 2; // 正则表达式

    // 消息通知行为
    const MessageType_Unknown = 0; // 未知类型
    const MessageType_Order_PaySuccess = 1; // 付费成功通知
    const MessageType_Order_Send = 2; // 订单发货通知
    const MessageType_Order_NoPay = 3; // 订单催付通知
    const MessageType_GoodsRefund_Agree = 4; // 商家同意退货
    const MessageType_MoneyRefund_Reject = 5; // 商家拒绝退款
    const MessageType_GoodsRefund_Reject = 6; // 商家拒绝退货
    const MessageType_MoneyRefund_Success = 7; // 退款成功通知
    const MessageType_Balance_Change = 8; // 余额变动通知
    const MessageType_Point_Change = 9; // 积分赠送通知
    const MessageType_Member_LevelUpgrade = 10; // 会员升级通知
    const MessageType_DistributorBecome_Agree = 11; // 成为分销商通知
    const MessageType_SubMember_New = 12; // 新增分销下级通知
    const MessageType_Distributor_LevelUpgrade = 13; // 分销商等级变动通知
    const MessageType_Commission_Active = 14; // 分销订单提成通知
    const MessageType_Commission_Withdraw = 15; // 佣金提现通知
    const MessageType_DistributorBecome_Reject = 16; // 申请分销商被拒通知
    const MessageType_ProductStock_Warn = 17; // 库存预警通知（卖家）
    const MessageType_AfterSale_Apply = 18; // 维权订单通知（卖家）
    const MessageType_AfterSale_GoodsRefund = 19; // 买家已退货提醒（卖家）
    const MessageType_Withdraw_Apply = 20; // 提现申请通知（卖家）
    const MessageType_Order_NewPay = 21; // 新订单通知（卖家）
    const MessageType_Agent_Agree = 22; // 成为代理通知
    const MessageType_Agent_Reject = 23; // 申请代理被拒通知
    const MessageType_Agent_LevelUpgrade = 24; // 代理等级变动通知
    const MessageType_AgentSubMember_LevelUpgrade = 25; // 成员代理等级变动通知
    const MessageType_Agent_Commission = 26; // 团队分红通知
    const MessageType_Agent_Commission_Withdraw = 27; // 团队分红提现通知

    const MessageType_CloudStock_Purchase_Front_Order_Pay_Success = 28;// 付费成功通知（用于前台进货通知）
    const MessageType_CloudStock_Purchase_Admin_Verify_Order_Pay_Success = 29;// 付费成功通知（用于后台审核的进货通知）
    const MessageType_CloudStock_Purchase_Order_Match = 30;// 订单发货通知（进货单-配仓）
    const MessageType_CloudStock_Take_Delivery_Send = 31;// 订单发货通知（提货单-发货）
    const MessageType_CloudStock_Purchase_Order_NoPay = 32;// 订单催付通知-（进货单）（）
    const MessageType_CloudStock_Inventory_Change = 33;// 云仓库存变化通知
    const MessageType_CloudStock_ILevelUpgrade = 34;// 云仓等级通知
    const MessageType_CloudStock_Purchase_Commission_Under = 35;// 下级进货收入通知
    const MessageType_CloudStock_Retail_Commission = 36;// 零售订单收入通知
    const MessageType_CloudStock_Withdraw_Commission = 37;// 云仓收入提现通知
    const MessageType_CloudStock_Open = 38;// 开通云仓通知
    const MessageType_CloudStock_Member_Add = 39;// 新增云仓拿货成员通知
    const MessageType_CloudStock_Inventory_Not_Enough = 40;// 云仓补货提醒

    const MessageType_Dealer_Agree = 41; // 成为经销商通知
    const MessageType_Dealer_Reject = 42; // 申请经销商被拒通知
    const MessageType_Dealer_LevelUpgrade = 43; // 经销商等级变动通知
    const MessageType_DealerSubMember_LevelUpgrade = 44; // 成员经销商等级变动通知
    const MessageType_Dealer_Verify = 45; // 经销商审核

    const MessageType_Area_Agent_Agree = 46; // 成为区域代理商
    const MessageType_Area_Agent_Reject = 47; // 申请区域代理商被拒通知
    const MessageType_Area_Agent_Commission = 48; // 区域代理商佣金
    const MessageType_AreaAgent_Withdraw_Commission = 49; // 区域代理商佣金提现

    const MessageType_Supplier_Price_Change = 50; // 修改供货价通知

    const MessageType_Max_Num = 50; // 最大的消息通知行为类型id

    // 性别
    const Sex_Male = 1; // 男
    const Sex_Female = 2; // 女

    // 产品类型
    const Product_Type_Physical = 0; // 实体商品
    const Product_Type_Virtual = 1; // 虚拟商品
    const Product_Type_Fenxiao_Physical = 8; // 分销实体商品
    const Product_Type_Fenxiao_Virtual = 9; // 分销虚拟商品

    // 产品状态
    const Product_Status_Sell = 1; // 出售中
    const Product_Status_Warehouse = 0; // 下架 仓库中
    const Product_Status_Sold_Out = -1; // 售罄  数据库中使用其他字段保存 该字段只是后台查询导出用
    const Product_Status_Delete = -9; // 删除
    const Product_VerifyStatus_WaitReview = 0; // 供应商商品待审
    const Product_VerifyStatus_Active = 1; // 供应商商品审核通过
    const Product_VerifyStatus_Refuse = -1; // 供应商商品审核拒绝
    const Product_VerifyStatus_Draft = -2; // 供应商商品未提审
    // 产品是否售罄
    const Product_Sold_Out = 1; // 产品售罄
    const Product_No_Sold_Out = 0; // 产品未售罄
    // 积分来源/通途
    const PointInOutType_Default = 0; // 其他
    const PointInOutType_MemberReg = 1; // 注册会员
    const PointInOutType_MemberLogin = 2; // 登录
    const PointInOutType_MemberInfo = 3; // 完善个人资料
    const PointInOutType_Consume = 4; // 购物
    const PointInOutType_ProductComment = 5; // 评论商品
    const PointInOutType_Recharge = 6; // 充值
    const PointInOutType_Share = 7; // 分享
    const PointInOutType_MemberRecommend = 8; // 推荐新会员
    const PointInOutType_DistributionRecommend = 9; // 推荐新分销商
    const PointInOutType_DistributionBecome = 10; // 申请成为分销商
    const PointInOutType_OrderPay = 11; // 订单抵扣
    const PointInOutType_ConsumeRefund = 12; // 购物退款扣积分（保留）
    const PointInOutType_Give_InCome = 13; // 转现收入
    const PointInOutType_Give_Pay = 14; // 转现支出

    const PointStatus_Active = 1; // 积分生效
    const PointStatus_UnActive = 0; // 积分未生效

    // 会员状态
    const MemberStatus_Active = 1; // 活跃的
    const MemberStatus_UnActive = 0; // 封号的

    // SessionKey
    const SessionKey_SmsCode_LastTime = 'SmsCode.LastTime';
    const SessionKey_SmsCode_ExpireTime = 'SmsCode.ExpireTime'; // 短信验证码过期时间
    const SessionKey_DistributorApply_ProductID = 'Distributor.Apply.ProductID'; // 分销商申请

    // 后台管理员状态
    const SiteAdminStatus_Active = 1; // 生效
    const SiteAdminStatus_UnActive = 0; // 禁用
    const SiteAdminStatus_Delete = -1; // 删除

    // RoleKey
    const SiteRole_SiteAdmin = 'site.admin'; // 后台系统管理员
    const SiteRole_OnlyLogin = 'site.login'; // 只需后台登录，无需其他权限

    // 网站角色类型
    const SiteRoleType_Staff = 0; // 普通员工
    const SiteRoleType_Manager = 1; // 网站管理员
    const SiteRoleType_Admin = 9; // 系统管理员

    // 短信接口类型
    const SmsType_YunZhi = 1; // 云指接口
    const SmsType_DaYu = 2; // 阿里大鱼

    // 验证文件类型
    const VerifyFileType_MP_Verify = 'mp_verify'; //微信公众号
    const VerifyFileType_WxWork_Verify = 'wxwork_verify'; //企业微信
    const VerifyFileType_WxApp_Verify = 'wxapp_verify'; //微信小程序

    // 其他关键词
    const Keyword_CustomWord = "diy_word";

    // 粉丝平台类型
    const Fans_PlatformType_WxOfficialAccount = 0; // 公众号
    const Fans_PlatformType_H5 = 1; // H5
    const Fans_PlatformType_Douyin = 2; // 抖音

    /**
     * 返回网站状态的文本表示形式
     * @param int $status
     * @return string
     */
    public static function getSiteStatusText(int $status)
    {
        if ($status == static::SiteStatus_NoActive) return "未生效";
        if ($status == static::SiteStatus_Try) return "试用中";
        if ($status == static::SiteStatus_TryStop) return "试用停用";
        if ($status == static::SiteStatus_Active) return "生效";
        if ($status == static::SiteStatus_Stop) return "停用";
        if ($status == static::SiteStatus_UserTryStop) return "用户试用停用";
        if ($status == static::SiteStatus_UserStop) return "用户停用";
        return "未知状态";
    }

    /**
     * 返回终端类型的文本表示形式
     * @param int $terminalType
     * @return string
     */
    public static function getTerminalTypeText(int $terminalType)
    {
        switch ($terminalType) {
            case static::TerminalType_Mobile:
                return 'H5';
            case static::TerminalType_WxApp:
                return '小程序';
            case static::TerminalType_PC:
                return 'PC';
            case static::TerminalType_WxOfficialAccount:
                return '公众号';
            case static::TerminalType_Manual:
                return '手工录入';
            case static::TerminalType_WxAppCrm:
                return '小程序员工端';
            case static::TerminalType_WxWork:
                return '企业微信';
            default:
                return '未知';
        }
    }

    /**
     * 返回性别的文本表示形式
     * @param int $sex
     * @return string
     */
    public static function getSexText(int $sex)
    {
        if ($sex == static::Sex_Male) return '男';
        else if ($sex == static::Sex_Female) return '女';
        else return '未知';
    }

    /**
     * 返回会员状态的文本表示形式
     * @param $status
     * @return string
     */
    public static function getMemberStatusText(int $status)
    {
        if ($status == static::MemberStatus_Active) return '正常';
        else if ($status == static::MemberStatus_UnActive) return '封号';
        else return '未知';
    }

    /**
     * 返回积分来源用途的文本表示形式（后台专用）
     * @param int $inoutType
     * @return string
     */
    public static function getPointInoutTypeText(int $inoutType)
    {
        if ($inoutType == static::PointInOutType_Default) return '其他';
        else if ($inoutType == static::PointInOutType_MemberReg) return '首次注册';
        else if ($inoutType == static::PointInOutType_MemberLogin) return '登录';
        else if ($inoutType == static::PointInOutType_MemberInfo) return '完善个人资料';
        else if ($inoutType == static::PointInOutType_Consume) return '购买商品';
        else if ($inoutType == static::PointInOutType_ProductComment) return '评论商品';
        else if ($inoutType == static::PointInOutType_Recharge) return '充值';
        else if ($inoutType == static::PointInOutType_Share) return '首次分享';
        else if ($inoutType == static::PointInOutType_MemberRecommend) return '推荐新会员';
        else if ($inoutType == static::PointInOutType_DistributionRecommend) return '推荐新分销商';
        else if ($inoutType == static::PointInOutType_DistributionBecome) return '申请成为分销商';
        else if ($inoutType == static::PointInOutType_OrderPay) return '订单抵扣';
        else if ($inoutType == static::PointInOutType_ConsumeRefund) return '售后退款扣除';
        else if ($inoutType == static::PointInOutType_Give_InCome) return '积分转赠收入';
        else if ($inoutType == static::PointInOutType_Give_Pay) return '积分转赠支出';
        else return '其他';
    }

    /**
     * 返回积分来源用途的文本表示形式（前台专用）
     * @param int $inoutType
     * @return string
     */
    public static function getPointInoutTypeTextForFront(int $inoutType)
    {
        if ($inoutType == static::PointInOutType_Default) return '其他';
        else if ($inoutType == static::PointInOutType_MemberReg) return '注册' . trans('shop-front.diy_word.member');
        else if ($inoutType == static::PointInOutType_MemberLogin) return '登录';
        else if ($inoutType == static::PointInOutType_MemberInfo) return '完善个人资料';
        else if ($inoutType == static::PointInOutType_Consume) return '购买商品';
        else if ($inoutType == static::PointInOutType_ProductComment) return '评论商品';
        else if ($inoutType == static::PointInOutType_Recharge) return '充值';
        else if ($inoutType == static::PointInOutType_Share) return '分享';
        else if ($inoutType == static::PointInOutType_MemberRecommend) return '推荐新' . trans('shop-front.diy_word.member');
        else if ($inoutType == static::PointInOutType_DistributionRecommend) return '推荐新' . trans('shop-front.diy_word.distributor');
        else if ($inoutType == static::PointInOutType_DistributionBecome) return '申请成为' . trans('shop-front.diy_word.distributor');
        else if ($inoutType == static::PointInOutType_OrderPay) return '支付订单抵扣' . trans('shop-front.diy_word.point');
        else if ($inoutType == static::PointInOutType_ConsumeRefund) return '售后退款扣除';
        else if ($inoutType == static::PointInOutType_Give_InCome) return trans('shop-front.diy_word.point').'转赠收入';
        else if ($inoutType == static::PointInOutType_Give_Pay) return trans('shop-front.diy_word.point').'转赠支出';
        else return '其他';
    }

    /**
     * 返回支付方式的文本表示形式
     * @param int $payType
     * @param string $sign
     * @param bool $isFront true=用于前台，false=用于后台
     * @return string
     */
    public static function getPayTypeText(int $payType, $sign = '支付', $isFront = false)
    {
        $langGroup = 'shop-front';
        switch ($payType) {
            case self::PayType_Balance:
                return ($isFront ? trans($langGroup . '.diy_word.balance') : '余额') . $sign;
            case self::PayType_Alipay:
                return '支付宝' . $sign;
            case self::PayType_Weixin:
                return '微信' . $sign;
            case self::PayType_TongLian:
                return '通联支付' . $sign;
            case self::PayType_Paypal:
                return 'Paypal' . $sign;
            case self::PayType_Bonus:
                return '平台返款';
            case self::PayType_Supplier:
                return '供应商货款';
            case in_array($payType,[ self::PayType_Commission,self::PayType_CloudStockGoods]):
                return ($isFront ? trans($langGroup . '.diy_word.commission') : '佣金') . $sign;
            case in_array($payType, self::getOfflinePayType(true)):
                return '线下' . $sign;
            default:
                return '在线' . $sign;
        }
    }

    /**
     * 返回支付方式的文本表示形式(用于前台更完整的)
     * @param int $payType
     * @param string $sign
     * @param bool $isFront true=用于前台，false=用于后台
     * @return string
     */
    public static function getPayTypeTextTwo(int $payType, $isFront = false)
    {
        $langGroup = 'shop-front';
        switch ($payType) {
            case self::PayType_Balance:
                return ($isFront ? trans($langGroup . '.diy_word.balance') : '余额');
            case self::PayType_Alipay:
                return '支付宝' ;
            case self::PayType_Weixin:
                return '微信' ;
            case self::PayType_TongLian:
                return '通联支付' ;
            case self::PayType_Paypal:
                return 'Paypal';
            case self::PayType_WeixinQrcode:
                return '线下结算-微信';
            case self::PayType_AlipayQrcode:
                return '线下结算-支付宝';
            case self::PayType_AlipayAccount:
                return '线下结算-支付宝';
            case self::PayType_Bank:
                return '线下结算-银行账户';
            default:
                return '未知';
        }
    }

    /**
     * 返回支付方式的文本表示形式
     * @param int $payType
     * @return string
     */
    public static function getPayTypeWithdrawText(int $payType)
    {
        switch ($payType) {
            case self::PayType_Balance:
                return '余额';
            case in_array($payType, [self::PayType_Alipay, self::PayType_AlipayQrcode, self::PayType_AlipayAccount]) :
                return '支付宝';
            case  in_array($payType, [self::PayType_Weixin, self::PayType_WeixinQrcode]) :
                return '微信钱包';
            case  in_array($payType, [self::PayType_TongLian]) :
                return '通联支付';
            case self::PayType_Paypal:
                return 'Paypal';
            case self::PayType_Commission:
                return '佣金';
            case self::PayType_Manual:
                return '线下';
            case self::PayType_WeixinQrcode:
                return '微信收款码';
            case self::PayType_AlipayQrcode:
                return '支付宝收款码';
            case self::PayType_AlipayAccount:
                return '支付宝账户';
            case self::PayType_Bank:
                return '银行账户';
            default:
                return '在线';
        }
    }

    /**
     * 返回入账类型的文本表示形式
     * @param int $inType
     * @return string
     */
    public static function getFinanceInTypeText(int $inType)
    {
        switch ($inType) {
            case self::FinanceInType_Recharge:
                return '充值';
            case self::FinanceInType_Reverse:
                return '冲账';
            case self::FinanceInType_Refund:
                return '退款';
            case self::FinanceInType_CommissionToBalance:
                return '内部提现-佣金提现';
            case self::FinanceInType_Commission:
                return '佣金';
            case self::FinanceInType_Trade:
                return '支付';
            case self::FinanceInType_Manual:
                return '手工充值';
            case self::FinanceInType_Give:
                return '转现收入';
            case self::FinanceInType_Bonus:
                return '充值赠送';
            default:
                return '未知';
        }
    }

    /**
     * 返回出账类型的文本表示形式
     * @param int $outType
     * @return string
     */
    public static function getFinanceOutTypeText(int $outType)
    {
        switch ($outType) {
            case self::FinanceOutType_PayOrder:
                return '支付';
            case self::FinanceOutType_ServiceFee:
                return '手续费';
            case self::FinanceOutType_Reverse:
                return '冲帐';
            case self::FinanceOutType_Refund:
                return '退款';
            case self::FinanceOutType_Withdraw:
                return '提现';
            case self::FinanceOutType_CommissionToBalance:
                return '内部提现-佣金提现';
            case self::FinanceOutType_Manual:
                return '手工扣款';
            case self::FinanceOutType_Give:
                return '转现支出';
            case self::FinanceOutType_SupplierToBalance:
                return '供应商货款转余额';
            default:
                return '未知';
        }
    }

    /**
     * 出入账类型文字形式
     * @param int $accountType
     * @return string
     */
    public static function getAccountTypeText(int $accountType)
    {
        switch ($accountType) {
            case self::FinanceAccountType_In:
                return '入账';
            case self::FinanceAccountType_Out:
                return '出账';
            default:
                return '平账';
        }
    }

    /**
     * 获取第三方在线支付方式类型
     * @return array
     */
    public static function getOnlinePayType()
    {
        return [
            self::PayType_Weixin,
            self::PayType_Alipay,
            self::PayType_Paypal,
            self::PayType_TongLian
        ];
    }

    /**
     * 获取第三方线下支付方式类型
     * @param bool $containManual 是否包含手工入款或扣款
     * @return array
     */
    public static function getOfflinePayType($containManual = false)
    {
        $result = [
            self::PayType_WeixinQrcode,
            self::PayType_AlipayQrcode,
            self::PayType_AlipayAccount,
            self::PayType_Bank
        ];
        if ($containManual) {
            array_push($result, self::PayType_Manual);;
        }
        return $result;
    }

    /**
     * 获取第三方线线上下上支付方式类型
     * @param bool $containManual 是否包含手工入款或扣款
     * @return array
     */
    public static function getThirdPartyPayType($containManual = false)
    {
        return array_merge(self::getOnlinePayType(), self::getOfflinePayType($containManual));
    }

    /**
     * 获取财务类型文字形式
     * @param int $financeType
     * @return string
     */
    public static function getFinanceTypeText(int $financeType)
    {
        switch ($financeType) {
            case self::FinanceType_Normal:
                return '余额';
            case self::FinanceType_Gift:
                return '赠金';
            case self::FinanceType_AgentCommission:
                return '分红';
            case self::FinanceType_Commission:
                return '佣金';
            case self::FinanceType_Transfer:
                return '流水';
            case self::FinanceType_CloudStock:
                return '经销商资金';
            case self::FinanceType_AreaAgentCommission:
                return '区代返佣';
            case self::FinanceType_Supplier:
                return '供应商货款';
            default:
                return '未知';
        }
    }

    /**
     * 获取财务子类型文字形式
     * @param int $subType
     * @return string
     */
    public static function getFinanceSubTypeText(int $subType)
    {
        switch ($subType) {
            case self::FinanceSubType_AgentCommission_Order;
                return '订单分红';
            case self::FinanceSubType_AgentCommission_SaleReward:
                return '销售奖';
            case self::FinanceSubType_AgentCommission_Recommend:
                return '推荐奖';
            case self::FinanceSubType_AgentCommission_Performance:
                return '业绩奖';
            case self::FinanceSubType_CloudStock_Goods:
                return '云仓货款';
            case self::FinanceSubType_DealerCommission_Performance:
                return '经销商业绩奖';
            default:
                return '';
        }
    }

    /**
     * 获取财务子类型文字形式（前台）
     * @param int $subTpye
     * @return string
     */
    public static function getFinanceSubTypeTextForFront(int $subTpye)
    {
        switch ($subTpye) {
            case self::FinanceSubType_AgentCommission_Order;
                return '订单' . trans('shop-front.diy_word.agent_reward');
            case self::FinanceSubType_AgentCommission_SaleReward:
                return '订单' . trans('shop-front.diy_word.team_agent_sale_reward');
            case self::FinanceSubType_AgentCommission_Recommend:
                return trans('shop-front.diy_word.team_agent_recommend_reward');
            case self::FinanceSubType_AgentCommission_Performance:
                return trans('shop-front.diy_word.team_agent_performance_reward');
            case self::FinanceSubType_AreaAgentCommission_Order:
                return trans('shop-front.diy_word.area_agent_commission');
            default:
                return '';
        }
    }
}