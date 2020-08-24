<?php
/**
 * 供应商平台商品管理相关接口
 * User: liyaohui
 * Date: 2020/6/29
 * Time: 17:56
 */

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Product;


use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierProduct;
use Illuminate\Http\Request;

class SupplierProductController extends BaseSupplierPlatformController
{
    /**
     * 获取产品列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('pageSize', 20);
            $filter = [
                // 库存状态 1 为警告库存
                'is_inventory' => $request->input('is_inventory', 0),
                // 产品状态1为出售中 0下架 仓库中 -1为售罄
                'status' => $request->input('status', null),
                // 产品类型 0 为实体产品，1 为虚拟产品，8=分销资格实体产品，9=分销资格虚拟产品
                'type' => $request->input('type', null),
                // 分类id数组
                'class' => $request->input('class', []),
                // 搜索的关键字
                'keyword' => $request->input('keyword', ''),
                // 排序规则 column为排序字段 order是升序还是降序 desc降序 asc升序
                'order_by' => $request->input('order_by', [
                    'column' => 'id',
                    'order' => 'asc'
                ]),
                'verify_status' => $request->input('verify_status', 1),
                'created_at_start'=>$request->input('created_at_start',null),
                'created_at_end'=>$request->input('created_at_end',null)
            ];
            $list = SupplierProduct::getList($filter, $page, $pageSize);
            $classList = SupplierProduct::getClassList();
            return makeApiResponseSuccess('ok', ['productList' => $list, 'classList' => $classList]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取产品数量统计
     * @return array
     */
    public function getProductCount()
    {
        try {
            $count = SupplierProduct::getProductCount($this->memberId);
            return makeApiResponseSuccess('ok', $count);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 修改产品状态
     * @param Request $request
     * @return array
     */
    public function editProductStatus(Request $request)
    {
        try {
            $product = $request->input('product_id', '');
            $status = $request->input('status', '');
            $update = SupplierProduct::editProductStatus($product, $status);
            if ($update !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '修改失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 修改产品审核状态
     * @param Request $request
     * @return array
     */
    public function editProductVerifyStatus(Request $request)
    {
        try {
            $status = $request->input('edit_status', '');
            $update = SupplierProduct::editProductVerifyStatus($request->all(), $status);
            if ($update !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '修改失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 检测商品编码是否重复
     * @param Request $request
     * @return array
     */
    public function checkSerialNumber(Request $request)
    {
        try {
            $num = $request->input('serial_number', '');
            $productId = $request->input('product_id', 0);
            $check = SupplierProduct::checkSerialNumber($num, $productId);
            if ($check === true) {
                return makeApiResponseSuccess('ok');
            } elseif ($check == 0) {
                return makeApiResponse(400, '商品编码最多20个字符');
            } elseif ($check == -1) {
                return makeApiResponse(400, '已存在相同商品编码');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取产品数据
     * @param ProductModel $product
     * @return array
     */
    public function getProductData($product)
    {
        try {
            $product = new SupplierProduct($product);
            if($product->getModel()->supplier_member_id != $this->memberId){
                throw new \Exception("你没有管理此商品的权限");
            }
            $data = $product->getProductData();
            $data['product_sku_num'] = ShopConfig::getProductSkuNum();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取添加产品需要的数据
     * @return array
     */
    public function getAddProductData()
    {
        try {
            return makeApiResponseSuccess('ok', [
                'classList' => SupplierProduct::getClassList(),
                'memberLevelList' => (new MemberLevel())->getList()["list"],
                'freightTemplateList' => SupplierProduct::getFreightTemplateList(),
                'product_sku_num' => ShopConfig::getProductSkuNum(),
                'paramTemplateList' => SupplierProduct::getProductParamTemplateList()
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 上传产品图片
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function uploadProductImage(Request $request)
    {
        $imagePath = SupplierProduct::uploadProductImage($request->file('image'));
        return makeApiResponseSuccess('ok', $imagePath);
    }

    /**
     * 上传产品sku图片
     * @param Request $request
     * @return array
     */
    public function uploadProductSkuImage(Request $request)
    {
        try {
            $imagePath = SupplierProduct::uploadProductSkuImage($request->file('skuImage'));
            return makeApiResponseSuccess('ok', $imagePath);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 上传产品视频封面图
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function uploadProductVideoPoster(Request $request)
    {
        $imagePath = SupplierProduct::uploadProductVideoPoster($request->file('image'));
        return makeApiResponseSuccess('ok', ['bigImage' => $imagePath]);
    }

    /**
     * 保存产品数据
     * @param Request $request
     * @return array
     */
    public function saveProductData(Request $request)
    {
        try {
            $productDetail = $request->input('productData');
            $skuData = $request->input('skuData');
            $skus = $request->input('skus');
            $product = new SupplierProduct($productDetail['id']);
            if($product->getModel() && $product->getModel()->supplier_member_id != $this->memberId){
                throw new \Exception("你没有管理此商品的权限");
            }
            $save = $product->store($productDetail, $skus, $skuData);
            return makeApiResponseSuccess('ok', $save);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 导出产品列表
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportProductList(Request $request)
    {
        try {
            $filter = [
                // 库存状态 1 为警告库存
                'is_inventory' => $request->input('is_inventory', 0),
                // 产品状态1为出售中 0下架 仓库中 -1为售罄
                'status' => $request->input('status', null),
                // 产品类型 0 为实体产品，1 为虚拟产品，8=分销资格实体产品，9=分销资格虚拟产品
                'type' => $request->input('type', null),
                // 分类id数组
                'class' => $request->input('class', []),
                // 搜索的关键字
                'keyword' => $request->input('keyword', ''),
                // 排序规则 column为排序字段 order是升序还是降序 desc降序 asc升序
                'order_by' => $request->input('order_by', [
                    'column' => 'created_at',
                    'order' => 'asc'
                ]),
                'product_ids' => $request->input('product_ids', 0),
                'verify_status' => $request->input('verify_status', 1)
            ];
            $list = SupplierProduct::getExportList($filter);

            return SupplierProduct::exportProductList($list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}