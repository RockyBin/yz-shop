<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Common;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Product\Product;
use Illuminate\Http\Request;
use YZ\Core\Site\Site;

class CommonController extends BaseAdminController
{



    /**
     * 获取产品列表以及分类列表
     * @param Request $request
     * @return array
     */
    public function getProductList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('pageSize', 20);
            $filter = [
                'is_inventory' => $request->input('is_inventory', 0),
                'status' => $request->input('status', null),
                'type' => $request->input('type', null),
                'class' => $request->input('class', []),
                'keyword' => $request->input('keyword', ''),
                'order_by' => $request->input('order_by', [
                    'column' => 'created_at',
                    'order' => 'asc'
                ]),
                'show_sku'=>$request->input('show_sku', '')
            ];
            $productList = Product::getList($filter, $page, $pageSize);
            $classList = Product::getClassList();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'productList' => $productList,
                'classList' => $classList
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 展示列表
     * @param Request $request
     * @return array
     */
    public function getMemberList(Request $request)
    {
        try {
            $param = $request->all();
            $param['count_extend'] = true; // 输出统计数据
            $this->member = new Member(0, Site::getCurrentSite()->getSiteId());
            $data = $this->member->getList($param);
            if ($data && $data['list']) {
                $list = $data['list']->toArray();
                foreach ($list as &$item) {
                    $item = $this->convertMemberOutputData($item);
                }
                unset($item);
                $data['list'] = $list;
            }
            $this->memberLevel = new MemberLevel();
            // 会员等级列表
            $levelData = $this->memberLevel->getList();
            $data['member_level_list'] = $levelData['list'];

            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */
    public function convertMemberOutputData($item)
    {
        $privateColumns = ['password', 'pay_password']; // 这些字段不返回给前端
        // 清楚私密数据
        foreach ($privateColumns as $privateColumn) {
            unset($item[$privateColumn]);
        }

        $keys = ['buy_money', 'buy_money_real', 'deal_money', 'deal_money_real', 'balance', 'balance_history'];
        foreach ($keys as $key) {
            if ($item[$key]) {
                $item[$key] = moneyCent2Yuan(intval($item[$key]));
            } else {
                $item[$key] = '0';
            }
        }

        return $item;
    }
    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */

}