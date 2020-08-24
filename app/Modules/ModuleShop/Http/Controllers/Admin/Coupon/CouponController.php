<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Coupon;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Common\Export;

/**
 * 后台积分Controller
 * Class PointController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Point
 */
class CouponController extends BaseAdminController
{
    private $coupon;

    /**
     * 初始化
     * CouponController constructor.
     */
    public function __construct()
    {
        $this->coupon = new \App\Modules\ModuleShop\Libs\Coupon\Coupon();
    }

    /**
     * 添加优惠券
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            $param = $request->toArray();
            if (!$this->coupon->dataCheck(array_merge($param, ['is_delete' => 0]))) {
                return makeApiResponse(510, '已存在与之设置信息完全一致的优惠券');
            }
            $this->coupon->add($param);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑优惠券
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $param = $request->toArray();
            $this->coupon->edit($param);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 更改优惠券状态
     * @param Request $request
     * @return array
     */
    public function editStatus(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $param = $request->toArray();
            $this->coupon->editStatus($param);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 查看详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $data = $this->coupon->getInfo($request->id);
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }


    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getProductClass()
    {
        try {
            $data = $this->coupon->getProductClass();
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->toArray();
            $param['count_used'] = 1;
            $param['count_nouse'] = 1;
            $data = $this->coupon->getList($param);
            $list = $data['list']->toArray();

            return makeApiResponseSuccess('成功', [
                'total' => intval($data['total']),
                'page_size' => intval($data['page_size']),
                'current' => intval($data['current']),
                'last_page' => intval($data['last_page']),
                'list' => $list,
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 根据条件，返回优惠券记录数据
     * $param 查询条件
     * @return $list
     */
    public function getCouponItem(Request $request)
    {
        $param = $request->toArray();
        $data = $this->coupon->getCouponItem($param);
        return makeApiResponseSuccess('成功', [
            'member_count' => count($data['all_member_id']->toArray()),
            'received_count' => $data['received_count']
        ]);
    }

    /**
     * 返回会员列表以及会员等级
     * $param 查询条件
     * @return $list
     */
    public function getMemberAndLevel(Request $request)
    {
        $param = $request->toArray();
        $member_data = $this->coupon->getMemberlist($param);
        $member_data_list = $member_data['list']->toArray();
        foreach ($member_data_list as $k => &$v) {
            $v['level_name'] = $v['level_name'] == null ? '暂无等级' : $v['level_name'];
        }
        $member_data['list'] = new Collection($member_data_list);
        $level_data = $this->coupon->getMemberLevel();
        return makeApiResponseSuccess('成功', [
            'member_data' => $member_data,
            'level_data' => $level_data
        ]);
    }

    /**
     * 检测优惠券是否大于优惠券总数量
     * $param
     * id 优惠券id
     * member_id 会员id
     * level_id 会员等级id
     * type  0：所有会员 1：会员等级 2：会员
     */
    public function checkCouponAmount(Request $request)
    {
        $param = $request->toArray();
        if ($param['type'] == 1 && !$param['level_id']) {
            return makeApiResponse(false, '请选择至少一个会员等级');
        }
        if ($param['type'] == 2 && !$param['member_id']) {
            return makeApiResponse(false, '请选择至少一名会员');
        }
        $data = $this->coupon->checkCouponAmount($param);
        //写到输出DATA的，data。
        if ($data['code'] == 200) {
            return makeApiResponse(true, '成功');
        } else {
            return makeApiResponse(false, $data['msg'], $data['data']);
        }

    }

    /**
     * 发放优惠券
     * $param
     * id 优惠券id
     * member_id 会员id
     * level_id 会员等级id
     * type  0：所有会员 1：会员等级 2：会员
     */
    public function sendCoupon(Request $request)
    {
        if (!$request->id) {
            return makeApiResponse(false, '缺少ID参数');
        }
        $param = $request->toArray();
        $param['system_send_coupon'] = true;
        $data = $this->coupon->addCouponItem($param);
        return makeApiResponseSuccess('成功', [
            'data' => $data
        ]);
    }

    /**
     * 删除优惠券
     * @param  Request id 模板ID
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(false, '缺少ID参数');
            }
            if ($this->coupon->checkHaveUse($request->id)) {
                return makeApiResponse(false, '此优惠券正在启用状态，不能删除，请先禁用');
            }
            $this->coupon->delete($request->id);
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 优惠券列表导出
     * $param 搜索条件
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $param['count_used'] = 1;
            $param['count_nouse'] = 1;
            $data = $this->coupon->getList($param);
            $exportHeadings = [
                '优惠券标题',
                '类型',
                '应用终端',
                '有效期',
                '指定用户群',
                '指定商品',
                '总数量',
                '已领取数',
                '可领取数',
                '已使用数',
                '使用率',
                '状态'
            ];
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        $item->title,
                        $item->coupon_type == 0 ? '现金券' : '折扣券',
                        $item->terminal_type,
                        $item->effective_time,
                        $item->member_type,
                        $item->product_info,
                        $item->amount,
                        (string)$item->have_received,
                        $item->can_received != -1 ? (string)$item->can_received : '无限',
                        (string)$item->already_used,
                        $item->use_rate,
                        $item->status == 1 ? '启用' : '禁用'
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'YouHuiQuan-' . date("YmdHis") . '.xlsx', $exportHeadings);

            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

}