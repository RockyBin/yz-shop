<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Product;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentSaleRewardSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentBaseSetting;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Dealer\DealerSaleRewardSetting;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Product\ProductImport;
use App\Modules\ModuleShop\Libs\Product\ProductSku;
use App\Modules\ModuleShop\Libs\Shop\ShoppingCart;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\Supplier\SupplierAdmin;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use YZ\Core\Common\Export;

class ProductController extends BaseSiteAdminController
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
                    'column' => 'created_at',
                    'order' => 'asc'
                ]),
                'keyword_type' => $request->input('keyword_type', 1),
                'is_supplier_product' => $request->input('is_supplier_product', null)
            ];
            $list = Product::getList($filter, $page, $pageSize);
            $classList = Product::getClassList();
            return makeApiResponseSuccess('ok', ['productList' => $list, 'classList' => $classList]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取产品二维码和链接
     * @param Request $request
     * @param $id
     * @return array
     */
    public function getProductQrCode(Request $request, $id)
    {
        $size = $request->input('size', 200);
        $margin = $request->input('margin', 10);
        $url = Product::getProductUrl($id);
        $qrcode = QrCode::format('png')
            ->size($size)
            ->encoding('UTF-8')
            ->errorCorrection('M')
            ->margin($margin)
            ->generate($url);
        return makeApiResponseSuccess('ok', ['image' => "data:image/png;base64," . base64_encode($qrcode), 'url' => $url]);
//        imagepng("data:image/png;base64," .base64_encode($qrcode));
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
//                // 产品状态1为出售中 0下架 仓库中 -1为售罄
                'status' => $request->input('status', null),
                // 产品类型 0 为实体产品，1 为虚拟产品，8=分销资格实体产品，9=分销资格虚拟产品
                'type' => $request->input('type', null),
                // 分类id数组
                'class' => $request->input('class', []),
                // 搜索的关键字
                'keyword' => $request->input('keyword', ''),
//                // 排序规则 column为排序字段 order是升序还是降序 desc降序 asc升序
                'order_by' => $request->input('order_by', [
                    'column' => 'created_at',
                    'order' => 'asc'
                ]),
                'product_ids' => $request->input('product_ids', 0),
                'keyword_type' => $request->input('keyword_type', 1),
                'is_supplier_product' => $request->input('is_supplier_product', null)
            ];
            $list = Product::getExportList($filter);

            return Product::exportProductList($list, $filter['status']);
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
            $product = $request->input('productId', '');
            $status = $request->input('status', '');
            $update = Product::editProductStatus($product, $status);
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
            $check = Product::checkSerialNumber($num, $productId);
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
            $product = new Product($product);
            $data = $product->getProductData();
            $data['agent_base_config'] = AgentBaseSetting::getCurrentSiteSettingFormat();
            $data['agent_sale_reward_config'] = AgentSaleRewardSetting::getCurrentSiteSettingFormat();
            // 供应商商品不参加云仓
            if ($data['productData']['supplier_member_id'] > 0) {
                // 获取供应商信息
                $supplier = SupplierAdmin::getSupplierInfo($data['productData']['supplier_member_id']);
                $data['productData']['supplier_name'] = $supplier['name'];
                $data['productData']['supplier_status'] = $supplier['status'];
            } else {
                $data['dealer_sale_reward_config'] = DealerSaleRewardSetting::getCurrentSiteSettingFormat();
                $data['dealer_config'] = [
                    'levels' => DealerLevel::getLevelTree()
                ];
            }
            $data['area_agent_config'] = [
                'setting' => AreaAgentBaseSetting::getCurrentSiteSetting(),
                'levels' => AreaAgentLevelModel::query()->where('site_id',getCurrentSiteId())->orderBy('weight','asc')->get()
            ];
            $data['product_sku_num'] = ShopConfig::getProductSkuNum();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getProductSkuInfo(Request $request)
    {
        try {
            if (!$request->product_id) {
                return makeApiResponseFail('不存在');
            }
            $product = new Product($request->product_id);
            $data = $product->getSkuInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 产品价格分销数据同步
     * @return array
     */
    public function synchroPriceRule()
    {
        $product = new Product();
        $product->synchroPriceRule();
    }

    /**
     * 获取添加产品需要的数据
     * @return array
     */
    public function addProduct()
    {
        try {
            return makeApiResponseSuccess('ok', [
                'classList' => Product::getClassList(),
                'memberLevelList' => (new MemberLevel())->getList()["list"],
                'freightTemplateList' => Product::getFreightTemplateList(),
                'distributionLevelList' => (new DistributionLevel())->getList(),
                'distributionLevel' => (new DistributionSetting())->getCurrentSiteSetting()->level,
                'agent_base_config' => AgentBaseSetting::getCurrentSiteSettingFormat(),
                'agent_sale_reward_config' => AgentSaleRewardSetting::getCurrentSiteSettingFormat(),
                'dealer_sale_reward_config' => DealerSaleRewardSetting::getCurrentSiteSettingFormat(),
                'area_agent_base_config' => AreaAgentBaseSetting::getCurrentSiteSetting(),
                'dealer_config' => [
                    'levels' => DealerLevel::getLevelTree()
                ],
                'area_agent_config' => [
                    'setting' => AreaAgentBaseSetting::getCurrentSiteSetting(),
                    'levels' => AreaAgentLevelModel::query()->where('site_id',getCurrentSiteId())->orderBy('weight','asc')->get()
                ],
                'product_sku_num' => ShopConfig::getProductSkuNum()
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getMaxCommission()
    {
        try {
            return makeApiResponseSuccess('ok', [
                'max' => Product::getMaxDistributionCommission()
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 上传产品图片
     * @param Request $request
     * @return array
     */
    public function uploadProductImage(Request $request)
    {
        $imagePath = Product::uploadProductImage($request->file('image'));
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
            $imagePath = Product::uploadProductSkuImage($request->file('skuImage'));
            return makeApiResponseSuccess('ok', $imagePath);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 上传产品视频封面图
     * @param Request $request
     * @return array
     */
    public function uploadProductVideoPoster(Request $request)
    {
        $imagePath = Product::uploadProductVideoPoster($request->file('image'));
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
            $product = new Product($productDetail['id']);
            $save = $product->store($productDetail, $skus, $skuData);
            return makeApiResponseSuccess('ok', $save);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function saveSkuInventory(Request $request)
    {
        try {
            if (!$request->product_id) {
                return makeApiResponseFail('请传入产品ID');
            }
            if ($request->warning_inventory) {
                (new Product($request->product_id))->saveWarningInventory($request->warning_inventory);
            }
            if ($request->skus) {
                ProductSku::saveSkuInventory($request->product_id, $request->skus);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }

    }

    /**
     * 获取分销新建的产品详情
     * @param Request $request
     * @return array
     */
    public function getFenxiaoProductData(Request $request)
    {
        try {
            $productId = $request->input('productId', '');
            $product = new Product($productId);
            $productData = $product->getFenxiaoProductData();
            return makeApiResponseSuccess('ok', $productData);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getFenxiaoShopProductData(Request $request)
    {
        try {
            $productId = $request->input('productId', '');
            // $product = new Product($productId);
            // $productData = $product->getFenxiaoShopProductData();
            $productData = Product::getList(['product_ids' => myToArray($productId)]);
            return makeApiResponseSuccess('ok', $productData);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存分销产品数据
     * @param Request $request
     * @return array
     */
    public function saveFenxiaoProductData(Request $request)
    {
        try {
            $productData = $request->input('productData');
            $product = new Product($productData['id']);
            $product = $product->saveFenxiaoProduct($productData);
            return makeApiResponseSuccess('ok', ['id' => $product->id]);
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
            $count = Product::getProductCount();
            return makeApiResponseSuccess('ok', $count);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function import(Request $request)
    {
        try {
            if (!$request->import_file) {
                makeApiResponseFail('请传入导入文件');
            }
            $importArray = Excel::toArray([], $request->file('import_file'));
            //去掉表头
            unset($importArray[0][0]);

            if ($request->import_file->getClientSize() <= 0) {
                throw new \Exception('上传的文件不能为空');
            }
            $newImportArray = [];
            foreach ($importArray[0] as &$item) {
                $newItem['serial_number'] = $item[0];
                $newItem['name'] = $item[1];
                $newItem['sku1'] = $item[2];
                $newItem['sku2'] = $item[3];
                $newItem['sku3'] = $item[4];
                $newItem['inventory'] = intval($item[5]);
                if (!($newItem['serial_number'] == null && $newItem['name'] == null && $newItem['inventory'] == 0)) {
                    $newImportArray[] = $newItem;
                };
            }
            if (count($newImportArray) > 3000) {
                throw new \Exception('上传的数据不能多于3000条');
            }
            $data = Product::import($newImportArray);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function importError(Request $request)
    {
        try {
            if (!$request->error_data) {
                makeApiResponseFail('请传入错误数据');
            }
            $error_data = $request->error_data;
            $exportHeadings = [
                '商品编码',
                '商品名称',
                '规格1',
                '规格2',
                '规格3',
                '库存',
                '异常提示'
            ];
            $exportData = [];
            if ($error_data) {
                foreach ($error_data as $item) {
                    $exportData[] = [
                        $item['serial_number'],
                        $item['name'],
                        $item['sku1'],
                        $item['sku2'],
                        $item['sku3'],
                        $item['inventory'],
                        $item['error_data'],
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'DaoruYC-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function importMB()
    {
        try {
            $exportHeadings = [
                '商品编码',
                '商品名称',
                '规格1',
                '规格2',
                '规格3',
                '库存'
            ];
            $exportData[] = [
                "如123456",
                "华为手机",
                "M80",
                "红色",
                "64G",
                "3444"
            ];

            $exportObj = new Export(new Collection($exportData), 'KucunMB-' . date("YmdHis") . '.xlsx', $exportHeadings);
            $style = ['A1' => ['fill' => ['fillType' => 'none', 'color' => ['rgb' => '#FFFF00']]]];
            $exportObj->setStyle($style);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function editSort(Request $request){
        try {
            $pro = new Product($request->input('id'));
            $pro->editSort($request->input('sort'));
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
