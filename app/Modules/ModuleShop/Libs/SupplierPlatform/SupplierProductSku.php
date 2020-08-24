<?php
/**
 * 供应商商品sku逻辑类
 * User: liyaohui
 * Date: 2020/6/29
 * Time: 15:07
 */

namespace App\Modules\ModuleShop\Libs\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use YZ\Core\Site\Site;
class SupplierProductSku

{
    private $_site = null;
    private $_product = null;

    public function __construct($product, $site = null)
    {
        $this->_site = $site;
        if (!$this->_site) {
            $this->_site = Site::getCurrentSite();
        }
        if ($product && $product instanceof ProductModel) {
            $this->_product = $product;
        } else {
            $this->_product = ProductModel::query()->findOrFail($product);
        }
    }

    public function getSkuNameByProduct()
    {
        return $this->_product->productSkuName();
    }

    public function getSkuValueByProduct()
    {
        return $this->_product->productSkuValue();
    }

    public function getSkusByProduct()
    {
        return $this->_product->productSkus();
    }

    /**
     * 获取skus列表里的价格最小值  price、market_price等
     * @param array $skus skus记录
     * @param string $field 要获取的字段
     * @return mixed
     */
    public static function getSkusMinPrice($skus, $field = 'price')
    {
        return collect($skus)->min($field);
    }

    /**
     * 获取skus列表里的价格最大值  price、market_price等
     * @param array $skus skus记录
     * @param string $field 要获取的字段
     * @return mixed
     */
    public static function getSkusMaxPrice($skus, $field = 'price')
    {
        return collect($skus)->max($field);
    }

    /** 保存 SKU 数据
     * @param $skus
     * @param array $skuInfo
     * @param bool $isNew
     * @return bool
     * @throws \Exception
     */
    public function editProductSkuInfo($skus, $skuInfo = [], $isNew = false)
    {
        $product = $this->_product;
        // skus 不能为空
        if (empty($skus)) {
            return false;
        }
        // 没有sku name 说明是没有多规格
        if (empty($skuInfo)) {
            // 没有sku name 说明是无规格产品  为了统一，
            // 无规格产品也会有一条skus的记录 sku_code为0即可
            $originalSkus = $product->productSkus()->where('sku_code', '0')->first();
            $skus = $skus[0];
            // 先保存SKU数据
            if (!$isNew && $originalSkus) {
                // 如果从自定义规则转换到不开启或系统默认，要将原来自定义的规则删除
                $originalSkus->price = $skus['price'];
                $originalSkus->market_price = $skus['market_price'];
                $originalSkus->supply_price = $skus['supply_price'];
                $originalSkus->supplier_price = $skus['supplier_price'];
                $originalSkus->inventory = $skus['inventory'];
                $originalSkus->weight = $skus['weight'];
                $originalSkus->serial_number = $skus['serial_number'];
                $originalSkus->save();
            } else {
                // 没有sku_code 为 0的记录 说明之前有多规格 先把之前的多规格删掉
                if (!$isNew && !$originalSkus) {
                    $this->deleteAllSku($product);
                }
                $insertData = $skus;
                // 新的产品 或者 没有sku记录时 直接保存一条新的记录
                $skuModal = $product->productSkus()->create($insertData);
            }
        } else {
            if ($isNew) {
                $this->saveSkuData($skus, $skuInfo);
            } else {
                // 编辑的要做一下处理
                $this->beforeSaveSkuData($skus, $skuInfo);
            }
        }
    }

    /**
     * 删除产品的所有sku信息
     * @param ProductModel $product
     */
    public function deleteAllSku(ProductModel $product)
    {
        $product->productPriceMemberRule()->delete();
        $product->productPriceFenxiaoRule()->delete();
        $product->productPriceAgentOrderCommissionRule()->delete();
        $product->productPriceAgentSaleRewardRule()->delete();
        $product->productPriceDealerSaleRewardRule()->delete();
        $product->productPriceCloudStockRule()->delete();
        // 需要删除图片
        $productValues = $product->productSkuValue();
        $bigImages = $productValues->pluck('big_image')->toArray();
        $this->deleteSkuImages($bigImages);
        $product->productSkuName()->delete();
        $productValues->delete();
        $product->productSkus()->delete();
    }

