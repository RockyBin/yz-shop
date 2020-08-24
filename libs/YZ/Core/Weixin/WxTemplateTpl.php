<?php
/**
 * Created by Aison.
 */

namespace YZ\Core\Weixin;

use YZ\Core\Constants;

class WxTemplateTpl
{
    /**
     * 模板类型
     * status：默认是否开启
     * short_id：默认的消息短id
     */
    const TemplateConfig = [
        Constants::MessageType_Unknown => [
            "status" => false,
            "short_id" => "",
        ],
        Constants::MessageType_Order_PaySuccess => [
            "status" => true,
            "short_id" => "OPENTM207777974",
        ],
        Constants::MessageType_Order_Send => [
            "status" => true,
            "short_id" => "OPENTM200565259",
        ],
        Constants::MessageType_Order_NoPay => [
            "status" => true,
            "short_id" => "OPENTM410180382",
        ],
        Constants::MessageType_GoodsRefund_Agree => [
            "status" => true,
            "short_id" => "OPENTM416301917",
        ],
        Constants::MessageType_GoodsRefund_Reject => [
            "status" => true,
            "short_id" => "OPENTM406292640",
        ],
        Constants::MessageType_MoneyRefund_Reject => [
            "status" => true,
            "short_id" => "OPENTM417800193",
        ],
        Constants::MessageType_MoneyRefund_Success => [
            "status" => true,
            "short_id" => "OPENTM406075758",
        ],
        Constants::MessageType_Balance_Change => [
            "status" => true,
            "short_id" => "OPENTM403182052",
        ],
        Constants::MessageType_Point_Change => [
            "status" => true,
            "short_id" => "OPENTM403182052",
        ],
        Constants::MessageType_Member_LevelUpgrade => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_DistributorBecome_Agree => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_DistributorBecome_Reject => [
            "status" => true,
            "short_id" => "OPENTM206867765",
        ],
        Constants::MessageType_SubMember_New => [
            "status" => true,
            "short_id" => "OPENTM207685059",
        ],
        Constants::MessageType_Distributor_LevelUpgrade => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_Commission_Active => [
            "status" => true,
            "short_id" => "OPENTM405637175",
        ],
        Constants::MessageType_Commission_Withdraw => [
            "status" => true,
            "short_id" => "OPENTM400492077",
        ],
        Constants::MessageType_Agent_Agree => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_Dealer_Agree => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_Agent_Reject => [
            "status" => true,
            "short_id" => "OPENTM206867765",
        ],
        Constants::MessageType_Agent_LevelUpgrade => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_AgentSubMember_LevelUpgrade => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_Agent_Commission => [
            "status" => true,
            "short_id" => "OPENTM405637175",
        ],
        Constants::MessageType_Agent_Commission_Withdraw => [
            "status" => true,
            "short_id" => "OPENTM400492077",
        ],
        Constants::MessageType_CloudStock_Purchase_Front_Order_Pay_Success => [
            "status" => true,
            "short_id" => "OPENTM207777974",
        ],
        Constants::MessageType_CloudStock_Purchase_Admin_Verify_Order_Pay_Success => [
            "status" => true,
            "short_id" => "OPENTM207777974",
        ],
        Constants::MessageType_CloudStock_Purchase_Order_Match => [
            "status" => true,
            "short_id" => "OPENTM200565259",
        ],
        Constants::MessageType_CloudStock_Take_Delivery_Send => [
            "status" => true,
            "short_id" => "OPENTM200565259",
        ],
        Constants::MessageType_CloudStock_Purchase_Order_NoPay => [
            "status" => true,
            "short_id" => "OPENTM410180382",
        ],
        Constants::MessageType_CloudStock_Inventory_Change => [
            "status" => true,
            "short_id" => "OPENTM403182052",
        ],
        Constants::MessageType_CloudStock_ILevelUpgrade => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_CloudStock_Purchase_Commission_Under => [
            "status" => true,
            "short_id" => "OPENTM405637175",
        ],
        Constants::MessageType_CloudStock_Retail_Commission => [
            "status" => true,
            "short_id" => "OPENTM405637175",
        ],
        Constants::MessageType_CloudStock_Withdraw_Commission => [
            "status" => true,
            "short_id" => "OPENTM400492077",
        ],
//        Constants::MessageType_CloudStock_Open => [
//            "status" => true,
//            "short_id" => "OPENTM409641771",
//        ],
        Constants::MessageType_CloudStock_Member_Add => [
            "status" => true,
            "short_id" => "OPENTM207685059",
        ],
        Constants::MessageType_CloudStock_Inventory_Not_Enough => [
            "status" => true,
            "short_id" => "OPENTM412922431",
        ],
        Constants::MessageType_Dealer_Verify => [
            "status" => true,
            "short_id" => "OPENTM408471635",
        ],
        Constants::MessageType_Dealer_Reject => [
            "status" => true,
            "short_id" => "OPENTM206867765",
        ],
        Constants::MessageType_Dealer_LevelUpgrade => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_Area_Agent_Commission => [
            "status" => true,
            "short_id" => "OPENTM405637175",
        ],
        Constants::MessageType_Area_Agent_Agree => [
            "status" => true,
            "short_id" => "OPENTM406524975",
        ],
        Constants::MessageType_Area_Agent_Reject => [
            "status" => true,
            "short_id" => "OPENTM206867765",
        ],
        Constants::MessageType_AreaAgent_Withdraw_Commission => [
            "status" => true,
            "short_id" => "OPENTM400492077",
        ],
    ];

