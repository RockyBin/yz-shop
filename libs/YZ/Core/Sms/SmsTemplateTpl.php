<?php
/**
 * Created by Aison.
 */

namespace YZ\Core\Sms;

use YZ\Core\Constants;

class SmsTemplateTpl
{
    /**
     * 模板类型
     */
    const TemplateConfig = [
        Constants::MessageType_Unknown => [
            "status" => false
        ],
        Constants::MessageType_Order_PaySuccess => [
            "status" => false
        ],
        Constants::MessageType_Order_Send => [
            "status" => false
        ],
        Constants::MessageType_Order_NoPay => [
            "status" => false
        ],
        Constants::MessageType_GoodsRefund_Agree => [
            "status" => false
        ],
        Constants::MessageType_MoneyRefund_Reject => [
            "status" => false
        ],
        Constants::MessageType_GoodsRefund_Reject => [
            "status" => false
        ],
        Constants::MessageType_MoneyRefund_Success => [
            "status" => false
        ],
        Constants::MessageType_Balance_Change => [
            "status" => false
        ],
        Constants::MessageType_Point_Change => [
            "status" => false
        ],
        Constants::MessageType_Member_LevelUpgrade => [
            "status" => false
        ],
        Constants::MessageType_DistributorBecome_Agree => [
            "status" => false
        ],
        Constants::MessageType_SubMember_New => [
            "status" => false
        ],
        Constants::MessageType_Distributor_LevelUpgrade => [
            "status" => false
        ],
        Constants::MessageType_Commission_Active => [
            "status" => false
        ],
        Constants::MessageType_Commission_Withdraw => [
            "status" => false
        ],
        Constants::MessageType_DistributorBecome_Reject => [
            "status" => false
        ],
        Constants::MessageType_ProductStock_Warn => [
            "status" => false
        ],
        Constants::MessageType_AfterSale_Apply => [
            "status" => false
        ],
        Constants::MessageType_AfterSale_GoodsRefund => [
            "status" => false
        ],
        Constants::MessageType_Withdraw_Apply => [
            "status" => false
        ],
        Constants::MessageType_Order_NewPay => [
            "status" => false
        ],
        Constants::MessageType_Agent_Agree => [
            "status" => false
        ],
        Constants::MessageType_Agent_Reject => [
            "status" => false
        ],
        Constants::MessageType_Agent_LevelUpgrade => [
            "status" => false
        ],
        Constants::MessageType_AgentSubMember_LevelUpgrade => [
            "status" => false
        ],
        Constants::MessageType_Agent_Commission => [
            "status" => false
        ],
        Constants::MessageType_Agent_Commission_Withdraw => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Purchase_Front_Order_Pay_Success => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Purchase_Order_Match => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Purchase_Order_NoPay => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Inventory_Change => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_ILevelUpgrade => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Purchase_Commission_Under => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Retail_Commission => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Withdraw_Commission => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Open => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Member_Add => [
            "status" => false
        ],
        Constants::MessageType_CloudStock_Inventory_Not_Enough => [
            "status" => false
        ],
        Constants::MessageType_Dealer_Verify => [
            "status" => false
        ],
        Constants::MessageType_Dealer_Reject => [
            "status" => false
        ],
        Constants::MessageType_Dealer_LevelUpgrade => [
            "status" => false
        ],
        Constants::MessageType_Area_Agent_Commission => [
            "status" => false
        ],
        Constants::MessageType_Area_Agent_Agree => [
            "status" => false
        ],
        Constants::MessageType_Area_Agent_Reject => [
            "status" => false
        ],
        Constants::MessageType_AreaAgent_Withdraw_Commission => [
            "status" => false
        ],
        Constants::MessageType_Dealer_Agree => [
            "status" => false
        ],
        Constants::MessageType_Supplier_Price_Change => [
            "status" => false
        ]
    ];

