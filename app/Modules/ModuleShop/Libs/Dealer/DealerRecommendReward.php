<?php
/**
 * 经销商推荐奖
 * User: liyaohui
 * Date: 2020/1/7
 * Time: 16:01
 */

namespace App\Modules\ModuleShop\Libs\Dealer;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerRecommendRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerRewardModel;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use \YZ\Core\Constants as CoreConstatns;
use YZ\Core\Site\Site;

class DealerRecommendReward implements IDealerReward
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
                ->join('tbl_dealer_recommend_reward as drr', 'drr.reward_id', 'dr.id')
                ->select([
                    'dr.*',
                    'drr.member_dealer_level',
                    'drr.member_dealer_hide_level',
                    'drr.sub_member_id',
                    'drr.sub_member_dealer_level',
                    'drr.sub_member_dealer_hide_level',
                    'drr.reward_type'
                ])
                ->first();
            // 获取等级名称
            $levelIds = [$model->member_dealer_level, $model->sub_member_dealer_level];
            if ($model->member_dealer_hide_level) {
                $levelIds[] = $model->member_dealer_hide_level;
            }
            if ($model->sub_member_dealer_hide_level) {
                $levelIds[] = $model->sub_member_dealer_hide_level;
            }
            $levelNames = DealerLevelModel::query()->where('site_id', $model->site_id)
                ->whereIn('id', $levelIds)
                ->pluck('name', 'id')->toArray();
            if ($levelNames) {
                $model['member_dealer_level_name'] = $levelNames[$model->member_dealer_level];
                $model['sub_member_dealer_level_name'] = $levelNames[$model->sub_member_dealer_level];
                $model['member_dealer_hide_level_name'] = $levelNames[$model->member_dealer_hide_level];
                $model['sub_member_dealer_hide_level_name'] = $levelNames[$model->sub_member_dealer_hide_level];
            }
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
                'type' => Constants::DealerRewardType_Recommend,
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
                'reward_id' => $reward->id,
            ];
            DealerRecommendRewardModel::query()->insert($performanceParam);
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
            ->join('tbl_dealer_recommend_reward as drr', 'drr.reward_id', 'dr.id')
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
            'm.id as member_id',
            'm.mobile as member_mobile',
            'm.headurl as member_headurl',
            'pm.nickname as pay_member_nickname',
            'pm.name as pay_member_name',
            'pm.mobile as pay_member_mobile',
            'pm.headurl as pay_member_headurl',
            'drr.sub_member_id',
            'drr.member_dealer_level',
            'drr.member_dealer_hide_level',
            'drr.sub_member_dealer_level',
            'drr.sub_member_dealer_hide_level',
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
        // 被推荐人经销商等级
        if (is_numeric($param['level'])) {
            if (intval($param['level']) != -1) {
                switch (true) {
                    case $param['level_type'] == 1:
                        $query->where(function ($q) use ($param) {
                            $q->where('drr.member_dealer_level', $param['level'])
                                ->orWhere('drr.member_dealer_hide_level', $param['level']);
                        });
                        break;
                    case $param['level_type'] == 2:
                        $query->where(function ($q) use ($param) {
                            $q->where('drr.sub_member_dealer_level', $param['level'])
                                ->orWhere('drr.sub_member_dealer_hide_level', $param['level']);
                        });
                        break;
                    default:
                        $query->where(function ($q) use ($param) {
                            $q->where('drr.member_dealer_level', $param['level'])
                                ->orWhere('drr.member_dealer_hide_level', $param['level']);
                        });
                }

            }
        }

        // 状态
        if (is_numeric($param['status'])) {
            if (intval($param['status']) != -9) {
                $query->where('dr.status', intval($param['status']));
            }
        }

        // 关键词
        if ($param['keyword']) {
            $keyword = '%' . $param['keyword'] . '%';
            switch (true) {
                case $param['keyword_type'] == 1 :
                    $query->where(function (Builder $subQuery) use ($keyword) {
                        $subQuery->where('m.nickname', 'like', $keyword)
                            ->orWhere('m.mobile', 'like', $keyword)
                            ->orWhere('m.name', 'like', $keyword);
                    });
                    break;
                case $param['keyword_type'] == 2 :
                    $query->where(function (Builder $subQuery) use ($keyword) {
                        $subQuery->where('pm.nickname', 'like', $keyword)
                            ->orWhere('pm.mobile', 'like', $keyword);
                    });
                    break;
                default:
                    $query->where(function (Builder $subQuery) use ($keyword) {
                        $subQuery->where('m.nickname', 'like', $keyword)
                            ->orWhere('m.mobile', 'like', $keyword)
                            ->orWhere('m.name', 'like', $keyword);
                    });
                    break;
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
        // 取出所有需要的等级id
//        $levelIds = $list->map(function ($value, $key) {
//            return [
//                $value['member_dealer_level'],
//                $value['member_dealer_hide_level'],
//                $value['sub_member_dealer_level'],
//                $value['sub_member_dealer_hide_level']
//            ];
//        })->collapse()->unique()->all();
//        // 根据id去查找等级名称
//        $levelNames = [];
//        if ($levelIds) {
//            $levelNames = DealerLevelModel::query()->whereIn('id', $levelIds)->pluck('name', 'id')->toArray();
//        }
        // 取出所有需要的会员id
        $memberIds = $list->map(function ($value, $key) {
            return [
                $value['pay_member_id'],
                $value['sub_member_id']
            ];
        })->collapse()->unique()->all();
        // 查找会员信息
        $memberInfos = [];
        if ($memberIds) {
            $memberInfos = MemberModel::query()->whereIn('id', $memberIds)
                ->select(['id', 'nickname', 'mobile', 'headurl','name'])
                ->get()->keyBy('id')->toArray();
        }

        foreach ($list as &$item) {
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
            $about = json_decode($item['about'], true);
            // 匹配经销商等级信息
            $item['member_dealer_level'] = $about['member_dealer_level_name'];
            $item['member_dealer_hide_level'] = $about['member_dealer_hide_level_name'];
            $item['sub_member_dealer_level'] = $about['sub_member_dealer_level_name'];
            $item['sub_member_dealer_hide_level'] = $about['sub_member_dealer_hide_level_name'];

            // 被推荐人信息
            $item['sub_member_nickname'] = $memberInfos[$item['sub_member_id']]['nickname'];
            $item['sub_member_name'] = $memberInfos[$item['sub_member_id']]['name'];
            $item['sub_member_mobile'] = $memberInfos[$item['sub_member_id']]['mobile'];
            $item['sub_member_headurl'] = $memberInfos[$item['sub_member_id']]['headurl'];
            // 支付人信息
            if ($item['pay_member_id']) {
                $item['pay_member_name'] = $item->pay_member_nickname;
                $item['pay_member_mobile'] = $item->pay_member_mobile;
                $item['pay_member_headurl'] = $item->pay_member_headurl;
            } else {
                $item['pay_member_name'] = '公司';
                $item['pay_member_mobile'] = '';
                $item['pay_member_headurl'] = '';
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
        $dealerRecommendRewardSetting = DealerRecommendRewardSetting::getCurrentSiteSetting();
        // 如果是自动审核的 并且审核人是公司的 自动审核
        if ($dealerRecommendRewardSetting->auto_check && $this->getModel()->pay_member_id == 0) {
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
        return 'JXSTJJ_' . $model->member_id . '_' . $model->sub_member_id;
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
            'sub_type' => CoreConstatns::FinanceSubType_DealerCommission_Recommend,
            'in_type' => CoreConstatns::FinanceInType_Commission,
            'pay_type' => CoreConstatns::PayType_Commission,
            'status' => CoreConstatns::FinanceStatus_Active,
            'order_id' => self::buildFinanceOrderId($reward),
            'tradeno' => 'JXSTJJ_' . date('YmdHis') . '_' . genUuid(8),
            'money' => $reward['reward_money'],
            'money_real' => $reward['reward_money'],
            'created_at' => date('Y-m-d H:i:s'),
            'active_at' => date('Y-m-d H:i:s'),
            'about' => '经销商推荐奖'
        ];

        return $financeData;
    }

    /**
     * 生成支出的财务数据
     * @param array $reward
     * @param string $subNickname 下级会员昵称
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
            'sub_type' => CoreConstatns::FinanceSubType_DealerCommission_SubRecommend,
            'out_type' => CoreConstatns::FinanceOutType_DealerRecommendReward,
            'pay_type' => CoreConstatns::PayType_Balance,
            'status' => CoreConstatns::FinanceStatus_Active,
            'order_id' => self::buildFinanceOrderId($reward),
            'tradeno' => 'JXSTJJ_' . date('YmdHis') . '_' . genUuid(8),
            'money' => -$reward['reward_money'],
            'money_real' => -$reward['reward_money'],
            'created_at' => date('Y-m-d H:i:s'),
            'active_at' => date('Y-m-d H:i:s'),
            'about' => "转现支出-兑换推荐奖金给【{$subNickname}】"
        ];

        return $financeData;
    }

    /**
     * 创建推荐奖
     * @param int $memberId 当前升级的会员id
     * @param int $oldAgentLevel 升级前的等级id
     * @param int $dealerLevel 升级后的等级id
     * @return bool
     */
    public static function createRecommendReward($memberId, $oldAgentLevel, $dealerLevel)
    {
        // 首次升级才会触发推荐奖
        if (!$oldAgentLevel && $dealerLevel > 0) {
            $siteId = getCurrentSiteId();
            // 获取推荐奖配置
            $dealerRecommendRewardSetting = DealerRecommendRewardSetting::getCurrentSiteSetting();
            if (!$dealerRecommendRewardSetting->enable) {
                return false;
            }
            $subMember = MemberModel::query()
                ->where('site_id', $siteId)
                ->where('id', $memberId)
                ->first();
            if ($subMember && $subMember->invite1) {
                $member = MemberModel::query()
                    ->where('site_id', $siteId)
                    ->where('id', $subMember->invite1)
                    ->first();
                // 如果推荐人不是经销商 则不发
                if (!$member->dealer_level) {
                    return false;
                }
                if ($member && $rule = json_decode($dealerRecommendRewardSetting->reward_rule, true)) {
                    // 获取奖金金额
                    $rewardMoney = $rule[$member->dealer_level . '-' . $subMember->dealer_level];
                    // 未设置奖金
                    if (!$rewardMoney) {
                        return false;
                    }
                    $rewardMoney = $rewardMoney['money'];
                    // 获取等级信息
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
                    // 获取支付奖金的人
                    // 如果平级 越级奖为公司支付 则为0
                    if ($dealerRecommendRewardSetting->same_reward_payer == 1) {
                        $payMemberId = $subMember->dealer_parent_id;
                    } else {
                        $payMemberId = 0;
                    }
                    $aboutData = [
                        'member_nickname' => $member->nickname,
                        'member_mobile' => $member->mobile,
                        'member_headurl' => $member->headurl,
                        'sub_member_nickname' => $subMember->nickname,
                        'sub_member_dealer_level_name' => $levelInfo[$subMember->dealer_level]['name'],
                        'sub_member_dealer_hide_level_name' => $subMember->dealer_hide_level ? $levelInfo[$subMember->dealer_hide_level]['name'] : '',
                        'member_dealer_level_name' => $levelInfo[$member->dealer_level]['name'],
                        'member_dealer_hide_level_name' => $member->dealer_hide_level ? $levelInfo[$member->dealer_hide_level]['name'] : '',
                        'member_dealer_level' => $member->dealer_level,
                        'member_dealer_hide_level' => $member->dealer_hide_level,
                        'sub_member_dealer_level' => $subMember->dealer_level,
                        'sub_member_dealer_hide_level' => $subMember->dealer_hide_level,
                        'data_text' => '推荐【' . $subMember->nickname . '】成为【' . $levelInfo[$subMember->dealer_level]['name'] . '】'
                    ];
                    // 不是公司支付 要检测是否是下级奖金 暂时下级奖金是公司支付
                    // 推荐人的等级权重大于被推荐人等级权重 则为下级奖金
                    if ($levelInfo[$member->dealer_level]['weight'] > $levelInfo[$subMember->dealer_level]['weight']) {
                        $payMemberId = 0;
                        $aboutData['type'] = 0; // 下级奖
                        $aboutData['type_text'] = '下级奖';
                    } else if ($levelInfo[$member->dealer_level]['weight'] == $levelInfo[$subMember->dealer_level]['weight']) {
                        $aboutData['type'] = 1; // 平级奖
                        $aboutData['type_text'] = '平级奖';
                    } else {
                        $aboutData['type'] = 2; // 越级奖
                        $aboutData['type_text'] = '越级奖';
                    }

                    $data = [
                        'member_id' => $member->id,
                        'sub_member_id' => $subMember->id,
                        'reward_money' => $rewardMoney,
                        'pay_member_id' => $payMemberId,
                        'reward_type' => $aboutData['type'],
                        'about' => json_encode($aboutData, JSON_UNESCAPED_UNICODE),
                        'member_dealer_level' => $member->dealer_level,
                        'member_dealer_hide_level' => $member->dealer_hide_level,
                        'sub_member_dealer_level' => $subMember->dealer_level,
                        'sub_member_dealer_hide_level' => $subMember->dealer_hide_level,
                    ];
                    return (new static())->add($data);
                }
            }
        }
        return false;
    }
}