    // 默认模板数据
    public static function getTemplateData()
    {
        return [
            "OPENTM207777974" => [
                "content" => [
                    "first" => "亲，我们已收到您的货款，会尽快为您打包，请耐心等候",
                    "keyword1" => "{order_id}",
                    "keyword2" => "{pay_time}",
                    "keyword3" => "￥{order_money}",
                    "remark" => ""
                ]
            ],
            "OPENTM200565259" => [
                "content" => [
                    "first" => "亲，您的宝贝已在路上啦，正全速向您飞奔",
                    "keyword1" => "{order_id}",
                    "keyword2" => "{logistics_name}",
                    "keyword3" => "{logistics_no}",
                    "remark" => ""
                ]
            ],
            "OPENTM410180382" => [
                "content" => [
                    "first" => "亲，您的订单尚未付款哦，我们会为您保留到{end_time}，请尽快支付",
                    "keyword1" => "{product_name}",
                    "keyword2" => "￥{order_money}",
                    "keyword3" => "{create_time}",
                    "remark" => ""
                ]
            ],
            "OPENTM416301917" => [
                "content" => [
                    "first" => "亲，卖家已同意您的退货申请，请尽快填写退货物流信息",
                    "keyword1" => "{after_sale_id}",
                    "keyword2" => "{product_name}",
                    "keyword3" => "￥{refund_money}",
                    "remark" => ""
                ]
            ],
            "OPENTM417800193" => [
                "content" => [
                    "first" => "亲，您的退款申请被拒绝",
                    "keyword1" => "{product_name}",
                    "keyword2" => "￥{refund_money}",
                    "keyword3" => "{reject_reason}",
                    "keyword4" => "{after_sale_id}",
                    "remark" => ""
                ]
            ],
            "OPENTM406292640" => [
                "content" => [
                    "first" => "亲，您的退货申请被拒绝",
                    "keyword1" => "{after_sale_id}",
                    "keyword2" => "{order_id}",
                    "keyword3" => "{reject_reason}",
                    "keyword4" => "{product_name}",
                    "remark" => ""
                ]
            ],
            "OPENTM406075758" => [
                "content" => [
                    "first" => "亲，您的订单已经完成退款，请留意查收退款金额。",
                    "keyword1" => "{after_sale_id}",
                    "keyword2" => "￥{refund_money}",
                    "remark" => ""
                ]
            ],
            "OPENTM403182052" => [
                "content" => [
                    "first" => "",
                    "keyword1" => "{keyword1}",
                    "keyword2" => "{keyword2}",
                    "keyword3" => "{time}",
                    "remark" => ""
                ]
            ],
            "OPENTM406524975" => [
                "content" => [
                    "first" => "",
                    "keyword1" => "{member_nickname}",
                    "keyword2" => "{change_type}",
                    "remark" => ""
                ]
            ],
            "OPENTM410479278" => [
                "content" => [
                    "first" => "恭喜您，您已成功成为" . trans("shop-front.diy_word.distributor") . "。",
                    "keyword1" => "{member_id}",
                    "keyword2" => "{member_nickname}",
                    "keyword3" => "成为" . trans("shop-front.diy_word.distributor"),
                    "keyword4" => "{time}",
                    "remark" => ""
                ]
            ],
            "OPENTM200899615" => [
                "content" => [
                    "first" => "恭喜您，您已成功发展您的下级" . trans("shop-front.diy_word.distributor") . "。",
                    "keyword1" => "{sub_member_nickname}",
                    "keyword2" => "{member_type}",
                    "keyword3" => "{time}",
                    "remark" => ""
                ]
            ],
            "OPENTM220197216" => [
                "content" => [
                    "first" => "亲，您又成功" . trans("shop-front.diy_word.distribution") . "出一笔订单了！",
                    "keyword1" => "{order_id}",
                    "keyword2" => "￥{order_money}",
                    "keyword3" => "￥{commission_money}",
                    "keyword4" => "{time}",
                    "remark" => ""
                ]
            ],
            "OPENTM400492077" => [
                "content" => [
                    "first" => "亲，您申请提现的{money_type}已打款到您的账户，请注意查收！",
                    "keyword1" => "{member_id},{member_nickname}",
                    "keyword2" => "￥{withdraw_money}",
                    "keyword3" => "{active_time}",
                    "remark" => ""
                ]
            ],
            "OPENTM206867765" => [
                "content" => [
                    "first" => "",
                    "keyword1" => "{apply_type}",
                    "keyword2" => "{reject_reason}",
                    "remark" => ""
                ]
            ],
            "OPENTM405637175" => [
                "content" => [
                    "first" => "",
                    "keyword1" => "￥{money}",
                    "keyword2" => "{source}",
                    "keyword3" => "{time}",
                    "remark" => ""
                ]
            ],
            "OPENTM409641771" => [
                "content" => [
                    "first" => "",
                    "keyword1" => "{member_nickname}",
                    "keyword2" => "{type}",
                    "keyword3" => "{time}",
                    "remark" => ""
                ]
            ],
            "OPENTM207685059" => [
                "content" => [
                    "first" => "",
                    "keyword1" => "{member_nickname}",
                    "keyword2" => "{time}",
                    "remark" => ""
                ]
            ],
            "OPENTM412922431" => [
                "content" => [
                    "first" => "",
                    "keyword1" => "{member_nickname}",
                    "keyword2" => "{product_name}",
                    "keyword3" => "{stock_num}",
                    "remark" => ""
                ]
            ],
            "OPENTM408471635" => [
                "content" => [
                    "first" => "亲，您有一条待审核信息，请及时处理！",
                    "keyword1" => "{member_nickname}",
                    "keyword2" => "{verify_type}",
                    "remark" => ""
                ]
            ],
        ];
    }
}