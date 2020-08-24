<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Center;

use Illuminate\Http\Request;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Browse\Browse;

/**
 * 浏览记录（需登录）
 * Class MemberBrowseController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\Center
 */
class MemberBrowseController extends BaseController
{
    protected $siteId = 0;
    private $Borwse = null;

    public function __construct()
    {
        parent::__construct();
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->Borwse = new Browse();
    }

    /**
     * 浏览列表
     * @param Request $request
     * @return array
     */
    public function getBrowse(Request $request)
    {
        try {
            $params = $request->toArray();
            $params['member_id'] = $this->memberId;
            $data = $this->Borwse->getList($params);
            // 获取产品数据
            if ($data && $data['list']) {
                $productIds = $data['list']->pluck('product_id');
                if ($productIds) {
                    // 读取产品数据
                    $productList = [];
                    $productData = Product::getList([
                        'product_ids' => $productIds,
                        'member_id' => $params['member_id'],
                    ], 1, 9999);
                    if ($productData && $productData['list']) {
                        foreach ($productData['list'] as $productDataItem) {
                            $productList[$productDataItem['id']] = $productDataItem;
                        }
                    }

                    foreach ($data['list'] as $dataItem) {
                        $productId = $dataItem->product_id;
                        if (array_key_exists($productId, $productList)) {
                            $productData = $productList[$productId];
                            foreach ($productData as $productKey => $productVal) {
                                if (in_array($productKey, ['id', 'created_at', 'updated_at'])) continue;
                                $dataItem->$productKey = $productVal;
                            }
                        }
                    }
                }
            }


            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 清空浏览记录
     * @return array|void
     */
    public function clear()
    {
        try {
            return $this->Borwse->deleteByMemberId($this->memberId);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除指定的浏览记录
     */
    public function delete(Request $request)
    {
        try {
            $ids = $request->get('ids');
            $this->Borwse->delete($ids,$this->memberId);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}