    /**
     * 保存sku数据到数据库
     * @param $skus skus 的数据
     * @param $skuInfo sku_name 和 sku_value 的关联数组
     * @throws \Exception
     */
    public function saveSkuData($skus, $skuInfo)
    {
        $product = $this->_product;
        // 因为新加的sku value的id是临时的 所以保存value之后需要记录下来 给skus用
        $valueIds = [];
        foreach ($skuInfo as $name) {
            // 先保存sku name
            // 是更新还是新加
            $hasImage = $name['has_image'] ? 1 : 0;
            if (stripos($name['id'], 'new_') !== false) {
                $skuName = [
                    'site_id' => $this->_site->getSiteId(),
                    'has_image' => $hasImage,
                    'name' => $name['name'],
                ];
                $skuName = $product->productSkuName()->create($skuName);
            } else {
                $skuName = $product->productSkuName()->where('id', $name['id'])->first();
                $skuName->has_image = $hasImage;
                $skuName->name = $name['name'];
                $skuName->save();
            }

            // 保存name 之后 拿到name的数据 再保存value
            foreach ($name['values'] as $val) {
                $skuValue = [
                    'site_id' => $this->_site->getSiteId(),
                    'sku_name_id' => $skuName['id'],
                    'value' => $val['value'],
                    'small_image' => NULL,
                    'big_image' => NULL
                ];
                if ($skuName['has_image']) {
                    $skuValue['small_image'] = $val['small_image'];
                    $skuValue['big_image'] = $val['big_image'];
                }
                // 是更新还是新加
                if (stripos($val['id'], 'new_') !== false) {
                    $skuValue = $product->productSkuValue()->create($skuValue);
                } else {
                    $skuValueData = $product->productSkuValue()->where('id', $val['id'])->first();
                    $skuValueData->sku_name_id = $skuValue['sku_name_id'];
                    $skuValueData->value = $skuValue['value'];
                    // 保存的时候对图片做一下处理 不再删除小图
                    beforeSaveImage([$skuValueData->big_image], [$skuValue['big_image']]);
                    $skuValueData->small_image = $skuValue['small_image'];
                    $skuValueData->big_image = $skuValue['big_image'];
                    $skuValueData->save();
                }
                // 记录临时value ID 和数据库生成id的关系
                $valueIds[$val['id']] = $skuValue['id'];
            }
        }
        // 最后保存skus
        $newSkusData = [];
        $updateSkusData = [];
        foreach ($skus as $sku) {
            $sku['site_id'] = $this->_site->getSiteId();
            $sku['sku_code'] = $this->replaceSkuCode($sku['sku_code'], $valueIds);
            // id 为0 说明是新加的
            if (!$sku['id']) {
                $sku['market_price'] = null;
                $newSkusData[] = $sku;
            } else {
                // 手动更新 updated_at
                $sku['updated_at'] = Carbon::now();
                $updateSkusData[] = $sku;
            }
        }
        // 新建
        if (!empty($newSkusData)) {
            $product->productSkus()->createMany($newSkusData);
        }
        // 更新
        if (!empty($updateSkusData)) {
            (new ProductSkusModel())->updateBatch($updateSkusData);
        }
    }

