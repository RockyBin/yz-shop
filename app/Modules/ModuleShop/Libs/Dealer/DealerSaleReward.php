<?php
/**
 * 经销商销售奖
 * User: liyaohui
 * Date: 2020/1/7
 * Time: 16:01
 */

namespace App\Modules\ModuleShop\Libs\Dealer;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderItemModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerSaleRewardModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use \YZ\Core\Constants as CoreConstatns;
use YZ\Core\Site\Site;

class DealerSaleReward implements IDealerReward
{
    private $_model = null;

    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            $this->findById($idOrModel);
        } else {
            $this->init($idOrModel);
        }
    }

    /**
     * 根据id查找
     * @param $id
     */
    private function findById($id)
    {
        if ($id) {
            $model = DealerRewardModel::query()
                ->from('tbl_dealer_reward as dr')
                ->where('dr.site_id', getCurrentSiteId())
                ->where('dr.id', $id)
                ->join('tbl_dealer_sale_reward as dsr', 'dsr.reward_id', 'dr.id')
                ->select([
                    'dr.*',
                    'dsr.member_dealer_level',
                    'dsr.member_dealer_hide_level',
                    'dsr.sub_member_id',
                    'dsr.sub_member_dealer_level',
                    'dsr.sub_member_dealer_hide_level',
                    'dsr.order_id',
                    'dsr.order_money',
                    'dsr.reward_type',
                    'dsr.order_created_at',
                ])
                ->first();
            // 获取等级名称
//            $levelIds = [$model->member_dealer_level, $model->sub_member_dealer_level];
//            if ($model->member_dealer_hide_level) {
//                $levelIds[] = $model->member_dealer_hide_level;
//            }
//            if ($model->sub_member_dealer_hide_level) {
//                $levelIds[] = $model->sub_member_dealer_hide_level;
//            }
//            $levelNames = DealerLevelModel::query()->where('site_id', $model->site_id)
//                ->whereIn('id', $levelIds)
//                ->pluck('name', 'id')->toArray();
//            if ($levelNames) {
//                $model['member_dealer_level_name'] = $levelNames[$model->member_dealer_level];
//                $model['sub_member_dealer_level_name'] = $levelNames[$model->sub_member_dealer_level];
//                $model['member_dealer_hide_level_name'] = $levelNames[$model->member_dealer_hide_level];
//                $model['sub_member_dealer_hide_level_name'] = $levelNames[$model->sub_member_dealer_hide_level];
//            }
            $this->init($model);
        }
    }

    /**
     * 初始化
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            $this->_model = $model;
        }
    }

    /**
     * 添加数据
     * @param array $param
     * @param bool $reload
     * @return bool|mixed
     */
    public function add(array $param, $reload = false)
    {
        if ($param) {
            $siteId = getCurrentSiteId();
            $time = date('Y-m-d H:i:s');
            $rewardParam = [
                'site_id' => $siteId,
                'member_id' => $param['member_id'],
                'type' => Constants::DealerRewardType_Sale,
                'reward_money' => $param['reward_money'],
                'pay_member_id' => $param['pay_member_id'],
                'about' => $param['about'],
                'created_at' => $time,
                'updated_at' => $time
            ];
            if (array_key_exists('status', $param) && intval($param['status']) == Constants::DealerRewardStatus_Active) {
                $param['verify_at'] = $time;
            }
            // 先生成主记录
            $reward = new DealerRewardModel();
            $reward->fill($rewardParam)->save();
            // 根据主id 生成详细记录
            $performanceParam = [
                'site_id' => $siteId,
                'member_id' => $param['member_id'],
                'sub_member_id' => $param['sub_member_id'],
                'member_dealer_level' => $param['member_dealer_level'],
                'member_dealer_hide_level' => $param['member_dealer_hide_level'],
                'sub_member_dealer_level' => $param['sub_member_dealer_level'],
                'sub_member_dealer_hide_level' => $param['sub_member_dealer_hide_level'],
                'reward_money' => $param['reward_money'],
                'order_money' => $param['order_money'],
                'order_id' => $param['order_id'],
                'order_created_at' => $param['order_created_at'],
                'reward_type' => $param['reward_type'],
                'reward_id' => $reward->id,
            ];
            DealerSaleRewardModel::query()->insert($performanceParam);
            if ($reload) {
                $this->findById($reward->id);
            }
            return $reward->id;
        } else {
            return false;
        }
    }

    /**
     * 列表
     * @param array $param
     * @return array
     */
    public static function getList(array $param)
    {
        $showAll = $param['is_all'] ? true : false;
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;

        $query = DealerRewardModel::query()
            ->from('tbl_dealer_reward as dr')
            ->join('tbl_dealer_sale_reward as dsr', 'dsr.reward_id', 'dr.id')
            ->leftJoin('tbl_member as m', 'm.id', 'dr.member_id')
            ->leftJoin('tbl_member as pm', 'pm.id', 'dr.pay_member_id')
            ->where('dr.site_id', getCurrentSiteId());

        // 搜索条件
        self::setQuery($query, $param);
        // 指定数据
        if ($param['ids']) {
            $ids = myToArray($param['ids']);
            if ($ids) {
                $showAll = true;
                $query->whereIn('dr.id', $ids);
            }
        }
        // 总数据量
        $total = $query->count();
        // 排序
        if ($param['order_by'] && Schema::hasColumn('tbl_dealer_reward', $param['order_by'])) {
            if ($param['order_by_asc']) {
                $query->orderBy('dr.' . $param['order_by']);
            } else {
                $query->orderByDesc('dr.' . $param['order_by']);
            }
        } else {
            $query->orderByDesc('dr.id');
        }
        $query->addSelect('dr.*');
        $query->addSelect([
            'm.nickname as member_nickname',
            'm.name as member_name',
            'm.mobile as member_mobile',
            'm.headurl as member_headurl',
            'm.id as member_id',
            'pm.nickname as pay_member_nickname',
            'pm.mobile as pay_member_mobile',
            'pm.headurl as pay_member_headurl',
            'pm.name as pay_member_name',
            'dsr.order_created_at',
            'dsr.reward_type',
            'dsr.order_id',
            'dsr.order_money'
        ]);
        if ($showAll) {
            $last_page = 1;
        } else {
            $query->forPage($page, $pageSize);
            $last_page = ceil($total / $pageSize);
        }
        $list = $query->get();
        if ($list->count()) {
            self::formatList($list);
        }
        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 查询条件设置
     * @param Builder $query
     * @param array $param
     */
    private static function setQuery(Builder $query, array $param)
    {
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('dr.member_id', intval($param['member_id']));
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('dsr.order_created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('dsr.order_created_at', '<=', $param['created_at_max']);
        }

        // 状态
        if (is_numeric($param['status'])) {
            if (intval($param['status']) != -9) {
                $query->where('dr.status', intval($param['status']));
            }
        }

        // 关键词
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            if ($param['keyword_type'] == 1) {
                $query->where(function (Builder $subQuery) use ($keyword) {
                    $likeKeyword = "%{$keyword}%";
                    $subQuery->where('m.nickname', 'like', $likeKeyword);
                    if (preg_match('/^\w+$/i', $keyword)) {
                        $subQuery->orWhere('m.mobile', 'like', $likeKeyword);
                    }
                });
            } else if ($param['keyword_type'] == 2) {
                $query->where(function (Builder $subQuery) use ($keyword) {
                    $likeKeyword = "%{$keyword}%";
                    $subQuery->where('pm.nickname', 'like', $likeKeyword);
                    if (preg_match('/^\w+$/i', $keyword)) {
                        $subQuery->orWhere('pm.mobile', 'like', $likeKeyword);
                    }
                });
            }

        }

        // 支付奖金
        if (isset($param['payer']) && $param['payer'] != -1) {
            $payer = intval($param['payer']);
            if ($payer > 0) {
                $query->where('pay_member_id', '>', 0);
            } else {
                $query->where('pay_member_id', 0);
            }
        }
    }

    /**
     * 格式化列表数据
     * @param $list
     */
    public static function formatList(&$list)
    {
        foreach ($list as &$item) {
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
            $item['order_money'] = moneyCent2Yuan($item['order_money']);
            // 支付人信息
            if (!$item['pay_member_id']) {
                $item['pay_member_nickname'] = '公司';
                $item['pay_member_mobile'] = '';
            }
            $item['status_text'] = DealerReward::getRewardStatusText($item['status']);
        }
    }

    /**
     * 修改数据
     * @param array $param
     * @param bool $reload
     * @return bool
     */
    public function edit(array $param, $reload = false)
    {
        if ($this->checkExist()) {
            $data = [];
            if (array_key_exists('status', $param)) {
                $status = intval($param['status']);
                if ($status == Constants::DealerRewardStatus_Active) {
                    $data['verify_at'] = date('Y-m-d H:i:s');
                    $data['status'] = Constants::DealerRewardStatus_Active;
                } else if ($status == Constants::DealerRewardStatus_RejectReview) {
                    $data['reason'] = $param['reason'];
                    $data['status'] = Constants::DealerRewardStatus_RejectReview;
                    $data['verify_at'] = date('Y-m-d H:i:s');
                } else if ($status == Constants::DealerRewardStatus_WaitReview) {
                    $data['status'] = Constants::DealerRewardStatus_WaitReview;
                    $data['exchange_at'] = date('Y-m-d H:i:s');
                }
            }
            if (!$data) {
                return false;
            }
            $model = DealerRewardModel::query()->where('id', $this->_model->id)->first();
            $model->fill($data)->save();
            if ($reload) {
                $this->findById($this->_model->id);
            } else {
                foreach ($data as $key => $value) {
                    $this->_model->$key = $value;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 兑换奖金
     * @return mixed|void
     * @throws \Exception
     */
    public function exchange()
    {
        $this->edit(['status' => Constants::DealerRewardStatus_WaitReview]);
        $dealerSaleRewardSetting = DealerSaleRewardSetting::getCurrentSiteSetting();
        // 如果是自动审核的 并且审核人是公司的 自动审核
        if ($dealerSaleRewardSetting->auto_check && $this->getModel()->pay_member_id == 0) {
            $this->pass();
        }
    }

    /**
     * 审核通过
     * @throws \Exception
     */
    public function pass()
    {
        if ($this->checkExist()) {
            // 如果是待审核的
            if (intval($this->getModel()->status) == Constants::DealerRewardStatus_WaitReview) {
                $model = $this->getModel();
                $orderId = self::buildFinanceOrderId($model);
                $financeExist = FinanceModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('member_id', $model->member_id)
                    ->where('type', CoreConstatns::FinanceType_CloudStock)
                    ->where('sub_type', CoreConstatns::FinanceSubType_DealerCommission_Recommend)
                    ->where('order_id', $orderId)
                    ->count();
                if ($financeExist == 0) {
                    // 上级支付时 要从上级账户减去财务
                    if ($model->pay_member_id > 0) {
                        // 检测余额是否足够
                        $blance = FinanceHelper::getMemberBalance($model->pay_member_id);
                        if ($blance < $model->reward_money) {
                            throw new \Exception('您的余额不足，不可通过审核！请充值后再进行审核');
                        }
                        // 获取会员名称 缓存信息用
                        $subNickname = MemberModel::query()
                            ->where('site_id', $model->site_id)
                            ->where('id', $model->member_id)
                            ->value('nickname');
                        $insertOutFinanceData = self::buildOutFinanceData($model, $subNickname);
                        if ($insertOutFinanceData) {
                            $financeModel = new FinanceModel();
                            $financeModel->fill($insertOutFinanceData);
                            $financeModel->save();
                        }
                    }
                    // 增加财务
                    $insertFinanceData = self::buildFinanceData($model);
                    if ($insertFinanceData) {
                        $financeModel = new FinanceModel();
                        $financeModel->fill($insertFinanceData);
                        $financeModel->save();
                        // 发送通知
                        //MessageNoticeHelper::sendMessageAgentCommission($financeModel);
                    }
                    $this->edit(['status' => Constants::DealerRewardStatus_Active]);
                }
            }
        }
    }

    /**
     * 审核不通过
     * @param string $reason
     */
    public function reject($reason = '')
    {
        if ($this->checkExist() && intval($this->getModel()->status) == Constants::DealerRewardStatus_WaitReview) {
            $this->edit([
                'reason' => $reason,
                'status' => Constants::DealerRewardStatus_RejectReview
            ], true);
        }
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        return $this->_model && $this->_model->id ? true : false;
    }

    /**
     * 返回模型数据
     * @return bool|null
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 生成财务订单id
     * @param $model
     * @return string
     */
    public static function buildFinanceOrderId($model)
    {
        return 'JXSXSJ_' . $model->member_id . '_' . $model->order_id;
    }

    /**
     * 生成财务数据
     * @param array $reward
     * @return array|null
     * @throws \Exception
     */
    public static function buildFinanceData($reward)
    {
        if (!$reward) return null;
        $financeData = [
            'site_id' => Site::getCurrentSite()->getSiteId(),
            'member_id' => $reward['member_id'],
            'type' => CoreConstatns::FinanceType_CloudStock,
            'sub_type' => CoreConstatns::FinanceSubType_DealerCommission_Sale,
            'in_type' => CoreConstatns::FinanceInType_Commission,
            'pay_type' => CoreConstatns::PayType_Commission,
            'status' => CoreConstatns::FinanceStatus_Active,
            'order_id' => self::buildFinanceOrderId($reward),
            'tradeno' => 'JXSXSJ_' . date('YmdHis') . '_' . genUuid(8),
            'money' => $reward['reward_money'],
            'money_real' => $reward['reward_money'],
            'created_at' => date('Y-m-d H:i:s'),
            'active_at' => date('Y-m-d H:i:s'),
            'about' => '经销商销售奖'
        ];

        return $financeData;
    }

    /**
     * 生成支出的财务数据
     * @param array $reward
     * * @param string $subNickname 下级会员昵称
     * @return array|null
     * @throws \Exception
     */
    public static function buildOutFinanceData($reward, $subNickname)
    {
        if (!$reward) return null;
        $financeData = [
            'site_id' => Site::getCurrentSite()->getSiteId(),
            'member_id' => $reward['pay_member_id'],
            'type' => CoreConstatns::FinanceType_Normal,
            'sub_type' => CoreConstatns::FinanceSubType_DealerCommission_SubSale,
            'out_type' => CoreConstatns::FinanceOutType_DealerSaleReward,
            'pay_type' => CoreConstatns::PayType_Balance,
            'status' => CoreConstatns::FinanceStatus_Active,
            'order_id' => self::buildFinanceOrderId($reward),
            'tradeno' => 'JXSXSJ_' . date('YmdHis') . '_' . genUuid(8),
            'money' => -$reward['reward_money'],
            'money_real' => -$reward['reward_money'],
            'created_at' => date('Y-m-d H:i:s'),
            'active_at' => date('Y-m-d H:i:s'),
            'about' => "转现支出-兑换销售奖金给【{$subNickname}】"
        ];

        return $financeData;
    }

    /**
     * 创建销售奖
     * @param $order  订单模型
     * @return bool
     */
    public static function createSaleReward($order)
    {
        // 获取销售奖配置
        $dealerSaleRewardSetting = DealerSaleRewardSetting::getCurrentSiteSetting();
        if (!$dealerSaleRewardSetting->enable) {
            return false;
        }
        $siteId = getCurrentSiteId();
        // 获取一遍订单
        $order = CloudStockPurchaseOrderModel::query()
            ->where('site_id', $siteId)
            ->where('id', $order->id)
            ->first();
        if (!$order) {
            return false;
        }
        // 获取下单会员信息
        $subMember = MemberModel::query()
            ->where('site_id', $siteId)
            ->where('id', $order->member_id)
            ->first();
        if ($subMember->invite1) {
            // 是否已经发过奖
            $exist = DealerSaleRewardModel::query()
                ->where('site_id', $siteId)
                ->where('member_id', $subMember->invite1)
                ->where('order_id', $order->id)
                ->count();
            if ($exist) {
                return false;
            }
            // 获取父级的信息
            $member = MemberModel::query()
                ->where('site_id', $siteId)
                ->where('id', $subMember->invite1)
                ->first();
            // 如果推荐人不是经销商 则不发
            if (!$member->dealer_level) {
                return false;
            }
            // 获取对应的经销商等级信息
            $levelIds = [$member->dealer_level, $subMember->dealer_level];
            if ($member->dealer_hide_level) {
                $levelIds[] = $member->dealer_hide_level;
            }
            if ($subMember->dealer_hide_level) {
                $levelIds[] = $subMember->dealer_hide_level;
            }
            $levelInfo = DealerLevelModel::query()
                ->where('site_id', $siteId)
                ->whereIn('id', $levelIds)
                ->get()
                ->keyBy('id');
            // 只有平级和越级才有奖
            if ($levelInfo[$member->dealer_level]['weight'] > $levelInfo[$subMember->dealer_level]['weight']) {
                return false;
            } else if ($levelInfo[$member->dealer_level]['weight'] == $levelInfo[$subMember->dealer_level]['weight']) {
                $rewardType = 0;
            } else {
                $rewardType = 1;
            }
            // 获取对应的销售奖配置
            $rule = json_decode($dealerSaleRewardSetting->reward_rule, true);
            if ($rule) {
                // 获取奖金金额比例
//                $scale = $rule[$member->dealer_level . '-' . $subMember->dealer_level];
//                $rewardMoney = bcmul($order->total_money, ($scale['commission'] / 100));
                $rewardMoney = self::calSaleRewardMoney($order->id, $rule, $member->dealer_level . '-' . $subMember->dealer_level);
                if ($rewardMoney <= 0) {
                    return false;
                }
            } else {
                return false;
            }
            // 奖金支付人
            // 如果平级 越级奖为公司支付 则为0
            if ($dealerSaleRewardSetting->payer == 1) {
                $payMemberId = $subMember->dealer_parent_id;
            } else {
                $payMemberId = 0;
            }
            $rewardTypeText = $rewardType == 0 ? '平级奖' : '越级奖';
            $aboutData = [
                'member_nickname' => $member->nickname,
                'member_mobile' => $member->mobile,
                'member_headurl' => $member->headurl,
                'type' => $rewardType,
                'type_text' => $rewardTypeText,
                'sub_member_nickname' => $subMember->nickname,
                'sub_member_dealer_level_name' => $levelInfo[$subMember->dealer_level]['name'],
                'sub_member_dealer_hide_level_name' => $subMember->dealer_hide_level ? $levelInfo[$subMember->dealer_hide_level]['name'] : '',
                'member_dealer_level_name' => $levelInfo[$member->dealer_level]['name'],
                'member_dealer_hide_level_name' => $member->dealer_hide_level ? $levelInfo[$member->dealer_hide_level]['name'] : '',
                'member_dealer_level' => $member->dealer_level,
                'member_dealer_hide_level' => $member->dealer_hide_level,
                'sub_member_dealer_level' => $subMember->dealer_level,
                'sub_member_dealer_hide_level' => $subMember->dealer_hide_level,
                'order_id' => $order->id,
                'data_text' => '来自-【' . $subMember->nickname . '】的' . $rewardTypeText
            ];
            $data = [
                'member_id' => $member->id,
                'reward_money' => intval($rewardMoney),
                'pay_member_id' => $payMemberId,
                'sub_member_id' => $subMember->id,
                'about' => json_encode($aboutData, JSON_UNESCAPED_UNICODE),
                'member_dealer_level' => $member->dealer_level,
                'member_dealer_hide_level' => $member->dealer_hide_level,
                'sub_member_dealer_level' => $subMember->dealer_level,
                'sub_member_dealer_hide_level' => $subMember->dealer_hide_level,
                'order_money' => $order->total_money,
                'order_id' => $order->id,
                'reward_type' => $rewardType,
                'order_created_at' => $order->created_at,
            ];
            return (new static())->add($data);
        }
        return false;
    }

    /**
     * 获取订单的销售奖金额
     * @param $orderId  订单id
     * @param $setting  系统奖金设置
     * @param $level 对应的等级level-level
     * @return int|string
     */
    public static function calSaleRewardMoney($orderId, $setting, $level)
    {
        // 先取出所有订单中商品的销售奖规则
        $skusInfo = CloudStockPurchaseOrderItemModel::query()
            ->from('tbl_cloudstock_purchase_order_item as oi')
            ->leftJoin('tbl_product_skus as sku', 'sku.id', 'oi.sku_id')
            ->where('oi.order_id', $orderId)
            ->where('oi.site_id', getCurrentSiteId())
            ->select(['oi.money', 'oi.sku_id', 'sku.dealer_sale_reward_rule', 'oi.num'])
            ->get();
        if (!$skusInfo) return 0;
        // 取出自定义销售奖规则的id
        $ruleIds = $skusInfo->where('dealer_sale_reward_rule', '>', 0)->pluck('dealer_sale_reward_rule')->toArray();
        $ruleInfo = [];
        if ($ruleIds) {
            $ruleInfo = ProductPriceRuleModel::query()
                ->where('site_id', getCurrentSiteId())
                ->whereIn('id', $ruleIds)
                ->where('type', Constants::ProductPriceRuleType_DealerSaleReward)
                ->select(['rule_info', 'id'])
                ->pluck('rule_info', 'id')->toArray();
        }
        $rewardMoney = 0;
        foreach ($skusInfo as $item) {
            $ruleId = $item->dealer_sale_reward_rule;
            // 关闭了销售奖
            if ($ruleId == -1) continue;
            // 自定义销售奖
            if ($ruleId > 0) {
                if (
                    $ruleInfo[$ruleId]
                    && $scal = json_decode($ruleInfo[$ruleId], true)
                ) {
                    if (isset($scal['rule']['commission'][$level])) {
                        // 为空或者为0 的时候 没有销售奖
                        if (!$scal['rule']['commission'][$level]) {
                            continue;
                        }
                        // 固定金额
                        if ($scal['amountType'] == 1) {
                            $rewardMoney = bcadd($scal['rule']['commission'][$level] * $item->num, $rewardMoney);
                        } else {
                            $rewardMoney = bcadd(bcmul($item->money, ($scal['rule']['commission'][$level] / 100)), $rewardMoney);
                        }
                        continue;
                    }
                    // 没有设置相关自定义数据 使用系统默认的
                    $ruleId = 0;
                }
            }
            // 默认销售奖
            if ($ruleId == 0) {
                if (!$setting[$level]['commission']) continue;
                $rewardMoney = bcadd(bcmul($item->money, ($setting[$level]['commission'] / 100)), $rewardMoney);
            }
        }
        return $rewardMoney;
    }
}