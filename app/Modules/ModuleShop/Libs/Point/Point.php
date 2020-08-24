<?php

namespace App\Modules\ModuleShop\Libs\Point;

use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use Illuminate\Database\Eloquent\Builder;
use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Point\PointHelper;
use YZ\Core\Site\Site;
use YZ\Core\Model\PointModel;
use YZ\Core\Point\Point as PointEntity;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Member\Member as CoreMember;

/**
 * 积分业务类
 */
class Point
{
    private $siteId = 0; // 站点ID
    private $point = null;

    /**
     * 初始化
     * Point constructor.
     */
    public function __construct($siteId = 0)
    {
        if (!$siteId) {
            $siteId = Site::getCurrentSite()->getSiteId();
        }
        $this->siteId = $siteId;
        $this->point = new PointEntity();
    }

    /**
     * 新插入数据
     * @param array $params
     * @return bool|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function add(array $params)
    {
        // 检查数据
        if (empty($params)) {
            return false;
        }

        // 检查会员是否存在
        $member = new Member(intval($params['member_id']), $this->siteId);
        if (!$member->checkExist()) {
            return false;
        }
        $point = intval($params['point']);
        if ($point < 0) {
            // 减少积分时 不能大于已有的积分
            $pointTotal = PointHelper::getPointBalance($params['member_id']);
            if ($pointTotal + $point < 0) {
                throw new \Exception("该会员addPointHandle只有 {$pointTotal} 积分，出账不能超过会员现有积分");
            }
        }

        $isIn = $point > 0; // true-来源，false-使用
        $in_out_type = intval($params['in_out_type']);
        $in_out_id = trim($params['in_out_id']);

        // 处理来源或使用
        if ($isIn) {
            $params['in_type'] = $in_out_type;
            $params['in_id'] = $in_out_id;
        } else {
            $params['out_type'] = $in_out_type;
            $params['out_id'] = $in_out_id;
        }

        // 填充保存数据
        $params['site_id'] = $this->siteId;
        $params['created_at'] = date('Y-m-d H:i:s');
        // 保存生效时间
        $status = intval($params['status']);
        if ($status && !$params['active_at']) {
            $params['active_at'] = date('Y-m-d H:i:s');
        }
        $pointId = $this->point->add($params);
        $this->point->find($pointId);
        // 发送通知
        if (intval($this->point->getModel()->status) == CoreConstants::PointStatus_Active) {
            MessageNoticeHelper::sendMessagePointChange($this->point->getModel());
        }
        return $this->point->getModel();
    }

    /**
     * 积分生效
     * @param $pointId
     * @param array $params
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function active($pointId, array $params)
    {
        $this->point->find($pointId);
        if ($this->point->checkExist()) {
            $oldStatus = intval($this->point->getModel()->status);
            $params['active_at'] = date('Y-m-d H:i:s');
            $params['status'] = CoreConstants::PointStatus_Active;
            $this->point->edit($params);
            if ($oldStatus == CoreConstants::PointStatus_UnActive) {
                MessageNoticeHelper::sendMessagePointChange($this->point->getModel());
            }
        }
    }

    /**
     * 列表查询
     * @param array $params 参数
     * @return array
     */
    public function getList(array $params = [])
    {
        // 分页参数
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;
        $isShowAll = $params['show_all'] ? true : false; // 是否显示全部数据（不分页）
        $isOutputText = $params['outputText'] ? true : false; // 是否转换一些信息为文字，比如省市区

        // 查询表达式
        $expression = PointModel::query()
            ->from('tbl_point as point')
            ->join('tbl_member as member', 'point.member_id', '=', 'member.id')
            ->where('point.site_id', $this->siteId);
        if ($params['ids']) $isShowAll = true;
        // 设置查询条件
        $this->setQuery($expression, $params);
        // 数据获取
        $total = $expression->count();
        $expression->select('point.*', 'member.name', 'member.nickname', 'member.mobile', 'member.id as member_id', 'member.headurl');
        if ($params['order_by'] == 'active_at') {
            $expression->orderBy('active_at', 'desc')->orderBy('created_at', 'desc');
        } else {
            $expression->orderBy('created_at', 'desc');
        }

        if ($isShowAll) {
            // 显示全部
            $page_size = $total > 0 ? $total : 1;
            $page = 1;
        } else {
            // 分页
            $offset = ($page - 1) * $page_size;
            $expression->offset($offset)->limit($page_size);
        }
        $list = $expression->get();
        $last_page = ceil($total / $page_size);

        if ($isOutputText) {
            foreach ($list as $item) {
                $inoutData = Point::mergeInoutType($item);
                $item->inout_type_text = CoreConstants::getPointInoutTypeText(intval($inoutData['type']));
                $item->type_text = intval($item->point) >= 0 ? '入账' : '出账';
                $item->terminal_type_text = CoreConstants::getTerminalTypeText(intval($item->terminal_type));
            }
        }

        return [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 统计数量
     * @param array $params
     * @return int
     */
    public function count(array $params = [])
    {
        $expression = PointModel::query()
            ->from('tbl_point as point')
            ->join('tbl_member as member', 'point.member_id', '=', 'member.id')
            ->where('point.site_id', $this->siteId);
        // 设置查询条件
        $this->setQuery($expression, $params);
        return $expression->count();
    }

    /**
     * 设置查询条件
     * @param Builder $expression
     * @param $params
     */
    public function setQuery(Builder $expression, $params)
    {
        // 积分id
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            // 如果通过id获取，则认为是显示全部
            if (count($ids) > 0) {
                $expression->whereIn('point.id', $ids);
            }
        }
        // 会员id
        if (intval($params['member_id']) > 0) {
            $expression->where('point.member_id', intval($params['member_id']));
        }
        // 流水类型（支出或收入）
        if ($params['flow_type'] != '' && intval($params['flow_type']) != 0) {
            if (intval($params['flow_type']) >= 0) {
                // 收入
                $expression->where('point.point', '>=', '0');
            } else {
                // 支出
                $expression->where('point.point', '<', '0');
            }
        }
        // 状态
        if (is_numeric($params['status']) && intval($params['status']) >= 0) {
            $expression->where('point.status', intval($params['status']));
        } else if ($params['status']) {
            $status = myToArray($params['status']);
            if (count($status) > 0) {
                $expression->whereIn('point.status', $status);
            }
        }
        // 昵称和手机搜索整合到一起
        if (trim($params['keyword'])) {
            $keyword = trim($params['keyword']);
            $expression->where(function ($query) use ($keyword) {
                $query->where('member.mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('member.nickname', 'like', '%' . $keyword . '%')
                    ->orWhere('member.name', 'like', '%' . $keyword . '%');
            });
        }
        // 终端
        if (is_numeric($params['terminal_type']) && intval($params['terminal_type']) >= 0) {
            $expression->where('point.terminal_type', intval($params['terminal_type']));
        }
        // 来源/用途
        if (is_numeric($params['in_out_type']) && intval($params['in_out_type'] >= 0)) {
            $in_out = intval($params['in_out_type']);
            if ($in_out > 0) {
                $expression->where(function ($query) use ($in_out) {
                    $query->where('point.in_type', $in_out)->orWhere('point.out_type', $in_out);
                });
            } else {
                $expression->where('point.in_type', $in_out);
                $expression->where('point.out_type', $in_out);
            }

            // 在有来源/用途的情况下，可搜索 来源/用途 id
            if ($params['in_id'] != '') {
                $expression->where('point.in_id', $params['in_id']);
            }
            if ($params['out_id'] != '') {
                $expression->where('point.out_id', $params['out_id']);
            }
        }
        // 创建时间开始
        if ($params['created_at_start'] != '') {
            $expression->where('point.created_at', '>=', $params['created_at_start']);
        }
        // 创建时间结束
        if ($params['created_at_end'] != '') {
            $expression->where('point.created_at', '<=', $params['created_at_end']);
        }
        // 生效时间开始
        if ($params['active_at_start'] != '') {
            $expression->where('point.active_at', '>=', $params['active_at_start']);
        }
        // 生效时间结束
        if ($params['active_at_end'] != '') {
            $expression->where('point.active_at', '<=', $params['active_at_end']);
        }
    }

    /**
     * 合并来源用途
     * @param $item
     * @return array
     */
    public static function mergeInoutType($item)
    {
        $inoutType = $item['in_type'];
        $inoutID = $item['in_id'];
        if (!$inoutType) {
            $inoutType = $item['out_type'];
            $inoutID = $item['out_id'];
        }
        return [
            'type' => $inoutType,
            'id' => $inoutID
        ];
    }

    /**
     * 寻找会员
     * @param $mobile 送取积分的对象
     * @param $member_id 转赠积分的对象
     * @return array
     */
    public static function pointGiveSearchMember($mobile, $memberId)
    {
        $site_id = Site::getCurrentSite()->getSiteId();
        $searchMemberData = MemberModel::query()
            ->where('mobile', $mobile)
            ->where('site_id', $site_id)
            ->select('id', 'nickname', 'mobile', 'headurl')
            ->first();
        // 不允许转赠给自己，直接返回会员信息，由控制器去判断
        if ($memberId == $searchMemberData->id) return $searchMemberData;
        $pointConfig = (new PointConfig($site_id))->getModel();
        if ($searchMemberData->id) {
            //当前会员可赠送的积分
            $searchMemberData->point = PointHelper::getPointBalance($memberId);
        }
        // 如果设置了积分对象为下级，需要搜索下级，无限级
        if ($pointConfig->point_give_target == Constants::PointConfig_GiveTarget_SubMember) {
            $searchRes = MemberParentsModel::query()
                ->where('parent_id', $memberId)
                ->where('member_id', $searchMemberData->id)
                ->where('site_id', $site_id)
                ->first();
            return $searchRes ? $searchMemberData : false;
        }
        // 如果设置了积分对象为直属下级，需要搜索下级，无限级
        if ($pointConfig->point_give_target == Constants::PointConfig_GiveTarget_directlyMember) {
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
     * 转赠积分
     * @param $inComeMemberId 收取积分的对象
     * @param $memberId 转赠积分的对象
     * @param $point 要赠送的积分
     * @return array
     */
    public function pointGive($inComeMemberId, $memberId, $point)
    {
        $site_id = Site::getCurrentSite()->getSiteId();
        // 验证功能状态是否开启
        $pointConfig = (new PointConfig($site_id))->getModel();
        if ($pointConfig->point_give_status < 1) {
            throw new \Exception("没有开启积分转赠功能");
        }

        // 验证积分是否足够
        $maxPoint = PointHelper::getPointBalance($memberId);
        if ($maxPoint < $point) {
            throw new \Exception("积分余额不足");
        }

        // 收取积分的添加
        $payMember = (new Member($memberId))->getModel();
        $payMemberNickname = $payMember->nickname;
        $inComeData['member_id'] = $inComeMemberId;
        $inComeData['in_out_type'] = CoreConstants::PointInOutType_Give_InCome;
        $inComeData['point'] = $point;
        $inComeData['about'] = '来自于 ' . $payMemberNickname;
        $inComeData['terminal_type'] = getCurrentTerminal();
        $inComeData['status'] = CoreConstants::PointStatus_Active;
        $this->add($inComeData);
        // 转赠积分的添加
        $inComeMember = (new Member($inComeMemberId))->getModel();
        $inComeMemberNickname = $inComeMember->nickname;
        $payData['member_id'] = $memberId;
        $payData['in_out_type'] = CoreConstants::PointInOutType_Give_Pay;
        $payData['point'] = -$point;
        $payData['about'] = '转赠给 ' . $inComeMemberNickname;
        $payData['terminal_type'] = getCurrentTerminal();
        $payData['status'] = CoreConstants::PointStatus_Active;
        $this->add($payData);
    }
}