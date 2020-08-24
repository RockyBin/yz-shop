<?php
/**
 * 经销商奖金通用业务逻辑
 * User: liyaohui
 * Date: 2020/1/4
 * Time: 14:02
 */

namespace App\Modules\ModuleShop\Libs\Dealer;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerRecommendRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerSaleRewardModel;
use App\Modules\ModuleShop\Libs\VerifyLog\VerifyLog;
use Illuminate\Support\Carbon;
use YZ\Core\Model\MemberModel;

class DealerReward
{
    protected $siteId = 0;
    protected $model = null;

    /**
     * DealerReward constructor.
     * @param $idOrModel
     * @throws \Exception
     */
    public function __construct($idOrModel)
    {
        $this->siteId = getCurrentSiteId();
        $this->init($idOrModel);
    }

    /**
     * 初始化
     * @param int|DealerRewardModel $idOrModel
     * @throws \Exception
     */
    private function init($idOrModel)
    {
        if (is_numeric($idOrModel)) {
            $model = DealerRewardModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $idOrModel)
                ->first();
            if (!$model) {
                throw new \Exception('数据不存在');
            }
            $this->model = $model;
        } else if ($idOrModel instanceof DealerRewardModel) {
            $this->model = $idOrModel;
        } else {
            throw new \Exception('数据错误');
        }
    }

    /**
     * 获取奖金模型
     * @return null|DealerRewardModel
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * 获取我的兑换奖金列表
     * @param $memberId
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @param int $getCount     是否获取统计数据
     * @return array
     */
    public static function getMyRewardList($memberId, $params = [], $page = 1, $pageSize = 20, $getCount = 0)
    {
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        // 获取基本列表
        $query = DealerRewardModel::query()
            ->from('tbl_dealer_reward as dr')
            ->leftJoin('tbl_member as m', 'm.id', 'dr.pay_member_id')
            ->where('dr.site_id', getCurrentSiteId())
            ->where('dr.member_id', $memberId);
        // 筛选条件
        if (isset($params['status'])) {
            $query->where('dr.status', intval($params['status']));
        }
        $data = [];
        if ($getCount) {
            $data['count_money'] = moneyCent2Yuan($query->sum('dr.reward_money'));
        }
        // 分页等处理
        $list = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->orderByDesc('dr.id')
            ->select(['dr.*', 'm.nickname as pay_member_name'])
            ->get();
        $hasNextPage = false; // 是否有下一页
        if ($list->count() > $pageSize) {
            $hasNextPage = true;
            $list->pop();
        }

        foreach ($list as &$item) {
            $item['pay_member_name'] = $item['pay_member_name'] ?: '公司';
            $item['type_text'] = self::getRewardTypeText($item['type']);
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
            $item['about'] = json_decode($item['about'], true);
        }
        return array_merge($data, [
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'list' => $list
        ]);
    }

    /**
     * 获取奖金类型文案
     * @param $type
     * @return string
     */
    public static function getRewardTypeText($type)
    {
        switch ($type) {
            case Constants::DealerRewardType_Performance:
                return '业绩奖';
            case Constants::DealerRewardType_Recommend:
                return '推荐奖';
            case Constants::DealerRewardType_Sale:
                return '销售奖';
            default:
                return '未知';
        }
    }

    /**
     * 兑换奖金
     * @throws \Exception
     */
    public function exchange()
    {
        $reward = DealerRewardHelper::createInstance($this->model->id, $this->model->type);
        $reward->exchange();
        $this->verifyLogHandle($reward);
    }

    public function getVerifyLogType($type)
    {
        switch ($type) {
            case Constants::DealerRewardType_Performance:
                return Constants::VerifyLogType_DealerPerformanceReward;
                break;
            case Constants::DealerRewardType_Recommend:
                return Constants::VerifyLogType_DealerRecommendReward;
                break;
            case Constants::DealerRewardType_Sale:
                return Constants::VerifyLogType_DealerSaleReward;
                break;
            case Constants::DealerRewardType_Order:
                return Constants::VerifyLogType_DealerOrderReward;
                break;
        }
    }

    /**
     * 处理审核日志
     * @param IDealerReward $reward
     * @throws \Exception
     */
    public function verifyLogHandle($reward)
    {
        // 审核记录处理
        VerifyLog::Log($this->getVerifyLogType($this->model->type), $reward->getModel());
    }

    /**
     * 获取需要自己审核的列表
     * @param $memberId
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getSubRewardList($memberId, $params = [], $page = 1, $pageSize = 20)
    {
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        // 获取基本列表
        $query = DealerRewardModel::query()
            ->from('tbl_dealer_reward as dr')
            ->leftJoin('tbl_member as m', 'm.id', 'dr.member_id')
            ->where('dr.site_id', getCurrentSiteId())
            ->where('dr.pay_member_id', $memberId);
        // 筛选条件
        if (isset($params['status'])) {
            if (intval($params['status']) == 0) {
                $query->where('dr.status', Constants::DealerRewardStatus_WaitReview);
            } else {
                // 审核过的包括生效和拒绝
                $query->whereIn('dr.status', [
                    Constants::DealerRewardStatus_Active,
                    Constants::DealerRewardStatus_RejectReview
                ]);
            }
        }
        // 分页等处理
        $list = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->orderByDesc('dr.id')
            ->select(['dr.*', 'm.nickname as member_name', 'm.headurl'])
            ->get();
        $hasNextPage = false; // 是否有下一页
        if ($list->count() > $pageSize) {
            $hasNextPage = true;
            $list->pop();
        }
        // 格式化数据
        foreach ($list as &$item) {
            $item['type_text'] = self::getRewardTypeText($item['type']) . '金审核';
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
        }
        return [
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'list' => $list
        ];
    }

    /**
     * 通过审核
     * @throws \Exception
     */
    public function pass()
    {
        $reward = DealerRewardHelper::createInstance($this->model->id, $this->model->type);
        $reward->pass();
        $this->verifyLogHandle($reward);
    }

    /**
     * 拒绝
     * @param string $reason 拒绝原因
     * @throws \Exception
     */
    public function reject($reason = '')
    {
        $reward = DealerRewardHelper::createInstance($this->model->id, $this->model->type);
        $reward->reject($reason);
        $this->verifyLogHandle($reward);
    }

    /**
     * 获取奖金详情
     * @return mixed
     */
    public function getInfo()
    {
        $data = $this->model->toArray();
        // 获取审核人和会员昵称
        $memberInfo = MemberModel::query()
            ->where('site_id', $this->siteId)
            ->whereIn('id', [$data['pay_member_id'], $data['member_id']])
            ->select(['nickname', 'id', 'mobile', 'headurl','name'])
            ->get()
            ->keyBy('id');
        $data['member_nickname'] = $memberInfo[$data['member_id']]['nickname'];
        $data['member_headurl'] = $memberInfo[$data['member_id']]['headurl'];
        $data['member_mobile'] = $memberInfo[$data['member_id']]['mobile'];
        $data['member_name'] = $memberInfo[$data['member_id']]['name'];
        if ($data['pay_member_id']) {
            $data['pay_member_mobile'] = $memberInfo[$data['pay_member_id']]['mobile'];
            $data['pay_member_nickname'] = $memberInfo[$data['pay_member_id']]['nickname'];
            $data['pay_member_headurl'] = $memberInfo[$data['pay_member_id']]['headurl'];
            $data['pay_member_name'] = $memberInfo[$data['pay_member_id']]['name'];
        } else {
            $data['pay_member_mobile'] = '';
            $data['pay_member_nickname'] = '公司';
            $data['pay_member_headurl'] = '';
            $data['pay_member_name'] = '';
        }

        $data['reward_money'] = moneyCent2Yuan($data['reward_money']);
        $data['about'] = json_decode($data['about'], true);
        return $data;
    }

    /**
     * 获取会员的奖金统计
     * @param $memberId
     * @param bool $toYuan 是否需要转为元
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getMemberReward($memberId, $toYuan = true)
    {
        $count = DealerRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->where('status', Constants::DealerRewardStatus_Active)
            ->selectRaw('sum(if(type=?,reward_money,0)) as performanceCount,
                sum(if(type=?,reward_money,0)) as recommendCount,
                sum(if(type=?,reward_money,0)) as saleCount', [
                Constants::DealerRewardType_Performance,
                Constants::DealerRewardType_Recommend,
                Constants::DealerRewardType_Sale
            ])
            ->first();
        if ($toYuan) {
            $count['performanceCount'] = moneyCent2Yuan($count['performanceCount']);
            $count['recommendCount'] = moneyCent2Yuan($count['recommendCount']);
            $count['saleCount'] = moneyCent2Yuan($count['saleCount']);
        }
        return $count;
    }

    /**
     * 获取会员支出奖金统计
     * @param $memberId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getMemberOutReward($memberId)
    {
        $count = DealerRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('pay_member_id', $memberId)
            ->where('status', Constants::DealerRewardStatus_Active)
            ->selectRaw('sum(if(type=?,reward_money,0)) as performanceCount,
                sum(if(type=?,reward_money,0)) as recommendCount,
                sum(if(type=?,reward_money,0)) as saleCount', [
                Constants::DealerRewardType_Performance,
                Constants::DealerRewardType_Recommend,
                Constants::DealerRewardType_Sale
            ])
            ->first();
        $count['performanceCount'] = moneyCent2Yuan($count['performanceCount']);
        $count['recommendCount'] = moneyCent2Yuan($count['recommendCount']);
        $count['saleCount'] = moneyCent2Yuan($count['saleCount']);
        return $count;
    }

    /**
     * 获取会员的奖金收入列表
     * @param int $memberId
     * * @param int $type
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getRewardList($memberId, $type, $params = [], $page = 1, $pageSize = 20)
    {
        $list = DealerRewardModel::query()
            ->from('tbl_dealer_reward as dr')
            ->leftJoin('tbl_member as m', 'm.id', 'dr.pay_member_id')
            ->where('dr.site_id', getCurrentSiteId())
            ->where('dr.status', Constants::DealerRewardStatus_Active)
            ->where('dr.type', intval($type))
            ->where('dr.member_id', $memberId);
        if (isset($params['year']) && $year = intval($params['year'])) {
            if (isset($params['month']) && $month = intval($params['month'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($year . '-' . $month));
                $endDate = date('Y-m-t 23:59:59', strtotime($year . '-' . $month));
            } else {
                $startDate = "{$year}-01-01 00:00:00";
                $endDate = "{$year}-12-31 23:59:59";
            }
            $list->where('dr.created_at', '>=', $startDate)
                ->where('dr.created_at', '<=', $endDate);
        }
        $data = [];
        if (isset($params['get_count']) && $params['get_count']) {
            $countQuery = clone $list;
            $count = $countQuery->selectRaw('sum(dr.reward_money) as reward_money, count(*) as reward_count')
                ->first();
            $count['reward_money'] = moneyCent2Yuan($count['reward_money']);
            $data['count'] = $count;
        }
        $list = $list->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->orderByDesc('dr.id')
            ->select(['dr.*', 'm.nickname as pay_member_name'])
            ->get();

        $hasNextPage = false; // 是否有下一页
        if ($list->count() > $pageSize) {
            $hasNextPage = true;
            $list->pop();
        }
        foreach ($list as &$item) {
            if (in_array($type, [Constants::DealerRewardType_Recommend, Constants::DealerRewardType_Sale])) {
                $item['pay_member_name'] = $item['pay_member_name'] ?: '公司';
            }
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
            $item['about'] = json_decode($item['about'], true);
        }
        return array_merge([
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'list' => $list
        ], $data);
    }

    /**
     * 获取会员的奖金支出列表
     * @param int $memberId
     * @param int $type
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getOutRewardList($memberId, $type, $params = [], $page = 1, $pageSize = 20)
    {
        $list = DealerRewardModel::query()
            ->from('tbl_dealer_reward as dr')
            ->leftJoin('tbl_member as m', 'm.id', 'dr.member_id')
            ->where('dr.site_id', getCurrentSiteId())
            ->where('dr.status', Constants::DealerRewardStatus_Active)
            ->where('dr.type', intval($type))
            ->where('dr.pay_member_id', $memberId);
        if (isset($params['year']) && $year = intval($params['year'])) {
            if (isset($params['month']) && $month = intval($params['month'])) {
                $startDate = date('Y-m-d 00:00:00', strtotime($year . '-' . $month));
                $endDate = date('Y-m-t 23:59:59', strtotime($year . '-' . $month));
            } else {
                $startDate = "{$year}-01-01 00:00:00";
                $endDate = "{$year}-12-31 23:59:59";
            }
            $list->where('dr.created_at', '>=', $startDate)
                ->where('dr.created_at', '<=', $endDate);
        }
        $data = [];
        if (isset($params['get_count']) && $params['get_count']) {
            $countQuery = clone $list;
            $count = $countQuery->selectRaw('sum(dr.reward_money) as reward_money, count(*) as reward_count')
                ->first();
            $count['reward_money'] = moneyCent2Yuan($count['reward_money']);
            $data['count'] = $count;
        }
        $list = $list->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->orderByDesc('dr.id')
            ->select(['dr.*', 'm.nickname as member_name'])
            ->get();

        $hasNextPage = false; // 是否有下一页
        if ($list->count() > $pageSize) {
            $hasNextPage = true;
            $list->pop();
        }
        foreach ($list as &$item) {
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
            $item['about'] = json_decode($item['about'], true);
        }
        return array_merge([
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'list' => $list
        ], $data);
    }

    /**
     * 获取经销商的奖金结算金额统计
     * @param int $memberId 会员id
     * @param int $type     类型
     * @return mixed
     */
    public static function getRewardCount($memberId, $type)
    {
        return DealerRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->where('status', Constants::DealerRewardStatus_Active)
            ->where('type', intval($type))
            ->selectRaw('sum(reward_money) as reward_money, count(*) as reward_count')
            ->first();
    }

    /**
     * 获取经销商的奖金支出结算统计
     * @param int $memberId 会员id
     * @param int $type     类型
     * @return mixed
     */
    public static function getOutRewardCount($memberId, $type)
    {
        return DealerRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('pay_member_id', $memberId)
            ->where('status', Constants::DealerRewardStatus_Active)
            ->where('type', intval($type))
            ->selectRaw('sum(reward_money) as reward_money, count(*) as reward_count')
            ->first();
    }

    /**
     * 获取奖金状态文案
     * @param $status
     * @return string
     */
    public static function getRewardStatusText($status)
    {
        switch ($status) {
            case Constants::DealerRewardStatus_WaitExchange:
                return '待兑换';
            case Constants::DealerRewardStatus_WaitReview:
                return '待审核';
            case Constants::DealerRewardStatus_Active:
                return '已发放';
            case Constants::DealerRewardStatus_RejectReview:
                return '已拒绝';
            default:
                return '未知状态';
        }
    }

    /**
     * 获取是否有待兑换或待审核的奖金
     * @param $memberId
     * @return bool
     */
    public static function hasNeedVerifyOrExchangeReward($memberId)
    {
        // 是否有待兑换或待审核的奖金
        $waitExchange = DealerRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->whereIn('status', [Constants::DealerRewardStatus_WaitExchange, Constants::DealerRewardStatus_WaitReview])
            ->first();
        if ($waitExchange) return true;
        // 是否有待审核的奖金
//        $wartReview = DealerRewardModel::query()
//            ->where('site_id', getCurrentSiteId())
//            ->where('pay_member_id', $memberId)
//            ->where('status', Constants::DealerRewardStatus_WaitReview)
//            ->first();
//        if ($wartReview) return true;
        return false;
    }

}