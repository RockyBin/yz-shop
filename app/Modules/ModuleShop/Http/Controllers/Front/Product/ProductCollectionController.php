<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Product;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Modules\ModuleShop\Libs\Product\ProductCollection;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;

/**
 * 产品收藏
 * Class ProductClassController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Product
 */
class ProductCollectionController extends BaseController
{
    /**
     * 收藏列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            // 先搜索出收藏数据
            $param = $request->toArray();
            $param['member_id'] = $this->memberId;
            $productCollection = new ProductCollection(null, $this->siteId);
            $data = $productCollection->getList($param);
            if ($data && $data['list']) {
                $productIds = $data['list']->pluck('product_id');
                if ($productIds) {
                    // 读取产品数据
                    $productList = [];
                    $productData = Product::getList([
                        'product_ids' => $productIds,
                        'member_id' => $this->memberId,
                    ], 1, 99);
                    if ($productData && $productData['list']) {
                        foreach ($productData['list'] as $productDataItem) {
                            $productList[$productDataItem['id']] = $productDataItem;
                        }
                    }

                    foreach ($data['list'] as $dataItem) {
                        $productId = $dataItem->product_id;
                        if (array_key_exists($productId, $productList)) {
                            $dataItem->product = new Collection($productList[$productId]);
                        }
                    }
                }
            }
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 添加一个收藏
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {

            if (empty($request->product_id)) {
                return makeApiResponse(510, trans("shop-front.common.data_error"));
            }

            $param = [
                'product_id' => $request->product_id,
                'member_id' => $this->memberId
            ];
            $productCollection = new ProductCollection(null, $this->siteId);
            $result = $productCollection->add($param);
            if ($result) {
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
            } else {
                return makeApiResponse(510, trans("shop-front.common.action_fail"));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除一个收藏
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $product_id = $request->product_id;
            if (empty($product_id)) {
                return makeApiResponse(510, trans("shop-front.common.data_error"));
            }
            ProductCollection::delete($this->memberId, $product_id);
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}