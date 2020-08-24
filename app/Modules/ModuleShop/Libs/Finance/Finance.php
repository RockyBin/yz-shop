<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Finance;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use App\Modules\ModuleShop\Libs\Constants as LibConstants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Config;
use YZ\Core\Site\Site;

class Finance
{
    private $siteId = 0; // 站点ID

    /**
     * 初始化
     * Finance constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 查询列表
     * @param $params
     * @return array
     */
    public function getList($params)
    {
        $singleMember = $params['single_member'] ? true : false; // 是否针对单个用户，默认False
        $showAll = $params['show_all'] || ($params['ids'] && strlen($params['ids'] > 0)) ? true : false; // 是否显示所有，导出功能用，默认False
        $time_orderby = $params['time_order_by'] ? true : false;//需要把created_at，active_at，invalid_at 进行综合排序

        $page = max(1, intval($params['page']));
        $pageSize = intval($params['page_size']);
        if ($pageSize < 1) $pageSize = 20;

        $query = FinanceModel::query()->from('tbl_finance as finance');

        $query->addSelect('finance.*');

        if ($time_orderby) {
            $query->addSelect(\DB::Raw('greatest(finance.created_at,finance.active_at , IFNULL(finance.invalid_at,1)) as time'));
        }

        // 构造关联与查询条件
        $this->setQuery($query, $params, $singleMember);
        // 总数
        $total = $query->count();
        if ($total > 0 && $showAll) {
            $page = 1;
            $pageSize = $total;
        }

        $last_page = ceil($total / $pageSize); // 总页数
        // 排序
        if ($params['order_by'] === 'active_at') {
            $query->orderBy('finance.active_at', 'desc')->orderBy('finance.created_at', 'desc');
        } else if ($params['order_by'] === 'time' && $time_orderby) {
            $query->orderBy('time', 'desc');
        } else {
            $query->orderBy('finance.created_at', 'desc');
        }
        // 分页
        $a = $query->toSql();
        $query->forPage($page, $pageSize);
        // 查询结果

        $list = $query->get();

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 添加一条财务记录
     * @param $params
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function add($params)
    {
        $financeModel = new FinanceModel();
        $finInfo = [
            'site_id' => $this->siteId,
            'member_id' => $params['member_id'],
            'type' => $params['type'],
            'pay_type' => $params['pay_type'],
            'order_id' => $params['order_id'],
            'is_real' => $params['is_real'],
            'operator' => $params['operator'],
            'terminal_type' => $params['terminal_type'],
            'money' => $params['money'],
            'money_real' => $params['money_real'],
            'created_at' => date('Y-m-d H:i:s'),
            'about' => $params['about'],
            'status' => $params['status'],
            'active_at' => $params['active_at'],
            'snapshot' => $params['snapshot']
        ];
        if ($params['tradeno']) {
            $finInfo['tradeno'] = $params['tradeno'];
        }
        if ($params['sub_type']) {
            $finInfo['sub_type'] = $params['sub_type'];
        }
        if (is_numeric($params['in_type'])) {
            $finInfo['in_type'] = $params['in_type'];
            $finInfo['out_type'] = 0;
        } else {
            $finInfo['out_type'] = $params['out_type'];
            $finInfo['in_type'] = 0;
        }
        $financeModel->fill($finInfo);
        $saveResult = $financeModel->save();
        if ($saveResult) {
            // 重新赋值
            $financeModel = FinanceModel::query()->where('site_id', $this->siteId)->where('id', $financeModel->id)->first();
            // 发送通知
            if ($financeModel && intval($financeModel->status) == Constants::PointStatus_Active) {
                if (intval($financeModel->type) == Constants::FinanceType_Normal) {
                    MessageNoticeHelper::sendMessageBalanceChange($financeModel);
                }
            }
        }
        return [
            'id' => $financeModel->id,
        ];
    }

    /**
     * 查询具体订单情况
     * @param $params
     * @return mixed
     */
    public function getInfo($params)
    {
        $isShowMemberInfo = $params['isShowMemberInfo'] ? true : false; // 是否显示会员信息
        $query = FinanceModel::where(array('tbl_finance.site_id' => $this->siteId, 'tbl_finance.id' => $params['id']));
        $query->leftjoin('tbl_member_withdraw_account as mwa', 'tbl_finance.member_id', '=', 'mwa.member_id');
        if ($isShowMemberInfo) {
            $query->leftjoin('tbl_member as member', 'tbl_finance.member_id', '=', 'member.id');
            $query->addSelect('member.mobile', 'member.nickname as auth_nickname', 'member.headurl','member.name as auth_name');
        }
        $query->addSelect('tbl_finance.*', 'mwa.wx_qrcode', 'mwa.alipay_qrcode', 'mwa.alipay_qrcode', 'mwa.alipay_account', 'mwa.alipay_name', 'mwa.bank_card_name', 'mwa.bank','mwa.bank_branch', 'mwa.bank_account');
        return $query->first();
    }

    /**
     * 根据月份来统计金额
     * @param $params
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public function countByMonth($params)
    {
        $singleMember = $params['single_member'] ? true : false; // 是否针对单个用户，默认False
        $query = FinanceModel::query()->from('tbl_finance as finance');
        // 构造关联与查询条件
        $this->setQuery($query, $params, $singleMember);
        // 重构搜索字段内容
        $query->select(DB::raw("DATE_FORMAT(finance.created_at, '%Y-%m') as date_sign"), DB::raw("sum(finance.money) as money"));
        return $query->groupBy('date_sign')->get();
    }

    /**
     * 统计
     * @param $params
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function count($params)
    {
        $singleMember = $params['single_member'] ? true : false; // 是否针对单个用户，默认False
        $query = FinanceModel::query()->from('tbl_finance as finance');
        // 构造关联与查询条件
        $this->setQuery($query, $params, $singleMember);
        $query->select(DB::raw("count(1) as num"), DB::raw("sum(finance.money) as money"), DB::raw("sum(finance.money_real) as money_real"));
        return $query->first();
    }

    /**
     * 构造关联与查询条件
     * @param Builder $query
     * @param $params
     * @param bool $singleMember
     */
    private function setQuery(Builder $query, $params, $singleMember = false)
    {
        if (!$singleMember) {
            // 关联会员信息
            $query->leftJoin('tbl_member as member', 'finance.member_id', '=', 'member.id');
            $query->addSelect('member.nickname', 'member.mobile', 'member.name');
            // 关联提现帐户信息
            if ($params['withdraw']) {
                $query->leftjoin('tbl_member_withdraw_account as mwa', 'finance.member_id', '=', 'mwa.member_id');
                $query->addSelect('mwa.wx_qrcode', 'mwa.alipay_qrcode', 'mwa.alipay_account', 'mwa.alipay_name', 'mwa.bank_card_name', 'mwa.bank', 'mwa.bank_branch', 'mwa.bank_account');
            }
            // 关联微信信息
            //$query->leftJoin('tbl_member_auth as auth', 'finance.member_id', '=', 'auth.member_id');
            //$query->addSelect('auth.openid', 'auth.nickname as auth_nickname');
            // 会员昵称
            if (trim($params['nickname'])) {
                $query->where('member.nickname', 'like', '%' . trim($params['nickname']) . '%');
            }
            // 会员手机
            if (trim($params['mobile'])) {
                $query->where('member.mobile', 'like', '%' . trim($params['mobile']) . '%');
            }
            // 商家端展示
            if ($params['for_business']) {
                // 排除某些
                $query->where(function ($subQuery) {
                    $subQuery->whereIn('out_type', [
                        Constants::FinanceOutType_PayOrder,
                        Constants::FinanceOutType_CloudStock_PayOrder,
                        Constants::FinanceOutType_Refund,
                        Constants::FinanceOutType_Withdraw,
                        Constants::FinanceOutType_Manual,
                        Constants::FinanceOutType_Give,
                        //Constants::FinanceOutType_AgentInitial,
                        Constants::FinanceOutType_CommissionToBalance,
                        Constants::FinanceOutType_CloudStockGoodsToBalance,
                        Constants::FinanceOutType_CloudStock_TakeDeliver_Fright,
                        Constants::FinanceOutType_AreaAgentCommissionToBalance,
                        Constants::FinanceOutType_DealerPerformanceReward,
                        Constants::FinanceOutType_DealerRecommendReward,
                        Constants::FinanceOutType_DealerSaleReward,
                        Constants::FinanceOutType_SupplierToBalance,
                        Constants::FinanceOutType_DealerOrderReward
                    ])->orWhereIn('in_type', [
                        Constants::FinanceInType_Recharge,
                        Constants::FinanceInType_Trade,
                        Constants::FinanceInType_Manual,
                        Constants::FinanceInType_Bonus,
                        Constants::FinanceInType_Give,
                        Constants::FinanceInType_AgentInitial,
                        Constants::FinanceInType_DealerInitial
                    ])->orWhere(function ($sonQuery) {
                        // 退款到余额，要排除退款到外部时先产生退款到余额的记录
                        $sonQuery->where('in_type', Constants::FinanceInType_Refund)
                            ->where('is_real', 0);
                    })->orWhere(function ($sonQuery2) {
                        // 排除云仓货款
                        $sonQuery2->where('in_type', [Constants::FinanceInType_CloudStockGoods])
                            ->where('type', [Constants::FinanceType_CloudStockPurchase]);
                    })->orWhere(function ($sonQuery3) {
                        // 排除云仓货款
                        $sonQuery3->where('out_type', [Constants::FinanceOutType_Withdraw])
                            ->where('type', [Constants::FinanceType_CloudStock]);
                    });
                });
            }
        }

        if ($params['keyword']) {
            $keyword = $params['keyword'];
            $query->where(function ($query) use ($keyword) {
                $query->orWhere('member.nickname', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('finance.tradeno', 'like', '%' . trim($keyword) . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $query->orWhere('member.mobile', 'like', '%' . trim($keyword) . '%');
                }
            });
        }

        if ($params['withdraw_keyword']) {
            $keyword = $params['withdraw_keyword'];
            $query->where(function ($query) use ($keyword) {
                $query->where('member.nickname', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('finance.id', 'like', '%' . trim($keyword) . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $query->orWhere('member.mobile', 'like', '%' . trim($keyword) . '%');
                }
            });
        }

        if ($params['order_info']) {
            $query->leftJoin('tbl_order as order', 'order.id', '=', 'finance.order_id');
            $query->leftJoin('tbl_member as buyer', 'buyer.id', '=', 'order.member_id');
            $query->addSelect('order.created_at as order_created_at');
            $query->addSelect('buyer.nickname as buyer_nickname');
        }
        // 站点id
        if ($this->siteId > 0) {
            $query->where('finance.site_id', $this->siteId);
        }
        // 会员id
        if ($params['member_id']) {
            $query->where('finance.member_id', intval($params['member_id']));
        }
        // 交易流水号
        if (trim($params['tradeno'])) {
            $query->where('finance.tradeno', 'like', '%' . trim($params['tradeno']) . '%');
        }
        // 金额正负
        if (intval($params['money_sign']) != 0) {
            if (intval($params['money_sign']) > 0) {
                $query->where('finance.money', '>', 0);
            } else {
                $query->where('finance.money', '<', 0);
            }
        }
        // 出账、入账、平账
        if ($params['account_type'] != '' && $params['account_type'] != -99) {
            $accountType = myToArray($params['account_type']);
            if ($accountType && count($accountType) > 0) {
                $query->where(function ($subQuery) use ($accountType) {
                    // 入账
                    if (in_array('1', $accountType)) {
                        $subQuery->orWhere(function ($sonQuery) {
                            $sonQuery->where('finance.is_real', 1)->where('finance.money', '>=', '0');
                        });
                    }
                    // 出账
                    if (in_array('-1', $accountType)) {
                        $subQuery->orWhere(function ($sonQuery) {
                            $sonQuery->where('finance.is_real', 1)->where('finance.money', '<', '0');
                        });
                    }
                    // 平账
                    if (in_array('0', $accountType)) {
                        $subQuery->orWhere(function ($sonQuery) {
                            $sonQuery->where('finance.is_real', 0);
                        });
                    }
                });
            }
        }
        // 类型：普通、赠金、佣金 等
        if (is_numeric($params['types'])) {
            $query->where('finance.type', intval($params['types']));
        } else if ($params['types'] !== null) {
            $types = myToArray($params['types']);
            if (count($types) > 0) {
                $query->whereIn('finance.type', $types);
            }
        }
        // 子类型
        if (is_numeric($params['sub_type']) && intval($params['sub_type']) >= 0) {
            $query->where('finance.sub_type', intval($params['sub_type']));
        }
        // 支付方式：手工入帐、微信、支付宝 等
        if (is_numeric($params['pay_types']) && $params['pay_types'] != -1) {
            $query->where('finance.pay_type', intval($params['pay_types']));
        } else if ($params['pay_types'] !== null && $params['pay_types'] != -1) {
            $payTypes = myToArray($params['pay_types']);
            if (count($payTypes) > 0) {
                $query->whereIn('finance.pay_type', $payTypes);
            }
        }
        // 状态
        if (is_numeric($params['status']) && $params['status'] != -1) {
            $query->where('finance.status', intval($params['status']));
        } else if ($params['status'] != -1) {
            $status = myToArray($params['status']);
            if (count($status) > 0) {
                $query->whereIn('finance.status', $status);
            }
        }
        // 交易时间
        if ($params['created_at_start']) {
            $query->where('finance.created_at', '>=', $params['created_at_start']);
        }
        if ($params['created_at_end']) {
            $query->where('finance.created_at', '<=', $params['created_at_end']);
        }
        // 生效时间
        if ($params['active_at_start']) {
            $query->where('finance.active_at', '>=', $params['active_at_start']);
        }
        if ($params['active_at_end']) {
            $query->where('finance.active_at', '<=', $params['active_at_end']);
        }
        //终端类型
        if ($params['terminal_type'] && intval($params['terminal_type']) > 0) {
            $terminal_type = myToArray($params['terminal_type']);
            if (count($terminal_type) > 0) {
                $query->whereIn('finance.terminal_type', $terminal_type);
            }
        }
        // 交易类型
        if ($params['trade_type'] && intval($params['trade_type']) > 0) {
            $tradeType = intval($params['trade_type']);
            if ($tradeType == 1) {
                // 支付
                $query->where('order_type', Constants::FinanceOrderType_Normal);
                $query->where(function (Builder $subQuery) {
                    $subQuery->where('finance.in_type', Constants::FinanceInType_Trade)
                        ->orWhere('finance.out_type', Constants::FinanceOutType_PayOrder);
                });
            } else if ($tradeType == 2) {
                // 充值
                $query->where('finance.in_type', Constants::FinanceInType_Recharge);
            } else if ($tradeType == 3) {
                // 退款
                $query->where(function (Builder $subQuery) {
                    $subQuery->where('finance.in_type', Constants::FinanceInType_Refund)
                        ->orWhere('finance.out_type', Constants::FinanceOutType_Refund);
                });
            } else if ($tradeType == 4) {
                // 分销佣金提现(至余额)
                $query->where('finance.out_type', Constants::FinanceOutType_CommissionToBalance)
                    ->where('finance.type', Constants::FinanceType_Commission);
            } else if ($tradeType == 5) {
                // 分销佣金提现(至第三方)
                $query->where('finance.out_type', Constants::FinanceOutType_Withdraw)
                    ->where('finance.type', Constants::FinanceType_Commission);
            } else if ($tradeType == 6) {
                // 余额提现(至第三方)
                $query->where('out_type', Constants::FinanceOutType_Withdraw)
                    ->where('finance.type', Constants::FinanceType_Normal);
            } else if ($tradeType == 7) {
                // 会员余额类型
                $query->where(function (Builder $subQuery) {
                    $subQuery->whereIn('finance.in_type', [Constants::FinanceInType_Recharge, Constants::FinanceInType_Bonus, Constants::FinanceInType_Give, Constants::FinanceInType_CommissionToBalance, Constants::FinanceInType_Refund, Constants::FinanceInType_Manual, Constants::FinanceInType_CloudStockGoodsToBalance,Constants::FinanceInType_AreaAgentCommissionToBalance])
                        ->orWhereIn('finance.out_type', [Constants::FinanceOutType_PayOrder, Constants::FinanceOutType_CloudStock_PayOrder, Constants::FinanceOutType_Give, Constants::FinanceOutType_Withdraw, Constants::FinanceOutType_Manual, Constants::FinanceOutType_AgentInitial, Constants::FinanceOutType_DealerInitial, Constants::FinanceOutType_CloudStock_TakeDeliver_Fright, Constants::FinanceOutType_DealerPerformanceReward, Constants::FinanceOutType_DealerRecommendReward, Constants::FinanceOutType_DealerSaleReward, Constants::FinanceOutType_DealerOrderReward]);
                });
            } else if ($tradeType == 8) {
                // 手工充值
                $query->where('in_type', Constants::FinanceInType_Manual)
                    ->where('finance.type', Constants::FinanceType_Normal);
            } else if ($tradeType == 9) {
                // 手工扣减
                $query->where('out_type', Constants::FinanceOutType_Manual)
                    ->where('finance.type', Constants::FinanceType_Normal);
            } else if ($tradeType == 10) {
                // 代理分红提现(至余额)
                $query->where('finance.out_type', Constants::FinanceOutType_CommissionToBalance)
                    ->where('finance.type', Constants::FinanceType_AgentCommission);
            } else if ($tradeType == 11) {
                // 代理分红提现(至第三方)
                $query->where('finance.out_type', Constants::FinanceOutType_Withdraw)
                    ->where('finance.type', Constants::FinanceType_AgentCommission);
            } else if ($tradeType == 12) {
                //代理进货
                $query->where('order_type', LibConstants::CloudStockOrderType_Purchase)
                    ->where(function ($query) {
                        $query->orWhere('finance.out_type', Constants::FinanceOutType_CloudStock_PayOrder)
                            ->orWhere('finance.type', Constants::FinanceType_CloudStockPurchase)
                            ->orWhere('finance.in_type', Constants::FinanceInType_Trade);
                    });
            } else if ($tradeType == 13) {
                //代理加盟费
                $query->where('in_type', Constants::FinanceInType_AgentInitial)
                    ->where('finance.type', Constants::FinanceType_AgentInitial);
            } else if ($tradeType == 14) {
                //云仓收入提现(至余额）
                $query->where('finance.out_type', Constants::FinanceOutType_CloudStockGoodsToBalance)
                    ->where('finance.type', Constants::FinanceType_CloudStock);
            } else if ($tradeType == 15) {
                //云仓收入提现(第三方）
                $query->where('finance.out_type', Constants::FinanceOutType_Withdraw)
                    ->where('finance.type', Constants::FinanceType_CloudStock);
            } else if ($tradeType == 16) {
                //充值赠送
                $query->where('finance.in_type', Constants::FinanceInType_Bonus)
                    ->where('finance.type', Constants::FinanceType_Normal);
            } else if ($tradeType == 17) {
                //余额转现收入
                $query->where('finance.in_type', Constants::FinanceInType_Give)
                    ->where('finance.type', Constants::FinanceType_Normal);
            } else if ($tradeType == 18) {
                //余额转现支出
                $query->whereIn('finance.out_type', [
                    Constants::FinanceOutType_Give,
                    Constants::FinanceOutType_DealerPerformanceReward,
                    Constants::FinanceOutType_DealerRecommendReward,
                    Constants::FinanceOutType_DealerSaleReward,
                    Constants::FinanceOutType_DealerOrderReward
                ])
                    ->where('finance.type', Constants::FinanceType_Normal);
            } else if ($tradeType == 19) {
                //云仓提货运费
                $query->where('finance.order_type', Constants::FinanceOrderType_CloudStock_TakeDelivery);
            } else if ($tradeType == 20) {
                //经销商加盟费
                $query->where('in_type', Constants::FinanceInType_DealerInitial)
                    ->where('finance.type', Constants::FinanceType_DealerInitial);
            } else if ($tradeType == 21) {
                // 区代返佣提现(至余额)
                $query->where('finance.out_type', Constants::FinanceOutType_AreaAgentCommissionToBalance)
                    ->where('finance.type', Constants::FinanceType_AreaAgentCommission);
            } else if ($tradeType == 22) {
                // 区代返佣提现(至第三方)
                $query->where('finance.out_type', Constants::FinanceOutType_Withdraw)
                    ->where('finance.type', Constants::FinanceType_AreaAgentCommission);
            } else if ($tradeType == 23) {
                // 供应提商提现(至余额)
                $query->where('finance.out_type', Constants::FinanceOutType_SupplierToBalance)
                    ->where('finance.type', Constants::FinanceType_Supplier);
            } else if ($tradeType == 24) {
                // 供应提商提现(至第三方)
                $query->where('finance.out_type', Constants::FinanceOutType_Withdraw)
                    ->where('finance.type', Constants::FinanceType_Supplier);
            }

        }
        // 入账类型
        if ($params['in_types'] != '') {
            $inTypes = myToArray($params['in_types']);
            if (count($inTypes) > 0) {
                $query->whereIn('finance.in_type', $inTypes);
            }
        }
        // 出账类型
        if ($params['out_types'] != '') {
            $outTypes = myToArray($params['out_types'], ',', '-1');
            if (count($outTypes) == 1) {
                $query->where('finance.out_type', $outTypes[0]);
            } else if (count($outTypes) > 1) {
                $query->whereIn('finance.out_type', $outTypes);
            }
        }
        // ids
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            if (count($ids) > 0) {
                $query->whereIn('finance.id', $ids);
            }
        }
    }

    /**
     * 转现余额时查找会员
     * @param $mobile 送取余额的对象
     * @param $member_id 转现余额的对象
     * @return array
     */
    public static function balanceGiveSearchMember($mobile, $memberId)
    {
        $site_id = Site::getCurrentSite()->getSiteId();
        $searchMemberData = MemberModel::query()
            ->where('mobile', $mobile)
            ->where('site_id', $site_id)
            ->select('id', 'nickname', 'mobile', 'headurl')
            ->first();
        // 不允许转现给自己，直接返回会员信息，由控制器去判断
        if ($memberId == $searchMemberData->id) return $searchMemberData;
        $config = (new Config($site_id))->getModel();
        if ($searchMemberData->id) {
            //当前会员可转现的余额
            $searchMemberData->balance = moneyCent2Yuan(FinanceHelper::getMemberBalance($memberId));
        }
        // 如果设置了余额对象为下级，需要搜索下级，无限级
        if ($config->balance_give_target == 2) {
            $searchRes = MemberParentsModel::query()
                ->where('parent_id', $memberId)
                ->where('member_id', $searchMemberData->id)
                ->where('site_id', $site_id)
                ->first();
            return $searchRes ? $searchMemberData : false;
        }
        // 如果设置了余额对象为直属下级，需要搜索下级，无限级
        if ($config->balance_give_target == 3) {
            $searchRes = MemberParentsModel::query()
                ->where('parent_id', $memberId)
                ->where('member_id', $searchMemberData->id)
                ->where('site_id', $site_id)
                ->where('level', 1)
                ->first();
            return $searchRes ? $searchMemberData : false;
        }
        return $searchMemberData;
    }

    /**
     * 转现余额
     * @param $inComeMemberId 入帐的对象
     * @param $memberId 出帐的对象
     * @param $money 要转现的金额，单位分
     * @return array
     */
    public static function balanceGive($inComeMemberId, $memberId, $money)
    {
        $site_id = Site::getCurrentSite()->getSiteId();
        // 验证功能状态是否开启
        $config = (new Config($site_id))->getModel();
        if ($config->balance_give_status < 1) {
            throw new \Exception("没有开启余额转现功能");
        }
        $ids = FinanceHelper::Give($inComeMemberId, $memberId, $money);
        foreach ($ids as $item) {
            $financeModel = FinanceModel::find($item);
            MessageNoticeHelper::sendMessageBalanceChange($financeModel);
        }
    }

    /**
     * 此会员是否参加充值优惠活动
     * @param $memberId 查询的会员ID
     * @return Boolean
     */
    public static function MemberJoinShowRechargedDiscount($memberId)
    {
        $res = FinanceModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $memberId)
            ->where('in_type', Constants::FinanceInType_Bonus)
            ->count();
        return $res > 0 ? true : false;
    }

    /**
     * 获取支付配置
     * @param int $type 0=余额充值支付配置，1=零售订单支付配置，2=云仓支付配置，4=代理加盟费支付配置，5=经销商加盟费支付配置
     * @return array
     */
    public static function getPayConfig(int $type)
    {
        if ($type === 0) return static::getRechargePayConfig();
        if ($type === 1) return static::getRetailPayConfig();
        if ($type === 2) return static::getCloudStockPayConfig();
        if ($type === 4) return static::getAgentInitialPayConfig();
        if ($type === 5) return static::getDealerInitialPayConfig();
    }

    /**
     * 获取余额充值的支付方式配置
     * @return array
     * @throws \Exception
     */
    private static function getRechargePayConfig()
    {
        $payConfig = (new PayConfig())->getInfo(true);
        $config = ['types' => []];
        if ($payConfig->type['wxpay']) {
            if ($payConfig->wxpay_online_entrance['pay_balance_recharge']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Weixin,
                    'text' => "微信钱包",
                    'group' => "online",
                    'account' => 'weixin'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxOfficialAccount && getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp && $payConfig->type['alipay']) {
            if ($payConfig->alipay_online_entrance['pay_balance_recharge']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Alipay,
                    'text' => "支付宝",
                    'group' => "online",
                    'account' => 'alipay'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxWork /*&& getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp*/ && $payConfig->type['tlpay']) {
            if ($payConfig->tlpay_online_entrance['pay_balance_recharge']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_TongLian,
                    'text' => "通联支付",
                    'group' => "online",
                    'account' => 'tlpay'
                ];
            }
        }
        if ($payConfig->type['wxpay_offline']) {
            if ($payConfig->wxpay_offline_entrance['pay_balance_recharge']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_WeixinQrcode,
                    'text' => "线下结算-微信",
                    'group' => "offline",
                    'account' => $payConfig->wx_qrcode,
                    'account_name' => "微信收款码"
                ];
            }
        }
        if ($payConfig->type['alipay_offline']) {
            if ($payConfig->alipay_offline_entrance['pay_balance_recharge']) {
                if ($payConfig->alipay_offline_pay_type) {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayQrcode,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $payConfig->alipay_qrcode,
                        'account_name' => "支付宝收款码"
                    ];
                } else {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayAccount,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $payConfig->alipay_account,
                        'account_name' => $payConfig->alipay_name
                    ];
                }
            }
        }
        if ($payConfig->type['bankpay']) {
            if ($payConfig->bank_offline_entrance['pay_balance_recharge']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Bank,
                    'text' => "线下结算-银行账户",
                    'group' => "offline",
                    'account' => $payConfig->bank_account,
                    'account_name' => $payConfig->bank_card_name,
                    'bank' => $payConfig->bank
                ];
            }
        }
        return $config;
    }

    private static function getRetailPayConfig()
    {
        $payConfig = (new PayConfig())->getInfo(true);
        $config = ['types' => []];
        $config['types'][] = [
            'type' => \YZ\Core\Constants::PayType_Balance,
            'text' => "余额",
            'group' => "online",
            'account' => 'balance'
        ];
        if ($payConfig->type['wxpay']) {
            if ($payConfig->wxpay_online_entrance['pay_retail']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Weixin,
                    'text' => "微信钱包",
                    'group' => "online",
                    'account' => 'weixin'
                ];
            }

        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxOfficialAccount && getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp && $payConfig->type['alipay']) {
            if ($payConfig->alipay_online_entrance['pay_retail']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Alipay,
                    'text' => "支付宝",
                    'group' => "online",
                    'account' => 'alipay'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxWork /*&& getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp*/ && $payConfig->type['tlpay']) {
            if ($payConfig->tlpay_online_entrance['pay_retail']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_TongLian,
                    'text' => "通联支付",
                    'group' => "online",
                    'account' => 'tlpay'
                ];
            }
        }
        return $config;
    }

    /**
     * 获取云仓订单的支付方式配置
     * @return array
     * @throws \Exception
     */
    private static function getCloudStockPayConfig()
    {
        $payConfig = (new PayConfig(-1))->getInfo(true);
        $config = ['types' => []];
        $config['types'][] = [
            'type' => \YZ\Core\Constants::PayType_Balance,
            'text' => "余额",
            'group' => "online",
            'account' => 'balance'
        ];
        if ($payConfig->type['wxpay']) {
            if ($payConfig->wxpay_online_entrance['pay_cloudstock_purchase']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Weixin,
                    'text' => "微信钱包",
                    'group' => "online",
                    'account' => 'weixin'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxOfficialAccount && getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp && $payConfig->type['alipay']) {
            if ($payConfig->alipay_online_entrance['pay_cloudstock_purchase']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Alipay,
                    'text' => "支付宝",
                    'group' => "online",
                    'account' => 'alipay'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxWork /*&& getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp*/ && $payConfig->type['tlpay']) {
            if ($payConfig->tlpay_online_entrance['pay_cloudstock_purchase']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_TongLian,
                    'text' => "通联支付",
                    'group' => "online",
                    'account' => 'tlpay'
                ];
            }
        }
        if ($payConfig->type['wxpay_offline']) {
            if ($payConfig->wxpay_offline_entrance['pay_cloudstock_purchase']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_WeixinQrcode,
                    'text' => "线下结算-微信",
                    'group' => "offline",
                    'account' => $payConfig->wx_qrcode
                ];
            }
        }
        if ($payConfig->type['alipay_offline']) {
            if ($payConfig->alipay_offline_entrance['pay_cloudstock_purchase']) {
                if ($payConfig->alipay_offline_pay_type) {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayQrcode,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $payConfig->alipay_qrcode
                    ];
                } else {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayAccount,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $payConfig->alipay_account,
                        'account_name' => $payConfig->alipay_name
                    ];
                }
            }
        }
        if ($payConfig->type['bankpay']) {
            if ($payConfig->bank_offline_entrance['pay_cloudstock_purchase']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Bank,
                    'text' => "线下结算-银行账户",
                    'group' => "offline",
                    'account' => $payConfig->bank_account,
                    'account_name' => $payConfig->bank_card_name,
                    'bank' => $payConfig->bank
                ];
            }
        }
        return $config;
    }

    /**
     * 获取代理加盟费的支付方式配置
     * @return array
     * @throws \Exception
     */
    private static function getAgentInitialPayConfig()
    {
        $payConfig = (new PayConfig())->getInfo(true);
        $config = ['types' => []];
        $config['types'][] = [
            'type' => \YZ\Core\Constants::PayType_Balance,
            'text' => "余额",
            'group' => "online",
            'account' => 'balance'
        ];
        if ($payConfig->type['wxpay']) {
            if ($payConfig->wxpay_online_entrance['pay_initial_money']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Weixin,
                    'text' => "微信钱包",
                    'group' => "online",
                    'account' => 'weixin'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxOfficialAccount && getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp && $payConfig->type['alipay']) {
            if ($payConfig->alipay_online_entrance['pay_initial_money']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Alipay,
                    'text' => "支付宝",
                    'group' => "online",
                    'account' => 'alipay'
                ];
            }
        }
        if (getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxWork /*&& getCurrentTerminal() != \YZ\Core\Constants::TerminalType_WxApp*/ && $payConfig->type['tlpay']) {
            if ($payConfig->tlpay_online_entrance['pay_initial_money']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_TongLian,
                    'text' => "通联支付",
                    'group' => "online",
                    'account' => 'tlpay'
                ];
            }
        }
        if ($payConfig->type['wxpay_offline']) {
            if ($payConfig->wxpay_offline_entrance['pay_initial_money']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_WeixinQrcode,
                    'text' => "线下结算-微信",
                    'group' => "offline",
                    'account' => $payConfig->wx_qrcode
                ];
            }
        }
        if ($payConfig->type['alipay_offline']) {
            if ($payConfig->alipay_offline_entrance['pay_initial_money']) {
                if ($payConfig->alipay_offline_pay_type) {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayQrcode,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $payConfig->alipay_qrcode
                    ];
                } else {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayAccount,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $payConfig->alipay_account,
                        'account_name' => $payConfig->alipay_name
                    ];
                }
            }
        }
        if ($payConfig->type['bankpay']) {
            if ($payConfig->bank_offline_entrance['pay_initial_money']) {
                $config['types'][] = [
                    'type' => \YZ\Core\Constants::PayType_Bank,
                    'text' => "线下结算-银行账户",
                    'group' => "offline",
                    'account' => $payConfig->bank_account,
                    'account_name' => $payConfig->bank_card_name,
                    'bank' => $payConfig->bank
                ];
            }
        }
        return $config;
    }

    /**
     * 获取经销商加盟费的支付方式配置
     * @return array
     * @throws \Exception
     */
    private static function getDealerInitialPayConfig()
    {
        //暂时经销商的加盟费设置与代理加盟费一样
        return static::getAgentInitialPayConfig();
    }
}