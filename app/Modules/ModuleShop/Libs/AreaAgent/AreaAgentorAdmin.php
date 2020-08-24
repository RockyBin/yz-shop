<?php
/**
 * 区域代理后台逻辑类
 * User: liyaohui
 * Date: 2020/6/2
 * Time: 18:42
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\AreaAgentMessageNotice;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplyModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\OrderAreaAgentHistoryModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Model\DistrictModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;

class AreaAgentorAdmin
{
    protected $siteId = 0;
    protected $memberId = 0;
    protected $memberModel = null;
    public function __construct($memberId)
    {
        $this->siteId = getCurrentSiteId();
        if (is_numeric($memberId)) {
            $this->memberId = $memberId;
        } else {
            throw new \Exception('数据错误');
        }
    }

    /**
     * 获取会员模型
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getMemberModel()
    {
        if ($this->memberModel === null) {
            $this->memberModel = MemberModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $this->memberId)
                ->first();
            if (!$this->memberModel) {
                throw new \Exception('会员不存在');
            }
        }
        return $this->memberModel;
    }

    /**
     * 获取区代列表
     * @param $params
     * @return array
     */
    public static function getList($params)
    {
        $siteId = getCurrentSiteId();
        $query = MemberModel::query()
            ->from('tbl_member as member')
            ->leftJoin('tbl_area_agent as agent', 'member.id', 'agent.member_id')
            ->where('member.site_id', $siteId);
//            ->groupBy('member.id');
        // 查询条件
        // 手机号 昵称搜索
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $query->where(function ($query) use ($keyword) {
                $query->where('member.nickname', 'like', $keyword);
                $query->orWhere('member.name', 'like', $keyword);
                $query->orWhere('member.mobile', 'like', $keyword);
            });
        }
        // 区域级别
        if (isset($params['area_type'])) {
            if (is_array($params['area_type'])) {
                $query->whereIn('agent.area_type', $params['area_type']);
            } else {
                $query->where('agent.area_type', $params['area_type']);
            }
        }
        // 代理区域搜索
        if (isset($params['prov']) && $params['prov']) {
            $query->where('agent.prov', $params['prov']);
        }
        if (isset($params['city']) && $params['city']) {
            $query->where('agent.city', $params['city']);
        }
        if (isset($params['district']) && $params['district']) {
            $query->where('agent.district', $params['district']);
        }
        // 状态
        if (isset($params['status']) && $params['status'] != 0) {
            $query->where('member.is_area_agent', $params['status']);
        } else {
            $query->whereIn('member.is_area_agent', [AreaAgentConstants::AreaAgentStatus_Cancel, AreaAgentConstants::AreaAgentStatus_Active]);
        }
        // 成为区代时间
        if (isset($params['created_at_start'])) {
            $query->where('member.area_agent_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $query->where('member.area_agent_at', '<=', $params['created_at_end']);
        }
        $page = $params['page'] ?: 1;
        $pageSize = $params['page_size'] ?: 20;
        // 统计数据条数
        // 查询下级列表不需要去重
        if ($params['is_sub_list']) {
            $total = $query->count();
            $query->select('member.id as member_id');
        } else {
            $total = (clone $query)->selectRaw('count(distinct member.id) as total')->first();
            $total = $total ? $total['total'] : 0;
            $query->selectRaw('distinct member.id as member_id');
        }
        $lastPage = ceil($total / $pageSize);
        $list = $query->orderByDesc('member.area_agent_at')
            ->addSelect([
                'member.nickname',
                'member.mobile as member_mobile',
                'member.headurl',
                'member.name',
                'member.area_agent_at as created_at',
                'member.is_area_agent as status'
             ])
            ->forPage($page, $pageSize)
            ->get();

        if ($list->isNotEmpty()) {
            $list = self::areaAgentListFormat($list);
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    /**
     * 列表数据处理
     * @param $list
     * @return mixed
     */
    public static function areaAgentListFormat($list)
    {
        // 会员id
        $memberIds = $list->pluck('member_id')->toArray();
        // 获取佣金收益
        $finance = FinanceModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('type', Constants::FinanceType_AreaAgentCommission)
            ->whereIn('member_id', $memberIds)
            ->groupBy('member_id')
            ->selectRaw('
                    member_id,
                    sum(if(`status`=? and money>0,money,0)) as total,
                    sum(if(`status`=? and money>0,money,0) + if(`status`<>? and money<0,money,0)) as can_use',
                [
                    Constants::FinanceStatus_Active,
                    Constants::FinanceStatus_Active,
                    Constants::FinanceStatus_Invalid
                ]
            )
            ->get()->keyBy('member_id')->toArray();
        // 获取代理的所有区域
        $areaAgents = AreaAgentHelper::getMemberAreaAgentList($memberIds);

        // 匹配给列表
        foreach ($list as $value) {
            // 代理区域匹配
            $value->area_list = $areaAgents->where('member_id', $value['member_id'])->values()->all();
            // 佣金匹配
            $value->commission_total = moneyCent2Yuan($finance[$value['member_id']]['total']);
            $value->commission_can_use = moneyCent2Yuan($finance[$value['member_id']]['can_use']);
            $value->member_mobile = Member::memberMobileReplace($value->member_mobile);
        }
        return $list;
    }

    /**
     * 添加代理
     * @param int $memberId     会员id
     * @param array $areaInfo   新增的区域信息 [['area_type' => 9, 'prov' => 1111, 'city' => 11, 'district' => 222]];
     * @return array
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function addAreaAgent($memberId, $areaInfo = [])
    {
        try {
            if (!$areaInfo || !is_array($areaInfo)) {
                throw new \Exception('数据错误');
            }
            DB::beginTransaction();
            $siteId = getCurrentSiteId();
            $memberModel = MemberModel::query()
                ->where('site_id', $siteId)
                ->where('id', $memberId)
                ->first();
            $coreMember = new \YZ\Core\Member\Member($memberModel);
            // 会员是否存在
            if (!$coreMember->checkExist()) {
                throw new \Exception("会员不存在");
            }
            // 是否绑定了手机号
            $coreMember->checkBindMobile();
            // 已经是区代
            if ($memberModel['is_area_agent']  == AreaAgentConstants::AreaAgentStatus_Active) {
                return makeServiceResult(412, '该会员已经是区域代理');
            }
            // 禁用中
            if ($memberModel['is_area_agent'] == AreaAgentConstants::AreaAgentStatus_Cancel) {
                return makeServiceResult(410, '区域代理被禁用');
            }

            // 是否有申请中的记录
            $apply = AreaAgentApplyModel::query()
                ->where('site_id', $siteId)
                ->where('member_id', $memberId)
                ->where('status', AreaAgentConstants::AreaAgentStatus_WaitReview)
                ->first();
            if ($apply) {
                return makeServiceResult(411, '有申请中的记录', ['apply_id' => $apply['id']]);
            }
            self::saveNewAreaAgent($memberModel, $areaInfo);
            // 更新会员
            $member = new Member($memberModel);
            $member->edit([
                'is_area_agent' => AreaAgentConstants::AreaAgentStatus_Active,
                'area_agent_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            // 获取一条区代记录 用来发送消息
            $agent = AreaAgentModel::query()
                ->where('site_id', $siteId)
                ->where('member_id', $memberId)
                ->first();
            AreaAgentMessageNotice::sendMessageAreaAgentAgree($agent);
            return makeServiceResult(200, 'ok');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 保存新区代数据 没有使用事务 请在外层使用事务
     * @param \Illuminate\Database\Eloquent\Model|null|object|static $member  会员模型
     * @param array $areaInfo     新增的区域信息 [['area_type' => 9, 'prov' => 1111, 'city' => 11, 'district' => 222]];
     * @param int $status   新增区域的状态
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function saveNewAreaAgent($member, $areaInfo, $status = AreaAgentConstants::AreaAgentStatus_Active)
    {
        $siteId = getCurrentSiteId();
        $memberId = $member['id'];
        $areaInfoCollect = collect($areaInfo);
        // 检测区域类型
        $areaTypes = $areaInfoCollect->pluck('area_type')->values()->toArray();
        if (array_diff($areaTypes, AreaAgentConstants::getAreaAgentAllLevel())) {
            throw new \Exception('区域类型错误');
        }
        // 检测区域是否已有代理存在
        if (AreaAgentHelper::checkAreaAgentExist($areaInfo)) {
            throw new \Exception('所选区域已有代理');
        }
        $checkAreaActive = self::checkAreaActive($areaInfo);
        if (!$checkAreaActive) {
            throw new \Exception('请选择正确的区域');
        }
        // 检测已有区域和所选区域 是否有重复
        $checkAreas = $areaInfo;
        // 如果已经是区代 需要把已代理的区域拿出来 一起检测是否可以申请
        if ($member['is_area_agent'] == AreaAgentConstants::AreaAgentStatus_Active) {
            $existAreas = AreaAgentModel::query()
                ->where('site_id', $siteId)
                ->where('member_id', $memberId)
                ->select(['area_type', 'prov', 'city', 'district'])
                ->get()->toArray();
            if ($existAreas) {
                $checkAreas = array_merge($existAreas, $checkAreas);
            }
        }
        if (AreaAgentHelper::checkAreaIsRepeat($checkAreas)) {
            throw new \Exception('所选区域有重合');
        }
        // 默认等级id
        $level = (new AreaAgentLevel())->getDefaultLevel();
        $now = date('Y-m-d H:i:s');
        $insertData = [];
        foreach ($areaInfo as $item) {
            $insertData[] = [
                'site_id' => $siteId,
                'member_id' => $memberId,
                'area_agent_level' => $level['id'],
                'status' => $status,
                'apply_id' => 0,
                'area_type' => $item['area_type'],
                'prov' => $item['prov'],
                'city' => $item['city'],
                'district' => $item['district'],
                'created_at' => $now,
                'apply_type' => 1,
            ];
        }
        // 批量插入
        return AreaAgentModel::query()->insert($insertData);
    }

    /**
     * 检测地区id是否有效
     * @param array $areaInfo   区域信息 [['area_type' => 9, 'prov' => 1111, 'city' => 11, 'district' => 222]];
     * @return bool
     * @throws \Exception
     */
    public static function checkAreaActive(&$areaInfo)
    {
        $districtModel = DistrictModel::query();
        $ids =[];
        foreach ($areaInfo as &$item) {
            switch ($item['area_type']) {
                // 省代
                case AreaAgentConstants::AreaAgentLevel_Province:
                    $ids[] = $item['prov'];
                    $item['city'] = 0;
                    $item['district'] = 0;
                    break;
                case AreaAgentConstants::AreaAgentLevel_City:
                    $ids[] = $item['prov'];
                    $ids[] = $item['city'];
                    $item['district'] = 0;
                    break;
                case AreaAgentConstants::AreaAgentLevel_District:
                    $ids[] = $item['prov'];
                    $ids[] = $item['city'];
                    $ids[] = $item['district'];
                    break;
                default:
                    throw new \Exception('区域代理类型错误');
            }
        }
        $ids = array_unique($ids);
        return count($ids) == $districtModel->whereIn('id', $ids)->count();
    }

    /**
     * 取消代理资格
     * @param int $unbind 是否解除区域的绑定
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancelAreaAgent($unbind = 0)
    {
        try {
            DB::beginTransaction();
            $member = $this->getMemberModel();
            // 状态是否是已生效
            if ($member->is_area_agent !== AreaAgentConstants::AreaAgentStatus_Active) {
                throw new \Exception('区域代理状态错误');
            }
            // 取消绑定 删掉代理的所有区域
            $areaAgentQuery = AreaAgentModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId);
            if ($unbind == 1) {
                $areaAgentQuery->delete();
            } else {
                // 不取消绑定 修改区代的状态
                $areaAgentQuery->update(['status' => AreaAgentConstants::AreaAgentStatus_Cancel]);
            }
            // 更新会员
            $member = new Member($member);
            $member->edit(['is_area_agent' => AreaAgentConstants::AreaAgentStatus_Cancel]);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 恢复代理商资格
     * @param array $areaInfo 新增的区域信息 [['area_type' => 9, 'prov' => 1111, 'city' => 11, 'district' => 222]];
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function recoverAreaAgent($areaInfo = [])
    {
        try {
            $member = $this->getMemberModel();
            // 检测状态
            if ($member->is_area_agent !== AreaAgentConstants::AreaAgentStatus_Cancel) {
                throw new \Exception('区域代理状态错误');
            }
            DB::beginTransaction();
            $areaAgentQuery = AreaAgentModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId);
            // 没有解绑区域 修改区代状态
            if ($areaAgentQuery->exists()) {
                $areaAgentQuery->update(['status' => AreaAgentConstants::AreaAgentStatus_Active]);
            } else {
                // 如果是解绑了区域 则需要另外选择区域去绑定
                if (!$areaInfo) {
                    throw new \Exception('请选择要代理的区域');
                }
                self::saveNewAreaAgent($member, $areaInfo);
            }
            // 更新会员
            $member = new Member($member);
            $member->edit(['is_area_agent' => AreaAgentConstants::AreaAgentStatus_Active]);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 修改代理区域
     * @param array $areaInfo 新增的区域信息 [['area_type' => 9, 'prov' => 1111, 'city' => 11, 'district' => 222]];
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function modifyAgentArea($areaInfo = [])
    {
        try {
            if (!$areaInfo) {
                throw new \Exception('请选择要代理的区域');
            }
            DB::beginTransaction();
            // 查找当前代理的区域列表 如果不在要保存的区域里 则去删除
            $areaList = AreaAgentModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId)
                ->get();
            $newAreaList = $areaInfo;
            if ($areaList->isNotEmpty()) {
                $areaInfoColl = collect($areaInfo);
                $deleteIds = [];
                $existAreas = [];
                foreach ($areaList as $item) {
                    // 把不需要的区域代理id筛选出来
                    $exist = $areaInfoColl->where('area_type', $item['area_type'])
                        ->where('prov', $item['prov'])
                        ->where('city', $item['city'])
                        ->where('district', $item['district'])
                        ->isNotEmpty();
                    if (!$exist) {
                        $deleteIds[] = $item['id'];
                    } else {
                        $existAreas[] = [
                            'area_type' => $item['area_type'],
                            'prov' => $item['prov'],
                            'city' => $item['city'],
                            'district' => $item['district']
                        ];
                    }
                }
                // 把已有的记录剔除掉 不需要重复添加
                if ($existAreas) {
                    $newAreaList = [];
                    foreach ($areaInfo as $item) {
                        if (!in_array($item, $existAreas)) {
                            $newAreaList[] = $item;
                        }
                    }
                }
                // 删除不需要的区域代理记录
                if ($deleteIds) {
                    AreaAgentModel::query()
                        ->where('site_id', $this->siteId)
                        ->where('member_id', $this->memberId)
                        ->whereIn('id', $deleteIds)
                        ->delete();
                }
            }
            if ($newAreaList) {
                // 获取会员信息
                $member = $this->getMemberModel();
                $save = self::saveNewAreaAgent($this->getMemberModel(), $newAreaList, $member->is_area_agent);
            } else {
                $save = true;
            }
            DB::commit();
            return $save;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 获取已被占用的区域id列表
     * @param $areaType     区域类型
     * @param int $parentId 上级地区id
     * @return array
     * @throws \Exception
     */
    public static function getDisableAreaIds($areaType, $parentId = 0)
    {
        if ($areaType != AreaAgentConstants::AreaAgentLevel_Province && $parentId <= 0) {
            throw new \Exception('数据错误');
        }
        // 生效的 和取消资格未解绑的区域 都是不能再使用的区域
        $query = AreaAgentModel::query()
            ->where('site_id', getCurrentSiteId())
            ->whereIn('status', [AreaAgentConstants::AreaAgentStatus_Active, AreaAgentConstants::AreaAgentStatus_Cancel])
            ->where('area_type', $areaType);
        // 申请中的也不能再使用
        $applyQuery = AreaAgentApplyModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('status', AreaAgentConstants::AreaAgentStatus_WaitReview)
            ->where('apply_area_type', $areaType);
        $select = '';
        $applySelect = '';
        switch ($areaType) {
            // 查找已有的省代
            case AreaAgentConstants::AreaAgentLevel_Province:
                $query->where('prov', '>', 0);
                $select = 'prov';
                $applyQuery->where('apply_prov', '>', 0);
                $applySelect = 'apply_prov';
                break;
            case AreaAgentConstants::AreaAgentLevel_City:
                $query->where('prov', $parentId)
                    ->where('city', '>', 0);
                $select = 'city';
                $applyQuery->where('apply_prov', $parentId)->where('apply_city', '>', 0);
                $applySelect = 'apply_city';
                break;
            case AreaAgentConstants::AreaAgentLevel_District:
                $query->where('city', $parentId)
                    ->where('district', '>', 0);
                $select = 'district';
                $applyQuery->where('apply_city', $parentId)->where('apply_district', '>', 0);
                $applySelect = 'apply_district';
                break;
            default:
                throw new \Exception('区域代理类型错误');

        }
        $agentAreas = $query->pluck($select)->toArray();
        $applyAreas = $applyQuery->pluck($applySelect)->toArray();
        return array_unique(array_merge($agentAreas, $applyAreas));
    }

    /**
     * 获取区代相关信息 后台会员详情用
     * @return array
     */
    public function getAreaAgentInfo()
    {
        // 会员信息
        $member = $this->getMemberModel();
        $memberInfo = [
            'id' => $member['id'],
            'name' => $member['name'],
            'nickname' => $member['nickname'],
            'mobile' => $member['mobile'],
            'headurl' => $member['headurl'],
            'agent_level' => $member['agent_level'],
            'dealer_level' => $member['dealer_level'],
            'is_distributor' => $member['is_distributor'],
            'is_area_agent' => $member['is_area_agent'],
            'is_supplier' => $member['is_supplier'],
        ];
        // 代理的区域列表
        $areaList = AreaAgentHelper::getMemberAreaAgentListAndCount($this->memberId);

        // 订单概况
        $orderInfo = OrderAreaAgentHistoryModel::query()
            ->from('tbl_order_area_agent_history as oh')
            ->join('tbl_order as o', 'o.id', 'oh.order_id')
            ->where('oh.site_id', $this->siteId)
            ->where('oh.member_id', $this->memberId)
            ->whereIn('o.status', \App\Modules\ModuleShop\Libs\Constants::getPaymentOrderStatus())
            ->selectRaw('count(1) as order_num, sum(o.money + o.after_sale_money) as order_money')
            ->first();
        $orderInfo['order_money'] = moneyCent2Yuan($orderInfo['order_money']);

        // 代理返佣概况
        $commission = FinanceModel::onWriteConnection()
            ->where('site_id', $this->siteId)
            ->where('member_id', $this->memberId)
            ->where('type', Constants::FinanceType_AreaAgentCommission)
            ->selectRaw("
                sum(if(`status`=? and money>0,money,0)) as commission_total,
                sum(if(`status`=? and money>0,money,0) + if(`status`<>? and money<0,money,0)) as commission_balance,
                sum(if(`status`=? and money>0,money,0)) as commission_unsettled,
                sum(if(`status`=? and money<0 and out_type in(?,?),money,0)) as commission_check
            ",
                [
                    Constants::FinanceStatus_Active,
                    Constants::FinanceStatus_Active,
                    Constants::FinanceStatus_Invalid,
                    Constants::FinanceStatus_Freeze,
                    Constants::FinanceStatus_Freeze,
                    Constants::FinanceOutType_Withdraw,
                    Constants::FinanceOutType_CommissionToBalance
                ]
            )
            ->first();
        $commission['commission_total'] = moneyCent2Yuan($commission['commission_total']);
        $commission['commission_balance'] = moneyCent2Yuan($commission['commission_balance']);
        $commission['commission_unsettled'] = moneyCent2Yuan($commission['commission_unsettled']);
        $commission['commission_check'] = moneyCent2Yuan(abs(intval($commission['commission_check'])));

        return [
            'order_count' => $orderInfo,
            'commission' => $commission,
            'area_agent_list' => $areaList,
            'member_info' => $memberInfo
        ];
    }

    /**
     * 获取区域子代理列表
     * @param $params
     * @return array
     * @throws \Exception
     */
    public static function getSubAreaAgentList($params)
    {
        if (!$params['agent_id']) {
            throw new \Exception('数据错误');
        }
        $areaAgentModel = AreaAgentModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('id', $params['agent_id'])
            ->first();
        if (!$areaAgentModel) {
            throw new \Exception('区代不存在');
        }
        if ($areaAgentModel->area_type == AreaAgentConstants::AreaAgentLevel_District) {
            return [];
        } else {
            // 没有传区代类型参数时
            if (!isset($params['area_type']) || !$params['area_type']) {
                // 省代 则查找所有的下级
                if ($areaAgentModel->area_type == AreaAgentConstants::AreaAgentLevel_Province) {
                    $params['area_type'] = [
                        AreaAgentConstants::AreaAgentLevel_District,
                        AreaAgentConstants::AreaAgentLevel_City
                    ];
                    $params['prov'] = $areaAgentModel->prov;
                } else {
                    // 市代 查找所有的区代
                    $params['area_type'] = AreaAgentConstants::AreaAgentLevel_District;
                    $params['city'] = $areaAgentModel->city;
                }
            } else {
                $params['prov'] = $areaAgentModel->prov;
                // 如果是查找区代
                if ($params['area_type'] == AreaAgentConstants::AreaAgentLevel_District) {
                    $params['city'] = $areaAgentModel->city;
                }
            }
            $params['status'] = AreaAgentConstants::AreaAgentStatus_Active;
            return self::getAllAreaAgentList($params);
        }
    }

    /**
     * 获取所有区代列表 没有去重
     * @param $params
     * @return array
     */
    public static function getAllAreaAgentList($params)
    {
        $query = AreaAgentModel::query()
            ->from('tbl_area_agent as agent')
            ->leftJoin('tbl_member as member', 'member.id', 'agent.member_id')
            ->where('agent.site_id', getCurrentSiteId());
        // 查询条件
        // 手机号 昵称搜索
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $query->where(function ($query) use ($keyword) {
                $query->where('member.nickname', 'like', $keyword);
                $query->orWhere('member.name', 'like', $keyword);
                $query->orWhere('member.mobile', 'like', $keyword);
            });
        }
        // 区域级别
        if (isset($params['area_type'])) {
            if (is_array($params['area_type'])) {
                $query->whereIn('agent.area_type', $params['area_type']);
            } else {
                $query->where('agent.area_type', $params['area_type']);
            }
        }
        // 代理区域搜索
        if (isset($params['prov']) && $params['prov']) {
            $query->where('agent.prov', $params['prov']);
        }
        if (isset($params['city']) && $params['city']) {
            $query->where('agent.city', $params['city']);
        }
        if (isset($params['district']) && $params['district']) {
            $query->where('agent.district', $params['district']);
        }
        // 状态
        if (isset($params['status']) && $params['status'] != 0) {
            $query->where('agent.status', $params['status']);
        } else {
            $query->where('agent.status', AreaAgentConstants::AreaAgentStatus_Active);
        }
        $page = $params['page'] ?: 1;
        $pageSize = $params['page_size'] ?: 20;
        // 统计数据条数
        $total = $query->count();
        $lastPage = ceil($total / $pageSize);
        $list = $query->orderByDesc('agent.created_at')
            ->addSelect([
                'member.nickname',
                'member.mobile as member_mobile',
                'member.headurl',
                'member.name',
                'agent.created_at',
                'agent.status',
                'agent.member_id',
                'agent.prov',
                'agent.city',
                'agent.district',
                'agent.area_type',
                'agent.id'
            ])
            ->forPage($page, $pageSize)
            ->get();

        if ($list->isNotEmpty()) {
            $list = AreaAgentHelper::getListAreaText($list);
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }
}