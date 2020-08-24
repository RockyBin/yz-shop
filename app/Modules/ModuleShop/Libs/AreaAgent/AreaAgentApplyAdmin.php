<?php
/**
 * 后台区代审核逻辑
 * User: liyaohui
 * Date: 2020/5/23
 * Time: 16:24
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplyFormDataModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplyModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Model\DistrictModel;
use YZ\Core\Constants as CoreConstants;
use Illuminate\Foundation\Bus\DispatchesJobs;

class AreaAgentApplyAdmin
{
    private $siteId = 0;
    use DispatchesJobs;

    public function __construct()
    {
        $this->siteId = getCurrentSiteId();
    }

    /**
     * 获取区域代理审核列表
     * @param array $params
     * @return array
     */
    public function getApplyList($params = [])
    {
        $siteId = $this->siteId;
        $agent = AreaAgentApplyModel::query()
            ->from('tbl_area_agent_apply as apply')
            ->where('apply.site_id', $siteId);

        $agent->leftJoin('tbl_member as member', 'member.id', '=', 'apply.member_id')
            ->leftJoin('tbl_area_agent_apply_form_data as form', 'form.member_id', 'apply.member_id')
            ->leftJoin('tbl_district as tbl_district_prov', 'form.contact_prov', '=', 'tbl_district_prov.id')
            ->leftJoin('tbl_district as tbl_district_city', 'form.contact_city', '=', 'tbl_district_city.id')
            ->leftJoin('tbl_district as tbl_district_area', 'form.contact_area', '=', 'tbl_district_area.id');
        // 手机号 昵称搜索
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $agent->where(function ($query) use ($keyword) {
                $query->where('member.nickname', 'like', $keyword);
                $query->orWhere('member.name', 'like', $keyword);
                $query->orWhere('member.mobile', 'like', $keyword);
            });
        }

        // 状态
        if (isset($params['status'])) {
            $agent->where('apply.status', $params['status']);
            if ($params['status'] == AreaAgentConstants::AreaAgentStatus_WaitReview) {
                // 申请成为代理时间搜索
                if (isset($params['created_at_start'])) {
                    $agent->where('apply.created_at', '>=', $params['created_at_start']);
                }
                if (isset($params['created_at_end'])) {
                    $agent->where('apply.created_at', '<=', $params['created_at_end']);
                }
                $agent->orderByDesc('apply.created_at');
            } else {
                // 申请成为代理时间搜索
                if (isset($params['created_at_start'])) {
                    $agent->where('apply.passed_at', '>=', $params['created_at_start']);
                }
                if (isset($params['created_at_end'])) {
                    $agent->where('apply.passed_at', '<=', $params['created_at_end']);
                }
                $agent->orderByDesc('apply.passed_at');
            }
        }

        // 等级搜索
        if (isset($params['apply_area_type'])) {
            $agent->where('apply_area_type', $params['apply_area_type']);
        }

        // 代理区域搜索
        if (isset($params['apply_prov']) && $params['apply_prov']) {
            $agent->where('apply_prov', $params['apply_prov']);
        }
        if (isset($params['apply_city']) && $params['apply_city']) {
            $agent->where('apply_city', $params['apply_city']);
        }
        if (isset($params['apply_district']) && $params['apply_district']) {
            $agent->where('apply_district', $params['apply_district']);
        }

        $page = $params['page'] ?: 1;
        $pageSize = $params['page_size'] ?: 20;
        // 统计数据条数
        $total = $agent->count();
        $lastPage = ceil($total / $pageSize);
        // 要查找的字段
        $list = $agent->select([
            'apply.id as apply_id',
            'apply.apply_area_agent_level',
            'member.nickname',
            'member.mobile as member_mobile',
            'member.headurl',
            'member.name',
            'apply.apply_area_type',
            'apply.apply_prov',
            'apply.apply_city',
            'apply.apply_district',
            'apply.status',
            'apply.apply_type',
            'apply.reject_reason',
            'apply.apply_condition',
            'apply.created_at',
            'apply.passed_at',
            'form.*',
            'member.id as member_id',
            'tbl_district_prov.name as contact_prov_text',
            'tbl_district_city.name as contact_city_text',
            'tbl_district_area.name as contact_area_text'
        ])
            ->forPage($page, $pageSize)
            ->get();
        if ($list->isNotEmpty()) {
            // 取出所有 省市区id
            $areaIds = [];
            foreach ($list as $item) {
                $areaIds[] = $item->apply_prov;
                $areaIds[] = $item->apply_city;
                $areaIds[] = $item->apply_district;
            }
            // 去重
            $areaIds = array_unique($areaIds);
            // 查找所有需要的地址id
            $districtList = DistrictModel::query()->whereIn('id', $areaIds)->get()->keyBy('id');
            // 匹配给列表
            foreach ($list as $value) {
                $value->apply_prov_text = $value->apply_prov ? $districtList[$value->apply_prov]['name'] : '';
                $value->apply_city_text = $value->apply_city ? $districtList[$value->apply_city]['name'] : '';
                $value->apply_district_text = $value->apply_district ? $districtList[$value->apply_district]['name'] : '';
                $value->apply_condition = $value->apply_condition ? json_decode($value->apply_condition, true) : '';
                $value->extend_fields = json_decode($value->extend_fields, true);
                $value->member_mobile = Member::memberMobileReplace($value->member_mobile);
            }
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
     * 审核区域代理
     * @param $params
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function verify($params)
    {
        $applyId = $params['apply_id'];
        if (!$applyId) {
            throw new \Exception('数据错误');
        }
        $apply = AreaAgentApplyModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $applyId)
            ->first();
        if (!$apply) {
            throw new \Exception('数据不存在');
        }

        if ($apply->status != AreaAgentConstants::AreaAgentStatus_WaitReview) {
            throw new \Exception('不能重复审核');
        }

        $status = $params['status'];
        $now = Carbon::now();
        if ($status == AreaAgentConstants::AreaAgentStatus_RejectReview) {
            if ($rejectReason = trim($params['reject_reason'])) {
                $apply->reject_reason = $rejectReason;
                $apply->status = AreaAgentConstants::AreaAgentStatus_RejectReview;
                $apply->passed_at = $now;
                $apply->save();
                $this->dispatch(new MessageNotice(CoreConstants::MessageType_Area_Agent_Reject, $apply));
            } else {
                throw new \Exception('请输入拒绝原因');
            }
        } elseif ($status == AreaAgentConstants::AreaAgentStatus_Active) {
            if (AreaAgentHelper::checkAreaAgentExist([[
                'area_type' => $apply->apply_area_type,
                'prov' => $apply->apply_prov,
                'city' => $apply->apply_city,
                'district' =>$apply->apply_district
            ]], $applyId)) {
                throw new \Exception('该区域已有代理');
            }
            // 成为代理
            try {
                DB::beginTransaction();
                $apply->status = AreaAgentConstants::AreaAgentStatus_Active;
                $apply->passed_at = $now;
                $apply->save();
                // 插入区代记录
                $areaAgent = new AreaAgentModel();
                $areaAgent->member_id = $apply->member_id;
                $areaAgent->site_id = $apply->site_id;
                $areaAgent->area_agent_level = $apply->apply_area_agent_level;
                $areaAgent->area_type = $apply->apply_area_type;
                $areaAgent->prov = $apply->apply_prov;
                $areaAgent->city = $apply->apply_city;
                $areaAgent->district = $apply->apply_district;
                $areaAgent->apply_id = $apply->id;
                $areaAgent->created_at = $now;
                $areaAgent->status = AreaAgentConstants::AreaAgentStatus_Active;
                $areaAgent->save();
                // 更新会员
                $member = new Member($apply->member_id);
                $member->edit([
                    'is_area_agent' => AreaAgentConstants::AreaAgentStatus_Active,
                    'area_agent_at' => $now
                ]);
                DB::commit();
                $this->dispatch(new MessageNotice(CoreConstants::MessageType_Area_Agent_Agree, $areaAgent));
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            throw new \Exception('审核状态错误');
        }
    }

    /**
     * 删除审核记录
     * @param $applyId
     * @throws \Exception
     */
    public function deleteApply($applyId)
    {
        $apply = AreaAgentApplyModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $applyId)
            ->first();
        if (!$apply) {
            throw new \Exception('数据不存在');
        }

        if ($apply->status != AreaAgentConstants::AreaAgentStatus_RejectReview) {
            throw new \Exception('该申请记录的状态不允许删除');
        }

        // 是否有生效的区代记录
        $agentCount = AreaAgentApplyModel::query()
            ->where('site_id', $this->siteId)
            ->where('member_id', $apply->member_id)
            ->count();

        // 如果有申请记录 则不去删除表单内容
        if ($agentCount <= 1) {
            AreaAgentApplyFormDataModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->member_id)
                ->delete();
        }
        $apply->delete();
    }
}