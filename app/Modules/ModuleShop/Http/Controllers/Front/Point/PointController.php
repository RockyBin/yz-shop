<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Point;

use Illuminate\Http\Request;
use YZ\Core\Constants as CodeConstants;
use YZ\Core\Constants;
use YZ\Core\Member\Member;
use YZ\Core\Point\PointHelper;
use App\Modules\ModuleShop\Libs\Point\PointConfig;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Point\Point;
use YZ\Core\Site\Site;


class PointController extends BaseController
{
    /**
     * 初始化
     * PointController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 积分信息
     * @return array
     */
    public function getInfo()
    {
        try {

            $balance = PointHelper::getPointBalance($this->memberId); // 积分余额
            $config = new PointConfig($this->siteId);
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), [
                'balance' => $balance,
                'config' => $config->getInfo(),
            ]);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 积分列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $point = new Point($this->siteId);
            $param = $request->toArray();
            $param['member_id'] = $this->memberId;
            // 全部只显示生效的
            if (intval($param['status']) == -1) {
                $param['status'] = Constants::PointStatus_Active;
            }
            // 生效状态按生效时间排序
            if (intval($param['status']) == Constants::PointStatus_Active) {
                $param['order_by'] = 'active_at';
            }
            $data = $point->getList($param);
            $list = [];
            if ($data['list']) {
                $list = $data['list']->toArray();
                if ($list) {
                    // 整合来源用途
                    foreach ($list as &$item) {
                        $item['in_out_type'] = Point::mergeInoutType($item)['type'];
                        $item['in_out_type_text'] = $this->parsePointInoutTypeText($item['in_out_type'], $item['status']);

                        if ($item['in_type'] == Constants::PointInOutType_Give_InCome && $item['in_id']) {
                            $member = (new Member($item['in_id']))->getModel();
                            if ($member) $item['in_out_type_text'] .= '-来自于 ' . $member->nickname . '';

                        } elseif ($item['out_type'] == Constants::PointInOutType_Give_Pay && $item['out_id']) {
                            $member = (new Member($item['out_id']))->getModel();
                            if ($member) $item['in_out_type_text'] .= '-转赠给 ' . $member->nickname . '';
                        }

                        if (intval($item['status']) == Constants::PointStatus_UnActive && $item['in_out_type'] == Constants::PointInOutType_Consume) {
                            $item['about'] = '购物赠送' . trans('shop-front.diy_word.point') . '，订单尚未完成';
                        }
                        unset($item['nickname']);
                        unset($item['name']);
                        unset($item['mobile']);
                    }
                }
            }
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), [
                'total' => intval($data['total']),
                'page_size' => intval($data['page_size']),
                'current' => intval($data['current']),
                'last_page' => intval($data['last_page']),
                'list' => $list
            ]);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 积分设置
     * @return array
     */
    public function getConfig()
    {
        try {
            $pointConfig = new PointConfig($this->siteId);
            $config = $pointConfig->getInfo();
            $config = $this->convertOutputData($config);
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $config);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 数据输出转换
     * @param $param
     * @return mixed
     */
    private function convertOutputData($param)
    {
        // 消费每多少元赠送积分
        $in_consume_per_price = $param['in_consume_per_price'];
        if ($in_consume_per_price) {
            $in_consume_per_price = intval($in_consume_per_price) / 100;
            $param['in_consume_per_price'] = number_format($in_consume_per_price, 2);
        }
        return $param;
    }

    private function parsePointInoutTypeText($inOutType, $status)
    {
        if (intval($status) == 1) {
            return CodeConstants::getPointInoutTypeTextForFront(intval($inOutType));
        } else {
            return trans('shop-front.diy_word.point') . '冻结';
        }
    }

    //积分转赠搜索会员
    public function pointGiveSearchMember(Request $request)
    {
        if (!$request->mobile) {
            return makeApiResponseFail('请输入需要搜索的电话号码');
        }
        $res = Point::pointGiveSearchMember($request->mobile, $this->memberId);
        if ($res) {
            if ($res->id == $this->memberId) return makeApiResponseFail('不能给自己转赠哦，请重新输入~');
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $res);
        } else {
            return makeApiResponseFail('找不到该会员哦，请重新输入~');
        }
    }

    public function pointGive(Request $request)
    {
        try {
            if (!$request->income_member_id) {
                return makeApiResponseFail('请输入需要赠送的会员ID');
            }
            (new Point())->pointGive($request->income_member_id, $this->memberId, $request->point);
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}