<?php
/**
 * 云仓商品 前端提货 会员中心用
 * User: liyaohui
 * Date: 2019/8/31
 * Time: 11:02
 */

namespace App\Modules\ModuleShop\Libs\CloudStock;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryShopCartModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Product\ProductClass;
use Illuminate\Support\Collection;
use YZ\Core\Site\Site;

class CloudStockProduct
{
    protected $_siteId = 0;
    protected $_memberId = 0;

    public function __construct($memberId)
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_memberId = $memberId;
    }

    /**
     * 获取云仓中的商品列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @param bool $selectCartExist 是否获取购物车中已有的数量
     * @return array
     */
    public function getProductList($params = [], $page = 1, $pageSize = 15, $selectCartExist = false)
    {
        $query = CloudStockSkuModel::query()->from('tbl_cloudstock_sku as cs')
            ->leftJoin('tbl_product as pro', function ($join) {
                $join->on('pro.id', 'cs.product_id');
                // 下级后，信息需要同步更改
            })
            ->leftJoin('tbl_product_skus as sku', function ($join) {
                $join->on('sku.id', 'cs.sku_id')
                    ->whereRaw('sku.product_id = pro.id');
            })
            ->where('cs.site_id', $this->_siteId)
            ->where('cs.member_id', $this->_memberId)
            ->where('cs.inventory', '>', 0);

        if (isset($params['keyword']) && trim($params['keyword']) != '') {
            $keyword = '%' . trim($params['keyword']) . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('cs.product_name', 'like', $keyword)
                    ->where('pro.name', 'like', $keyword);
            });
        }

        if (isset($params['class_id']) && $params['class_id'] > 0) {
            if (!is_array($params['class_id'])) {
                $params['class_id'] = [$params['class_id']];
            }
            // 如果查询的是父级分类 也需要查询该分类的所有下级分类的产品
            $allClassIds = $params['class_id'];
            ProductClass::getChildClassIds($params['class_id'], $allClassIds);
            $allClassIds = array_unique($allClassIds);
            $query->join('tbl_product_relation_class as rclass', function ($join) use ($allClassIds) {
                $join->on('rclass.product_id', 'cs.product_id')
                    ->whereIn('rclass.class_id', $allClassIds);
            });
        }
        $query->selectRaw('sum(cs.inventory) as total_inventory, count(cs.sku_id) as sku_num')
            ->addSelect([
                'cs.product_name',
                'cs.sku_name',
                'cs.product_image',
                'cs.id',
                'cs.product_id',
                'cs.sku_id',
                'cs.inventory',
                'pro.name as pro_name',
                'pro.small_images as pro_images',
                'sku.sku_name as pro_sku_name'
            ]);
        if ($selectCartExist) {
            $query->leftJoin('tbl_cloudstock_take_delivery_shop_cart as cart', 'cart.cloud_stock_sku_id', 'cs.id')
                ->addSelect('cart.product_quantity');
        }
        $list = $query->groupBy('cs.product_id')
            ->orderBy('cs.created_at', 'Desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->get();
        // 判断是否有下一页
        $hasNextPage = false;
        if ($list->count() > $pageSize) {
            $hasNextPage = true;
            $list->pop(); // 移除多余的一条数据
        }

        if ($params['show_sku_item']) {
            $skus = CloudStockSkuModel::query()->from('tbl_cloudstock_sku as cs')
                ->where('cs.site_id', $this->_siteId)
                ->where('cs.member_id', $this->_memberId)
                ->where('cs.inventory', '>', 0)
                ->get();
            foreach ($skus as &$item) {
                $item['sku_name'] = $item['sku_name'] ? json_decode($item['sku_name'], true) : [];
            }
        }
        foreach ($list as &$item) {
            $item['product_name'] = $item['pro_name'] ?: $item['product_name'];
            $item['product_image'] = $item['pro_images'] ? explode(',', $item['pro_images'])[0] : $item['product_image'];
            if ($params['show_sku_item']) {
                $skus_collection = new Collection();
                foreach ($skus as &$skus_item) {
                    if ($skus_item['product_id'] == $item['product_id'] && count($skus_item['sku_name']) > 0) {
                        $skus_item['product_name'] = $item['product_name'];
                        $skus_item['product_image'] = $item['product_image'];
                        $skus_collection[] = $skus_item;
                    }
                }
            }
            $item['item'] = $skus_collection;
            $item['sku_name'] = $item['pro_sku_name'] ?: $item['sku_name'];
            $item['sku_name'] = $item['sku_name'] ? json_decode($item['sku_name'], true) : [];

            unset($item['pro_sku_name'], $item['pro_name'], $item['pro_images']);
        }
        return [
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'productList' => $list
        ];
    }

    /**
     * 根据商品id 获取云仓中的对应sku列表
     * @param int $productId
     * @param bool $selectCartExist 是否获取购物车中已有的数量
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getProductSkuList($productId, $selectCartExist = false)
    {
        $query = CloudStockSkuModel::query()->from('tbl_cloudstock_sku as sku')
            ->leftJoin('tbl_product as pro', function ($join) {
                $join->on('pro.id', 'sku.product_id');
            })
            ->leftJoin('tbl_product_skus as psku', function ($join) {
                $join->on('psku.id', 'sku.sku_id')
                    ->whereRaw('psku.product_id = pro.id');
            })
            ->where('sku.site_id', $this->_siteId)
            ->where('sku.member_id', $this->_memberId)
            ->where('sku.product_id', $productId)
            ->where('sku.inventory', '>', 0)
            ->select([
                'sku.sku_name',
                'sku.inventory',
                'sku.sku_id',
                'sku.id',
                'psku.sku_name as pro_sku_name'
            ]);
        if ($selectCartExist) {
            $query->leftJoin('tbl_cloudstock_take_delivery_shop_cart as cart', 'cart.cloud_stock_sku_id', 'sku.id')
                ->addSelect('cart.product_quantity');
        }
        $list = $query->get();
        foreach ($list as &$item) {
            $item['sku_name'] = $item['pro_sku_name'] ?: $item['sku_name'];
            $item['sku_name'] = $item['sku_name'] ? json_decode($item['sku_name'], true) : [];
            unset($item['pro_sku_name']);
        }

        return $list;
    }

    /**
     * 获取当前提货单购物车的sku数量
     * @return int
     */
    public function getShoppingCartNum()
    {
        $count = CloudStockTakeDeliveryShopCartModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $this->_memberId)
            ->count();
        return $count;
    }

    public function getCloudStockProductsCount()
    {
        $count = CloudStockSkuModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $this->_memberId)
            ->where('inventory', '>', 0)
            ->selectRaw('sum(inventory) inventory_count, count(DISTINCT product_id) as product_count')
            ->first();
        return $count;
    }
}