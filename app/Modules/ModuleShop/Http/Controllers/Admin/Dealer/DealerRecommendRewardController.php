<?php
/**
 * 推荐奖后台api
 * User: liyaohui
 * Date: 2020/1/10
 * Time: 20:10
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;


use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerRecommendReward;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;

class DealerRecommendRewardController extends BaseSiteAdminController
{
    /**
     * 列表数据
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $data = DealerRecommendReward::getList($param);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 导出
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $data = DealerRecommendReward::getList($param);
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        $item->member_id,
                        $item->member_nickname,
                        $item->member_name,
                        "\t" . $item->member_mobile . "\t",
                        $item->member_dealer_level.($item->member_dealer_hide_level ? ' - '.$item->member_dealer_hide_level : ''),
                        $item->sub_member_nickname,
                        $item->sub_member_name,
                        "\t" . $item->sub_member_mobile . "\t",
                        $item->sub_member_dealer_level.($item->sub_member_dealer_hide_level ? ' - '.$item->sub_member_dealer_hide_level : ''),
                        $item->reward_money,
                        $item->pay_member_nickname,
                        $item->pay_member_name,
                        $item->pay_member_mobile ?  "\t" . $item->pay_member_mobile . "\t" : '--',
                        $item->status_text,
                    ];
                }
            }
            // 表头
            $exportHeadings = [
                '得奖推荐人ID',
                '得奖推荐人昵称',
                '得奖推荐人姓名',
                '得奖推荐人手机号',
                '推荐人等级',
                '被推荐人昵称',
                '被推荐人姓名',
                '被推荐人手机号',
                '被推荐人等级',
                '推荐奖金',
                '支付奖金人昵称',
                '支付奖金人姓名',
                '支付奖金人手机号',
                '状态',
            ];
            // 导出
            $exportObj = new Export(new Collection($exportData), 'Tuijian-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}