    // 模板数据
    public static function getTemplateData()
    {
        return [
            Constants::SmsType_YunZhi => [
                Constants::MessageType_Order_PaySuccess => [
                    'key' => ['shop_name', 'url'],
                    'content' => '您在{shop_name}购买的商品已支付成功。查看详情{url}',
                ],
                Constants::MessageType_Order_Send => [
                    'key' => ['send_time', 'url'],
                    'content' => '您购买的宝贝已于{send_time}发货。跟踪物流详情{url}',
                ],
                Constants::MessageType_Order_NoPay => [
                    'key' => ['end_time', 'url'],
                    'content' => '您购买的宝贝还没有付款，我们会为您保留到{end_time}，请尽快支付。查看详情{url}',
                ],
                Constants::MessageType_GoodsRefund_Agree => [
                    'key' => ['order_id', 'url'],
                    'content' => '您的退货申请已被同意，订单编号{order_id}，请及时填写商品退货信息{url}',
                ],
                Constants::MessageType_MoneyRefund_Reject => [
                    'key' => ['url'],
                    'content' => '您的退款申请被拒绝。查看详情{url}',
                ],
                Constants::MessageType_GoodsRefund_Reject => [
                    'key' => ['url'],
                    'content' => '您的退货申请被拒绝。查看详情{url}',
                ],
                Constants::MessageType_MoneyRefund_Success => [
                    'key' => [],
                    'content' => '您的订单已完成退款，请留意查收退款金额。',
                ],
                Constants::MessageType_Balance_Change => [
                    'key' => ['time', 'money', 'balance', 'url'],
                    'content' => '您的账户' . trans('shop-front.diy_word.balance') . '发生变动。前往查看{url}',
                ],
                Constants::MessageType_Member_LevelUpgrade => [
                    'key' => ['level_name'],
                    'content' => '恭喜您，您已成功升级为{level_name}，将享受更多权益。',
                ],
                Constants::MessageType_DistributorBecome_Agree => [
                    'key' => ['shop_name', 'url'],
                    'content' => '恭喜您，您已成为{shop_name}的' . trans('shop-front.diy_word.distributor') . '。查看详情{url}',
                ],
                Constants::MessageType_SubMember_New => [
                    'key' => ['time', 'member_nickname'],
                    'content' => '恭喜您于{time}新增一名下级' . trans('shop-front.diy_word.distributor') . '{member_nickname}',
                ],
                Constants::MessageType_Distributor_LevelUpgrade => [
                    'key' => ['level_name'],
                    'content' => '恭喜您，您已成功升级为{level_name}，将享受更多权益。',
                ],
                Constants::MessageType_Commission_Active => [
                    'key' => ['commission_money'],
                    'content' => '恭喜您，您已成功' . trans('shop-front.diy_word.distribution') . '出一笔订单！' . trans('shop-front.diy_word.commission') . '金额：{commission_money}',
                ],
                Constants::MessageType_Commission_Withdraw => [
                    'key' => ['money_type', 'active_time', 'withdraw_money'],
                    'content' => '您的{money_type}账户于{active_time}成功提现{withdraw_money}元。',
                ],
                Constants::MessageType_DistributorBecome_Reject => [
                    'key' => [],
                    'content' => '您的' . trans('shop-front.diy_word.distributor') . '申请被拒绝。'
                ],
                Constants::MessageType_ProductStock_Warn => [
                    'key' => ['product_name'],
                    'content' => '您的{product_name}库存已经到达警戒范围，请尽快登录后台补货。',
                ],
                Constants::MessageType_AfterSale_Apply => [
                    'key' => ['after_sale_id'],
                    'content' => '您的买家发起了维权申请，订单编号：{after_sale_id}，请尽快登录后台处理。',
                ],
                Constants::MessageType_AfterSale_GoodsRefund => [
                    'key' => ['after_sale_id'],
                    'content' => '您的买家已退货，订单编号{after_sale_id}，请尽快登录后台处理。',
                ],
                Constants::MessageType_Withdraw_Apply => [
                    'key' => ['withdraw_money'],
                    'content' => '您有新的提现申请，请尽快登录后台处理。申请金额：{withdraw_money}。',
                ],
                Constants::MessageType_Order_NewPay => [
                    'key' => ['order_id'],
                    'content' => '您有一笔新订单：{order_id}，请尽快登录后台处理。',
                ],
                Constants::MessageType_Point_Change => [
                    'key' => [],
                    'content' => '您的账户' . trans('shop-front.diy_word.point') . '发生变动。前往查看{url}',
                ],
                Constants::MessageType_Agent_Agree => [
                    'key' => [],
                    'content' => '恭喜您，您已经成为{shop_name}的' . trans('shop-front.diy_word.team_agent') . '。查看详情{url}',
                ],
                Constants::MessageType_Agent_Reject => [
                    'key' => [],
                    'content' => '非常抱歉您的' . trans('shop-front.diy_word.team_agent') . '申请未通过审核！',
                ],
                Constants::MessageType_Agent_LevelUpgrade => [
                    'key' => [],
                    'content' => '您的' . trans('shop-front.diy_word.team_agent') . '等级已发生变动，当前等级为{member_agent_level}',
                ],
                Constants::MessageType_AgentSubMember_LevelUpgrade => [
                    'key' => [],
                    'content' => '',
                ],
                Constants::MessageType_Agent_Commission => [
                    'key' => [],
                    'content' => '',
                ],
                Constants::MessageType_Agent_Commission_Withdraw => [
                    'key' => [],
                    'content' => '您的' . trans('shop-front.diy_word.agent_reward') . '账户于{active_time}成功提现¥{withdraw_money}',
                ],
                Constants::MessageType_CloudStock_Purchase_Front_Order_Pay_Success => [
                    'key' => ['shop_name', 'url'],
                    'content' => '您在{shop_name}购买的云仓商品已提交线下支付信息，等待商家审核货款。查看详情{url}',
                ],
                Constants::MessageType_CloudStock_Purchase_Admin_Verify_Order_Pay_Success => [
                    'key' => ['shop_name', 'url'],
                    'content' => '您在{shop_name}购买的云仓商品已支付成功。查看详情{url}',
                ],
                Constants::MessageType_CloudStock_Purchase_Order_Match => [
                    'key' => ['send_time', 'url'],
                    'content' => '您购买的云仓商品已于{send_time}配仓。点击查看{url}',
                ],
                Constants::MessageType_CloudStock_Take_Delivery_Send => [
                    'key' => ['send_time', 'url'],
                    'content' => '您提货的云仓商品已于{send_time}发货。跟踪物流详情。点击查看{url}',
                ],
                Constants::MessageType_CloudStock_Purchase_Order_NoPay => [
                    'key' => ['end_time', 'url'],
                    'content' => '您购买的云仓商品还没有付款，我们会为您预留到{end_time}，请尽快支付。查看详情{url}',
                ],
                Constants::MessageType_CloudStock_Inventory_Change => [
                    'key' => [],
                    'content' => '',
                ],
                Constants::MessageType_CloudStock_ILevelUpgrade => [
                    'key' => ['wx_content_first', 'url'],
                    'content' => '{wx_content_first}{url}',
                ],
                Constants::MessageType_CloudStock_Purchase_Commission_Under => [
                    'key' => ['wx_content_first', 'money'],
                    'content' => '{wx_content_first}{money}',
                ],
                Constants::MessageType_CloudStock_Retail_Commission => [
                    'key' => ['wx_content_first', 'url'],
                    'content' => '{wx_content_first},查看详情{url}',
                ],
                Constants::MessageType_CloudStock_Withdraw_Commission => [
                    'key' => ['wx_content_first', 'url'],
                    'content' => '{wx_content_first},查看详情{url}',
                ],
                Constants::MessageType_CloudStock_Open => [
                    'key' => ['time', 'shop_name', 'url'],
                    'content' => '恭喜您，您于{time}在{shop_name}开通了云仓,查看详情{url}',
                ],
                Constants::MessageType_CloudStock_Member_Add => [
                    'key' => ['wx_content_first', 'url', 'member_nickname'],
                    'content' => '恭喜您，新增了一名经销商{member_nickname}',
                ],
                Constants::MessageType_CloudStock_Inventory_Not_Enough => [
                    'key' => ['url'],
                    'content' => '您的云仓库存不足无法自动完成配仓，请及时补货，早日结算回款哦！查看详情{url}',
                ],
                Constants::MessageType_Dealer_Agree => [
                    'key' => ['url', 'shop_name'],
                    'content' => '恭喜您，您已成为{shop_name}的经销商。查看详情{url}',
                ],
                Constants::MessageType_Dealer_Reject => [
                    'key' => ['url'],
                    'content' => '非常抱歉您的经销商申请未通过审核！',
                ],
                Constants::MessageType_Dealer_LevelUpgrade => [
                    'key' => ['dealer_level_name'],
                    'content' => '恭喜您，你的经销商等级升级至{dealer_level_name}',
                ],
                Constants::MessageType_Dealer_Verify => [
                    'key' => ['url'],
                    'content' => '您有一条待审核信息，请及时处理！查看详情{url}',
                ],
                Constants::MessageType_Area_Agent_Agree => [
                    'key' => ['url', 'shop_name'],
                    'content' => '恭喜您，您已成为{shop_name}的区域代理。查看详情{url}',
                ],
                Constants::MessageType_Area_Agent_Reject => [
                    'key' => ['url'],
                    'content' => '非常抱歉您的区域代理申请未通过审核！',
                ],
                Constants::MessageType_AreaAgent_Withdraw_Commission => [
                    'key' => ['active_time', 'withdraw_money'],
                    'content' => '您的区域代理返佣于{active_time}成功提现¥{withdraw_money}，请注意查收',
                ],
                Constants::MessageType_Area_Agent_Commission => [
                    'key' => [],
                    'content' => '',
                ],
                Constants::MessageType_Supplier_Price_Change => [
                    'key' => [],
                    'content' => '',
                ]
            ]
        ];
    }
}