    /**
     * 编辑sku的操作
     * @param array $skus skus 的数据
     * @param array $skuInfo sku name  和 sku value的关联数组
     * @throws \Exception
     */
    public function beforeSaveSkuData($skus, $skuInfo)
    {
        $product = $this->_product;
        // 旧的sku name数据
        $originalSkuNames = $product->productSkuName;
        // 如果没有sku name 说明以前是没有多规格的 当做新加处理
        if ($originalSkuNames->isEmpty()) {
            // 把单规格的删掉
            $product->productSkus()->delete();
            $this->saveSkuData($skus, $skuInfo);
        } else {
            $skuInfoCollect = collect($skuInfo);
            // 查询出来删掉的sku数据
            // 查询sku name
            foreach ($originalSkuNames as $name) {
                // sku name 是否还存在
                if (!$skuInfoCollect->contains('id', $name->id)) {
                    // 如果是要删除的 要同时删掉sku value
                    $values = $name->skuValues();
                    // 要同时删除大图
                    if ($name->has_image) {
                        $bigImage = $values->pluck('big_image')->toArray();
                        $this->deleteSkuImages($bigImage);
                    }
                    $values->delete();
                    $name->delete();
                }
            }
            // 旧的sku value
            $originalSkuValues = $product->productSkuValue;
            // 先取出要保存的所有sku value
            $allValues = $skuInfoCollect->flatten(2)->filter(function ($value) {
                return is_array($value);
            });
            // 查询需要删除的sku value
            $deleteImages = [];
            foreach ($originalSkuValues as $val) {
                if (!$allValues->contains('id', $val->id)) {
                    $deleteImages[] = $val->big_image;
                    $val->delete();
                }
            }
            // 删掉大图
            $this->deleteSkuImages($deleteImages);
            // 旧的skus数据
            $originalSkus = $product->productSkus;

            $skusCollect = collect($skus);

            foreach ($originalSkus as $sku) {
                if (!$skusCollect->contains('id', $sku->id)) {
                    $sku->delete();
                }
            }
            $this->saveSkuData($skus, $skuInfo);
        }
    }

    /**
     * 替换sku_code中的临时value id
     * @param string $skuCode 用逗号分隔的sku value id
     * @param array $code 保存到数据库sku value id和临时id对照
     * @return string  替换后的sku_code 形如：,342,434,
     */
    private function replaceSkuCode($skuCode, $code = [])
    {
        $skuArr = explode(',', $skuCode);
        $replaceRes = ',';
        foreach ($skuArr as $item) {
            $replaceRes .= ($code[$item] ?: $item) . ',';
        }
        return $replaceRes;
    }

    /**
     * 删除图片
     * @param $images
     * @return bool
     */
    public function deleteSkuImages($images)
    {
        if (!$images) {
            return false;
        }
        if (is_string($images)) {
            $images = [$images];
        }
        $sitePath = Site::getSiteComdataDir('', true);
        foreach ($images as $img) {
            if ($img) {
                File::delete($sitePath . $img);
            }
        }
        return true;
    }

    /**
     * 修改库存
     * @param $productId
     * @param $skus
     * @throws \Exception
     */
    public static function saveSkuInventory($productId, $skus)
    {
        try {
            (new ProductSkusModel())->updateBatch($skus);

            // 如果是已售罄的商品 检查一下 如果增加了库存 则把售罄状态取消
            $collectSkus = collect($skus);
            $inventory = $collectSkus->sum('inventory');
            $product = ProductModel::find($productId);
            if ($inventory > 0 && $product->is_sold_out == \YZ\Core\Constants::Product_Sold_Out) {
                $product->is_sold_out = \YZ\Core\Constants::Product_No_Sold_Out;
            } elseif ($inventory <= 0) {
                // 如果修改了库存为0 更新售罄时间
                if ($product->is_sold_out != \YZ\Core\Constants::Product_Sold_Out) {
                    $product->sold_out_at = Carbon::now();
                    $product->is_sold_out = \YZ\Core\Constants::Product_Sold_Out;
                }
            }
            $product->save();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 根据产品ID获取最低的SKU记录
     * @param $productId
     * @return bool
     */
    public static function getMinSku($productId)
    {
        return ProductSkusModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('product_id', $productId)
            ->orderBy('price')
            ->first();
    }
}