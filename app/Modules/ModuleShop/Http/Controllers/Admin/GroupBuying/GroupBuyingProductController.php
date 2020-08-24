<?php
/**
 * 拼团产品接口
 * User: wenke
 * Date: 2020/4/9
 * Time: 15:13
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\GroupBuying;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingProducts;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use App\Modules\ModuleShop\Libs\Product\Product;
use Illuminate\Http\Request;

class GroupBuyingProductController extends BaseAdminController
{
    /**
     * 获取活动商品的列表信息
     * @param Request $request
     * @return array
     */
    function getList(Request $request)
    {
        try {
            $params = $request->toArray();
            $list = GroupBuyingProducts::getFrontList($params);
            $classList = Product::getClassList();
            return makeApiResponseSuccess('ok', ['productList' => $list, 'classList' => $classList]);
        } catch (\Exception $e) {
            return makeApiResponse(500, $e->getMessage());
        }
    }
}