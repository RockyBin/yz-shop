<?php
/**
 * 供应商商品管理逻辑
 * User: liyaohui
 * Date: 2020/6/23
 * Time: 14:03
 */

namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use function foo\func;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;

class SupplierProductAdmin
{
    protected $productId = 0;
    protected $siteId = 0;

    public function __construct($productId)
    {
        if (!$productId) {
            throw new \Exception('数据错误');
        }
        $this->productId = $productId;
        $this->siteId = getCurrentSiteId();
    }

    /**
     * 获取供应商待审核商品列表
     * @param $params
     * @return array
     */
    public static function getWaitVerifyProductList($params)
    {
        $siteId = getCurrentSiteId();
        $query = ProductModel::query()
            ->leftJoin('tbl_supplier as sup', 'sup.member_id', 'tbl_product.supplier_member_id')
            ->leftJoin('tbl_product_skus as sku', 'sku.product_id', 'tbl_product.id')
            ->where('tbl_product.site_id', $siteId)
            ->where('sup.status', SupplierConstants::SupplierStatus_Active)
            ->where('tbl_product.supplier_member_id', '>', 0)
            ->where('tbl_product.verify_status', Constants::Product_VerifyStatus_WaitReview)
            ->where('tbl_product.status', '<>',Constants::Product_Status_Delete);

        // 搜索条件
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $keywordType = intval($params['keyword_type']);
            switch ($keywordType) {
                // 供应平台搜索
                case 1:
                    $query->where('sup.name', 'like', $keyword);
                    break;
                // 商品搜索
                case 2:
                    $query->where(function ($query) use ($keyword) {
                        $query->where('sku.serial_number', 'like', $keyword)
                            ->orWhere('tbl_product.name', 'like', $keyword);
                    });
                    break;
            }
        }
        // 提交审核时间
        if (isset($params['created_at_start'])) {
            $query->where('tbl_product.submit_verify_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $query->where('tbl_product.submit_verify_at', '<=', $params['created_at_end']);
        }

        $page = $params['page'] ?: 1;
        $pageSize = $params['page_size'] ?: 20;
        $total = (clone $query)->selectRaw('count(distinct tbl_product.id) as total')->first();
        $total = $total ? $total['total'] : 0;
        $lastPage = ceil($total / $pageSize);
        $list = $query->orderByDesc('tbl_product.submit_verify_at')
            ->groupBy('tbl_product.id')
            ->selectRaw('sum(sku.inventory) as inventory, count(sku.id) as sku_count')
            ->addSelect([
                'tbl_product.id',
                'tbl_product.name as product_name',
                'sup.name as supplier_name',
                'tbl_product.price',
                'tbl_product.supply_price',
                'tbl_product.supplier_price',
                'tbl_product.submit_verify_at',
                'sku.sku_code',
                'tbl_product.small_images',
                'tbl_product.status'
            ])
            ->forPage($page, $pageSize)
            ->get();
        $list = self::formatList($list);
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    /**
     * 格式化列表数据
     * @param $list
     * @return mixed
     */
    public static function formatList($list)
    {
        foreach ($list as &$item) {
            $item->price = moneyCent2Yuan($item->price);
            $item->supply_price = moneyCent2Yuan($item->supply_price);
            $item->supplier_price = moneyCent2Yuan($item->supplier_price);
            if (isset($item->small_images)) {
                $item->image = explode(',', $item->small_images)[0];
            }
        }
        return $list;
    }

    /**
     * 获取单个待审核商品相关数据
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getWaitVerifyProductInfo()
    {
        $product = ProductModel::query()
            ->leftJoin('tbl_supplier as sup', 'sup.member_id', 'tbl_product.supplier_member_id')
            ->where('tbl_product.site_id', $this->siteId)
            ->where('tbl_product.id', $this->productId)
            ->where('tbl_product.supplier_member_id', '>', 0)
            ->where('sup.status', SupplierConstants::SupplierStatus_Active)
            ->select(['tbl_product.id', 'tbl_product.name as product_name', 'sup.name as supplier_name', 'tbl_product.verify_status'])
            ->with(['productSkus' => function ($query) {
                $query->select(['id', 'product_id', 'price', 'supply_price', 'supplier_price', 'inventory', 'sku_code']);
            }, 'productSkuName' => function ($query) {
                $query->select(['id', 'product_id', 'name']);
            }, 'productSkuValue' => function ($query) {
                $query->select(['id', 'product_id', 'value', 'sku_name_id']);
            }])
            ->first();
        if (!$product) {
            throw new \Exception('商品不存在');
        }
        if ($product->verify_status !== Constants::Product_VerifyStatus_WaitReview) {
            throw new \Exception('商品状态错误', 411);
        }
        // sku
        $product->product_skus = self::formatList($product->productSkus);

        return $product;
    }

    /**
     * 审核单个商品之前 检测处理一下数据
     * @param $ids
     * @param $params
     * @return array
     * @throws \Exception
     */
    public static function beforeVerifyProductsSku($ids, $params)
    {
        // 检测sku价格
        $skuPrice = $params['sku_price'];
        if (!$skuPrice || !is_array($skuPrice)) {
            throw new \Exception('请输入商品价格');
        }

        // 先获取所有的要修改的sku
        $originalSkus = ProductSkusModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('product_id', $ids)
            ->select(['id', 'price', 'supply_price'])
            ->get();
        if ($originalSkus->count() != count($skuPrice)) {
            throw new \Exception('请输入所有的sku价格');
        }
        $skuPrice = collect($skuPrice)->keyBy('id')->toArray();
        $updateData = [];
        // 循环检测比对
        foreach ($originalSkus as $item) {
            $newPrice = $skuPrice[$item['id']];
            // 数据转换
            $newPrice['price'] = moneyYuan2Cent($newPrice['price']);
            $newPrice['supply_price'] = moneyYuan2Cent($newPrice['supply_price']);
            if ($newPrice['price'] > 0 && $newPrice['supply_price'] > 0) {
                // 比对是否需要更新
                if ($newPrice['price'] != $item['price'] || $newPrice['supply_price'] != $item['supply_price']) {
                    $updateData[] = [
                        'id' => $item['id'],
                        'price' => $newPrice['price'],
                        'supply_price' => $newPrice['supply_price'],
                    ];
                    $item['price'] = $newPrice['price'];
                    $item['supply_price'] = $newPrice['supply_price'];
                }
            } else {
                throw new \Exception('请输入正确的价格');
            }
        }
        $returnData = ['sku_price' => $updateData];
        if ($updateData) {
            $returnData['min_price'] = $originalSkus->min('price');
            $returnData['min_supply_price'] = $originalSkus->min('supply_price');
        }
        return $returnData;
    }

    /**
     * 审核供应商商品
     * @param number|array $ids 要审核的商品id
     * @param array $params 其他参数
     * @return int
     * @throws \Exception
     */
    public static function verifyProducts($ids, $params)
    {
        if (!$ids && !$params['is_all']) {
            throw new \Exception('请选择要审核的商品');
        }
        $verifyStatus = $params['verify_status'] == 1 ? Constants::Product_VerifyStatus_Active : Constants::Product_VerifyStatus_Refuse;
        $status = $params['status'] ? Constants::Product_Status_Sell : Constants::Product_Status_Warehouse;
        $rejectReason = trim($params['reject_reason']);
        $now = Carbon::now();
        $updateData = [
            'verify_status' => $verifyStatus,
            'status' => $status,
            'tbl_product.updated_at' => $now,
            'tbl_product.verify_at' => $now
        ];
        if (Constants::Product_VerifyStatus_Refuse === $verifyStatus) {
            if (!$rejectReason) {
                throw new \Exception('请输入拒绝原因');
            }
            $updateData['verify_reject_reason'] = $rejectReason;
        }
        DB::beginTransaction();
        try {
            $query = DB::table('tbl_product')
                ->where('tbl_product.site_id', getCurrentSiteId())
                ->where('tbl_product.verify_status', Constants::Product_VerifyStatus_WaitReview)
                ->where('tbl_product.supplier_member_id', '>', 0);
            if (!$params['is_all']) {
                if (is_array($ids)) {
                    $query->whereIn('id', $ids);
                } else {
                    $query->where('id', $ids);
                    // 不是批量审核的 检测一下价格
                    $checkData = self::beforeVerifyProductsSku($ids, $params);
                    // 需要更新价格的
                    if ($checkData['sku_price']) {
                        $updateData['price'] = $checkData['min_price'];
                        $updateData['supply_price'] = $checkData['min_supply_price'];
                        $skuModel = new ProductSkusModel();
                        $skuModel->updateBatch($checkData['sku_price']);
                    }
                }
            } else {
                if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
                    $keyword = '%' . $params['keyword'] . '%';
                    $keywordType = intval($params['keyword_type']);
                    switch ($keywordType) {
                        // 供应平台搜索
                        case 1:
                            $query->leftJoin('tbl_supplier as sup', 'sup.member_id', 'tbl_product.supplier_member_id')
                                ->where('sup.name', 'like', $keyword);
                            break;
                        // 商品搜索
                        case 2:
                            $query->leftJoin('tbl_product_skus as sku', 'sku.product_id', 'tbl_product.id')
                                ->where(function ($query) use ($keyword) {
                                    $query->where('sku.serial_number', 'like', $keyword)
                                        ->orWhere('tbl_product.name', 'like', $keyword);
                                });
                            break;
                    }
                }
                // 申请日期
                if (isset($params['created_at_start'])) {
                    $query->where('tbl_product.created_at', '>=', $params['created_at_start']);
                }
                if (isset($params['created_at_end'])) {
                    $query->where('tbl_product.created_at', '<=', $params['created_at_end']);
                }
            }
            // 如果是上架商品 要检测供应商的状态
            if ($updateData['status'] === Constants::Product_Status_Sell) {
                $isCencel = (clone $query)->leftJoin('tbl_supplier as sup', 'sup.member_id', 'tbl_product.supplier_member_id')
                    ->where('sup.status', SupplierConstants::SupplierStatus_Cancel)
                    ->exists();
                // 如果有禁用的供应商 不可以保存
                if ($isCencel) {
                    throw new \Exception('供应商被禁用', 410);
                }
            }
            if ($status == Constants::Product_Status_Sell) {
                // 更新上架时间
                $updateData['sell_at'] = $now;
            }
            $isDel = (clone $query)->where('status', Constants::Product_Status_Delete)->exists();
            if($isDel){
                throw new \Exception('供应商已删除该商品', 411);
            }
            $update = $query->update($updateData);
            DB::commit();
            return $update;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}