<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 2019/3/7
 * Time: 15:15
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Url;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use App\Modules\ModuleShop\Libs\Link\LinkConstants;
use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSettingModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Product\ProductClass;
use App\Modules\ModuleShop\Libs\UI\PageMobi;
use Illuminate\Http\Request;

class UrlManageController extends BaseAdminController
{
    /**
     * 获取所有静态的链接
     * @return array
     */
    public function getStaticUrl()
    {
        try {
            // 静态的url
            $staticUrl = LinkHelper::getAllStaticUrl();

            return makeApiResponseSuccess('ok', [
                'static_url' => $staticUrl
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getPageUrl(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $keyword = $request->input('keyword', null);
            $type = $request->input('type', null);
            $list = PageMobi::getPageList(['keyword' => $keyword,'type' => $type], $page, $pageSize);
            $pageUrl = LinkHelper::getUrl(LinkConstants::LinkType_Page, '{page_id}');
            return makeApiResponseSuccess('ok', [
                'page_list' => $list,
                'page_url' => $pageUrl,
                'type' => LinkConstants::LinkType_Page
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 产品分类链接
     * @return array
     */
    public function getProductClassUrl()
    {
        try {
            $classList = ProductClass::getClassList(['status' => 1]);
            // 产品列表url
            $productListUrl = LinkHelper::getUrl(LinkConstants::LinkType_ProductList, '{class_id}');
            return makeApiResponseSuccess('ok', [
                'class_list' => $classList['list'],
                'product_list_url' => $productListUrl,
                'type' => LinkConstants::LinkType_ProductList
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 产品详情url
     * @param Request $request
     * @return array
     */
    public function getProductDetailUrl(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $keyword = $request->input('keyword', '');
            $class = $request->input('class', '');
            $class = $class == -1 ? '' : $class;
            // 产品详情url
            $productDetailUrl = LinkHelper::getUrl(LinkConstants::LinkType_ProductDetail, '{product_id}');
            $productList = Product::getList(
                ['keyword' => $keyword, 'class' => $class],
                $page,
                $pageSize,
                'tbl_product.id, tbl_product.name, tbl_product.price, tbl_product.small_images',
                true
            );
            if (count($productList['list']) > 0) {
                foreach ($productList['list'] as &$pro) {
                    $class = collect($pro['product_class']);
                    $class = $class->pluck('class_name')->all();
                    $class = !$class ? '无分类' : implode(' ', $class);
                    $pro['product_class'] = $class;
                    $pro['price'] = moneyCent2Yuan($pro['price']);
                    $pro['small_images'] = explode(',', $pro['small_images'])[0];
                }
            }
            return makeApiResponseSuccess('ok', [
                'product_detail_url' => $productDetailUrl,
                'product_list' => $productList,
                'type' => LinkConstants::LinkType_ProductDetail
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 拼团url
     * @param Request $request
     * @return array
     */
    public function getGroupBuyingUrl(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $keyword = $request->input('keyword', '');
            // 产品详情url
            $groupBuyingDetailUrl = LinkHelper::getUrl(LinkConstants::LinkType_GroupBuyingDetail, '{id}');
            $list = GroupBuyingSetting::getList(
                ['status' => 2, 'keyword' => $keyword],
                $page,
                $pageSize
            );

            return makeApiResponseSuccess('ok', [
                'groupbuying_detail_url' => $groupBuyingDetailUrl,
                'groupbuying_list' => $list,
                'type' => LinkConstants::LinkType_GroupBuyingDetail
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}