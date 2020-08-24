<?php
/**
 * 前端提货逻辑
 * User: liyaohui
 * Date: 2019/8/28
 * Time: 19:43
 */

namespace App\Modules\ModuleShop\Libs\CloudStock;


use App\Modules\ModuleShop\Libs\Model\CloudStockSkuModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryShopCartModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Site\Site;

class TakeDeliveryShoppingCart
{
    protected $_siteId = 0;
    protected $_memberId = 0;

    public function __construct($memberId)
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_memberId = $memberId;
    }

    /**
     * 获取购物车中的商品列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getShoppingCartProductList($params = [], $page = 1, $pageSize = 15)
    {
        // 获取的是失效列表还是正常可提货的列表
        $whereInventory = !!$params['inventory'] ? ['sku.inventory', '>', 0] : ['sku.inventory', '<', 1];
        // 先获取spu
        $productIds = CloudStockTakeDeliveryShopCartModel::query()
            ->from('tbl_cloudstock_take_delivery_shop_cart as cart')
            ->join('tbl_cloudstock_sku as sku', function ($join) use ($whereInventory) {
                $join->on('sku.id', 'cart.cloud_stock_sku_id')
                    ->where(...$whereInventory);

            })
            ->where('cart.site_id', $this->_siteId)
            ->where('cart.member_id', $this->_memberId)
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->selectRaw('distinct cart.product_id')
            ->pluck('cart.product_id')->all();
        $skuList = [];
        $hasNextPage = false;
        if ($productIds) {
            if (count($productIds) > $pageSize) {
                $hasNextPage = true;
                array_pop($productIds); // 移除多余的一条数据
            }

            // 获取具体sku
            $skuList = CloudStockTakeDeliveryShopCartModel::query()
                ->from('tbl_cloudstock_take_delivery_shop_cart as cart')
                ->leftJoin('tbl_cloudstock_sku as sku', 'sku.id', 'cart.cloud_stock_sku_id')
                ->where('cart.site_id', $this->_siteId)
                ->where('cart.member_id', $this->_memberId)
                ->where(...$whereInventory)
                ->whereIn('cart.product_id', $productIds)
                ->orderByDesc('cart.created_at')
                ->select([
                    'cart.id',
                    'cart.product_id',
                    'cart.product_skus_id',
                    'cart.product_name',
                    'cart.product_skus_name',
                    'cart.product_image',
                    'cart.product_quantity',
                    'cart.cloud_stock_sku_id',
                    'sku.inventory'
                ]);
            // 正常库存商品要去查找平台商品信息
            if ($params['inventory']) {
                $skuList->leftJoin('tbl_product as pro', function ($join) {
                    $join->on('pro.id', 'cart.product_id')
                        ->where('pro.status', Constants::Product_Status_Sell);
                })
                    ->leftJoin('tbl_product_skus as psku', function ($join) {
                        $join->on('psku.id', 'sku.sku_id')
                            ->whereRaw('psku.product_id = pro.id');
                    })
                    ->addSelect([
                        'pro.name as pro_name',
                        'psku.sku_name as pro_sku_name',
                        'pro.small_images as pro_images'
                    ]);
            }
            $skuList = $skuList->get();
        }
        return [
            'has_next_page' => $hasNextPage,
            'page_size' => intval($pageSize),
            'current' => $page,
            'productList' => $this->formatCartProductList($skuList)
        ];
    }

    /**
     * 获取选中的购物车商品 给生成订单时用
     * @param array $ids
     * @param bool $format 是否需要格式化分组输出
     * @return array|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getSelectProductList(array $ids, $format = true)
    {
        $skuList = CloudStockTakeDeliveryShopCartModel::query()
            ->from('tbl_cloudstock_take_delivery_shop_cart as cart')
            ->leftJoin('tbl_product as pro', function ($join) {
                $join->on('pro.id', 'cart.product_id')
                    ->where('pro.status', Constants::Product_Status_Sell);
            })
            ->leftJoin('tbl_product_skus as sku', function ($join) {
                $join->on('sku.id', 'cart.product_skus_id')
                    ->whereRaw('sku.product_id = pro.id');
            })
            ->where('cart.site_id', $this->_siteId)
            ->where('cart.member_id', $this->_memberId)
            ->whereIn('cart.id', $ids)
            ->orderByDesc('cart.created_at')
            ->select([
                'cart.id',
                'cart.product_id',
                'cart.product_skus_id',
                'cart.product_name',
                'cart.product_skus_name',
                'cart.product_image',
                'cart.product_quantity',
                'cart.cloud_stock_sku_id',
                'cart.weight as cart_weight',
                'pro.name as pro_name',
                'pro.type as product_type',
                'sku.sku_name as pro_sku_name',
                'pro.small_images as pro_images',
                'sku.sku_image',
                'sku.weight as sku_weight'
            ])
            ->get();
        return $format ? $this->formatCartProductList($skuList) : $skuList;
    }

    /**
     * 格式化购物车商品列表 主要是分组
     * @param $list
     * @return array
     */
    public function formatCartProductList($list)
    {
        if (!$list) return [];
        $returnList = [];
        foreach ($list as $item) {
            // 用product_id 分组
            // 初始化分组信息
            if (!isset($returnList['product-' . $item->product_id])) {
                $returnList['product-' . $item->product_id] = [];
                $item->product_image = $item->pro_images ? explode(',', $item->pro_images)[0] : $item->product_image;
                // 商品spu信息
                $returnList['product-' . $item->product_id]['info'] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->pro_name ?: $item->product_name,
                    'product_image' => $item->product_image,
                    'product_type' => $item->product_type
                ];
                $returnList['product-' . $item->product_id]['list'] = [];
            }
            $item->product_skus_name = $item->pro_sku_name ?: $item->product_skus_name;
            $item->product_skus_name = $item->product_skus_name ? json_decode($item->product_skus_name, true) : [];
            // 具体sku信息
            $returnList['product-' . $item->product_id]['list'][] = [
                'product_skus_name' => $item->product_skus_name,
                'product_quantity' => $item->product_quantity,
                'product_skus_id' => $item->product_skus_id,
                'id' => $item->id,
                'cloud_stock_sku_id' => $item->cloud_stock_sku_id,
                'inventory' => $item->inventory,
                'product_type' => $item->product_type
            ];
        }
        return $returnList;
    }

    /**
     * 添加商品到购物车
     * @param array $items [['id'=>1, 'num' => 1]] id为CloudStockSkuModel 的id
     * @return array|bool
     * @throws \Exception
     */
    public function addToCart($items)
    {
        if (!$items) {
            return makeServiceResult(500, trans('shop-front.shop.add_product_to_cart_fail'));
        }

        try {
            DB::beginTransaction();
            // 检测库存是否足够
            $checkInventory = $this->checkInventory($items);
            if (!$checkInventory['data']['all_enough']) {
                DB::commit();
                return makeServiceResult(402, trans('shop-front.shop.inventory_not_enough'), $checkInventory['data']);
            }
            $itemsObj = collect($items)->pluck('num', 'id');
            // 库存足够 添加到购物车
            // 检测购物车是否已有该商品 如果有则更新数量
            $checkCart = CloudStockTakeDeliveryShopCartModel::query()
                ->where('site_id', $this->_siteId)
                ->where('member_id', $this->_memberId)
                ->whereIn('cloud_stock_sku_id', array_keys($itemsObj->all()))
                ->select('cloud_stock_sku_id', 'id', 'product_quantity')
                ->get();
            $addToCartData = [];    // 新增的商品
            $updateCartData = [];   // 更新的商品
            $deleteIds = []; // 数量为0的要删掉
            $now = Carbon::now();
            foreach ($checkInventory['list'] as $item) {
                $cartHas = $checkCart->where('cloud_stock_sku_id', $item->id)->first();
                // 如果购物车已存在该商品 则更新数量
                if ($cartHas) {
                    if ($itemsObj[$item->id] > 0) {
                        $updateCartData[] = [
                            'id' => $cartHas->id,
                            'product_quantity' => $itemsObj[$item->id],
                            'updated_at' => $now
                        ];
                    } else {
                        // 如果数量为0 则从购物车删除
                        $deleteIds[] = $cartHas->id;
                    }
                } else {
                    if ($itemsObj[$item->id] > 0) {
                        $addToCartData[] = [
                            'site_id' => $this->_siteId,
                            'member_id' => $this->_memberId,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'product_skus_id' => $item->sku_id,
                            'product_skus_name' => $item->sku_name,
                            'product_image' => $item->product_image,
                            'product_quantity' => $itemsObj[$item->id],
                            'cloud_stock_sku_id' => $item->id,
                            'updated_at' => $now,
                            'created_at' => $now,
                            'weight' => $item->weight
                        ];
                    }
                }
            }
            $insert = $save = true;
            // 批量增加
            if ($addToCartData) {
                $insert = CloudStockTakeDeliveryShopCartModel::query()->insert($addToCartData);
            }
            // 批量更新
            if ($updateCartData) {
                $save = (new CloudStockTakeDeliveryShopCartModel())->updateBatch($updateCartData, 'id');
            }
            // 删掉为0的
            if ($deleteIds) {
                $this->remove($deleteIds);
            }
            DB::commit();
            if ($insert && $save) {
                return makeServiceResult(200, 'ok');
            } else {
                return makeServiceResult(500, trans('shop-front.shop.add_product_to_cart_fail'));
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 购物车增加商品数量
     * @param int $id 购物车中商品id
     * @param int $incrementNum 要增加的数量
     * @return array|int
     * @throws \Exception
     */
    public function increment($id, $incrementNum = 1)
    {
        try {
            DB::beginTransaction();
            $cartItem = CloudStockTakeDeliveryShopCartModel::find($id);
            if (!$cartItem) {
                throw new \Exception(trans('shop-front.shop.data_error'));
            } else {
                // 检测库存
                $item[] = [
                    'id' => $cartItem->cloud_stock_sku_id,
                    'num' => $cartItem->product_quantity + $incrementNum
                ];
                $checkInventory = $this->checkInventory($item, false);
                if ($checkInventory['all_enough']) {
                    $save = $cartItem->increment('product_quantity', $incrementNum);
                } else {
                    $save = makeServiceResult(402, trans('shop-front.shop.inventory_not_enough'), $checkInventory['no_enough_list'][0]);
                }
                DB::commit();
                return $save;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 购物车减少库存
     * @param int $id 购物车id
     * @param int $decrementNum 要减少的数量
     * @return array|int
     * @throws \Exception
     */
    public function decrement($id, $decrementNum = 1)
    {
        try {
            DB::beginTransaction();
            // 减少的时候也需要检测库存
            $cartItem = CloudStockTakeDeliveryShopCartModel::find($id);
            if (!$cartItem) {
                throw new \Exception(trans('shop-front.shop.data_error'));
            } else {
                if ($cartItem->product_quantity - $decrementNum < 1) {
                    throw new \Exception(trans('shop-front.shop.product_number_more_than_0'));
                }
                // 检测库存
                $item[] = [
                    'id' => $cartItem->cloud_stock_sku_id,
                    'num' => $cartItem->product_quantity - $decrementNum
                ];
                $checkInventory = $this->checkInventory($item, false);
                if ($checkInventory['all_enough']) {
                    $save = $cartItem->decrement('product_quantity', $decrementNum);
                } else {
                    $save = makeServiceResult(402, trans('shop-front.shop.inventory_not_enough'), $checkInventory['no_enough_list'][0]);
                }
                DB::commit();
                return $save;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 删除购物车商品
     * @param array $items 购物车记录id数组
     * @return mixed
     */
    public function remove($items)
    {
        if ($items) {
            $items = is_numeric($items) ? [$items] : $items;
            return CloudStockTakeDeliveryShopCartModel::query()
                ->where('member_id', $this->_memberId)
                ->whereIn('id', $items)
                ->delete();
        }
        return false;
    }


    /**
     * @param array $items 要检测的云仓商品 [['id' => 1, 'num' => 1]] id为CloudStockSkuModel的id
     * @param bool $returnOriginalData 是否返回原始数据
     * @return array
     * [
     *  'data' => [ // 检测后的数据
     *  'all_enough' => true,   // 是否全部够库存
     * 'part_enough' => false, // 是否至少有一件够库存
     * 'no_enough_list' => [], // 库存不够的列表
     * 'enough_list' => []] // 库存够的列表
     * 'list' => [] // 原始数据
     * ]
     * | ['all_enough' => true,   // 是否全部够库存
     * 'part_enough' => false, // 是否至少有一件够库存
     * 'no_enough_list' => [], // 库存不够的列表
     * 'enough_list' => []] // 库存够的列表]
     * @throws \Exception
     */
    public function checkInventory($items, $returnOriginalData = true)
    {
        $ids = [];
        $needNum = [];
        foreach ($items as $item) {
            $ids[] = $item['id'];
            $needNum['item-' . $item['id']] = $item['num'];
        }
        // 查找所有云仓中的商品
        $productList = CloudStockSkuModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $this->_memberId)
            ->whereIn('id', $ids)
            ->get();
        // 如果要添加的数量和查找出来的数量不相等 则认为出错
        if ($productList->count() != count($ids)) {
            throw new \Exception(trans('shop-front.shop.data_error'));
        }

        $checkData = [
            'all_enough' => true,
            'part_enough' => false,
            'no_enough_list' => [],
            'enough_list' => []
        ];
        // 循环判断库存是否足够
        foreach ($productList as $pro) {
            $num = $needNum['item-' . $pro->id];
            if ($needNum['item-' . $pro->id] > $pro->inventory) {
                $checkData['all_enough'] = false;
                $checkData['no_enough_list'][] = [
                    'id' => $pro->id,
                    'inventory' => $pro->inventory,
                    'need' => $num
                ];
            } else {
                $checkData['part_enough'] = true;
                $checkData['enough_list'][] = [
                    'id' => $pro->id,
                    'inventory' => $pro->inventory,
                    'need' => $num
                ];
            }
        }
        return $returnOriginalData ? ['list' => $productList, 'data' => $checkData] : $checkData;
    }
}