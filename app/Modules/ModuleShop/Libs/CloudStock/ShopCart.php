<?php

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderItemModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkuValueModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockShopCartModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use YZ\Core\Constants;
use YZ\Core\Member\Auth;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Product\Product;

/**
 * 云仓进货购物车类
 * Class ShopOrder
 * @package App\Modules\ModuleShop\Libs\CloudStock
 */
class ShopCart
{
    public $memberId = null;
    public $_site = null;
    protected $_member = null;
    protected $_cloudStock = null;

    public function __construct($memberId = null)
    {
        if (!$memberId) {
            $this->memberId = Auth::hasLogin();
        } else {
            $this->memberId = $memberId;
        }
        if (!$this->memberId) {
            throw new \Exception(trans('shop-front.member.login_need'));
        }
        $this->_site = Site::getCurrentSite();
        $this->_member = new Member($this->memberId);
    }

    /**
     * 添加批量商品到购物车
     * @param array $productInfos 要添加到购物车里的商品信息，格式如 [
     *  ['product_id' => 1,'sku_id' => 2, 'num' => 22,'action' => add|set]
     *  ['product_id' => 3,'sku_id' => 4, 'num' => 33,'action' => add|set]
     * ]
     * @return array|mixed
     */
    public function setSkus(array $skusInfo)
    {
        $coll = new Collection($skusInfo);
        $productIds = array_unique($coll->pluck('product_id')->all());
        $skuIds = $coll->pluck('sku_id')->all();
        $cartProducts = CloudStockShopCartModel::query()
            ->where('member_id', $this->memberId)
            ->whereIn('product_id', $productIds)
            ->whereIn('product_skus_id', $skuIds)
            ->get();
        $products = ProductModel::query()->where('site_id', $this->_site->getSiteId())->whereIn('id', $productIds)->get();
        $skus = ProductSkusModel::query()->where('site_id', $this->_site->getSiteId())->whereIn('id', $skuIds)->get();
        DB::beginTransaction();
        try {
            $data = [];
            foreach ($skusInfo as $item) {
                $quantity = $item['num'];
                $cartProduct = $cartProducts->where('product_id', $item['product_id'])->where('product_skus_id', $item['sku_id'])->first();
                $productModel = $products->where('id', $item['product_id'])->first();
                $skuModel = $skus->where('id', $item['sku_id'])->first();
                $pro = new ShopProduct($this->_member, $productModel, $skuModel, $item['num']);
//                $originalProductImage = '';
                if (empty($cartProduct) && $item['num'] > 0) {
                    $cartProduct = new CloudStockShopCartModel();
                } else {
                    // 如果商品数量为0，表示此商品需要删除
                    if ($cartProduct && $cartProduct->product_quantity > 0 && intval($item['num']) < 1) {
                        $this->removeSkuWithModel($cartProduct);
                        $data[] = $item;
                        continue;
                    }
                    // 如果购物车没有记录 并且num为0 不去处理
                    if (!$cartProduct && intval($item['num']) < 1) {
                        continue;
                    }
                    // 数量相加
                    if ($item['action'] == 'add') $quantity = $cartProduct->product_quantity + $pro->num;
                    else $quantity = $pro->num;
                    // 更新创建时间
                    $cartProduct->created_at = Carbon::now();
//                    $originalProductImage = $cartProduct->product_image;
                }
                $skus = $pro->getThisProductSkuModel();
                // 检测是否可以购买
                $canBuy = $pro->canBuy();
                if ($canBuy['code'] != 200) {
                    return $canBuy;
                }
                // 更新快照
                $product = $pro->getThisProductModel();
                $cartProduct->member_id = $this->memberId;
                $cartProduct->product_id = $pro->productId;
                $cartProduct->site_id = $this->_site->getSiteId();
                $cartProduct->product_type = $product->type;
                $cartProduct->product_name = $product->name;
                $cartProduct->product_skus_id = $pro->skuId;
                $cartProduct->product_price = $skus->price;
                $cartProduct->product_quantity = $quantity;
                $cartProduct->product_skus_name = $skus->sku_name;
                // 获取第一张小图
                $smallImage = explode(',', $product->small_images)[0];
                /*
                $sitePath = Site::getSiteComdataDir($this->_site->getSiteId(), true);
                // 先删除旧的图片
                if ($originalProductImage) {
                    $path = $sitePath . $originalProductImage;
                    File::delete($path);
                }
                // 要保存的图片名称
                $saveImageName = time() . str_random(5) . strrchr($smallImage, '.');
                // 图片保存路径
                $saveImagePath = "/cloudstockcart/image/";
                if (!is_dir($sitePath . $saveImagePath)) {
                    File::makeDirectory($sitePath . $saveImagePath, 0777, true);
                }
                File::copy($sitePath . $smallImage, $sitePath . $saveImagePath . $saveImageName);
                $cartProduct->product_image = $saveImagePath . $saveImageName;
                */
                $cartProduct->product_image = $smallImage;
                $save = $cartProduct->save();
                if (!$save) {
                    throw new \Exception(trans('shop-front.shop.add_product_to_cart_fail'));
                }
                $item['num'] = $cartProduct->product_quantity;
                $data[] = $item;
            }
            DB::commit();
            return makeServiceResult(200, 'ok', $data);
        } catch (\Exception $ex) {
            DB::rollBack();
            return makeServiceResult(400, $ex->getMessage());
        }
    }

    /**
     * 删除购物车中的商品
     * @param CloudStockShopCartModel $model
     * @return bool
     * @throws \Exception
     */
    public function removeSkuWithModel(CloudStockShopCartModel $model)
    {
        $images = $model->product_image;
        // 删除记录
        $delete = $model->delete();
        if (!$delete) {
            return false;
        }
        // 删除图片
        //$sitePath = Site::getSiteComdataDir($this->_site->getSiteId(), true);
        //File::delete($sitePath . $images);

        return true;
    }

    /**
     * @param array $skuInfos , 格式如 [
     *  ['product_id' => 1,'sku_id' => 2]
     *  ['product_id' => 3,'sku_id' => 4]
     * ]
     * @throws \Exception
     */
    public function removeSku(array $skuInfos)
    {
        $where = '';
        foreach ($skuInfos as $sku) {
            $where .= "(product_id = '" . intval($sku['product_id']) . "' and product_skus_id = '" . intval($sku['sku_id']) . "') OR ";
        }
        if ($where) $where = substr($where, 0, -4);
        $cartList = CloudStockShopCartModel::query()->whereRaw($where)->get();
        if ($cartList) {
            foreach ($cartList as $item) {
                $this->removeSkuWithModel($item);
            }
        }
    }

    /**
     * 以商品ID来删除购物车记录（主要是用于批量删除多规格商品）
     * @param array|string $productId
     * @throws \Exception
     */
    public function removeByProductId($productId)
    {
        if (!is_array($productId)) $productId = [$productId];
        $cartList = CloudStockShopCartModel::query()->whereIn('product_id', $productId)->get();
        foreach ($cartList as $item) {
            $this->removeSkuWithModel($item);
        }
    }

    /**
     * 刷新购物车 查找出来失效的产品
     * @return \Illuminate\Database\Eloquent\Collection|mixed|static[]
     */
    public function refresh()
    {
        // 失效的产品
        // 多检测规格删除的情况
        $invalidProduct = CloudStockShopCartModel::query()
            ->where('member_id', $this->memberId)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_cloudstock_shop_cart.product_id', '=', 'tbl_product.id');
            })
            ->leftJoin('tbl_product_skus as sku', 'sku.id', 'tbl_cloudstock_shop_cart.product_skus_id')
            ->whereRaw('(tbl_product.status <> ? OR sku.id IS NULL)', [Constants::Product_Status_Sell])
            ->select(['tbl_cloudstock_shop_cart.*'])
            ->get();
        if ($invalidProduct) {
            $invalidProduct = $invalidProduct->toArray();
        }
        // 检测限购的商品 只查找未删除的
        /* 经和产品商量，前期先不限购
        $invalidProduct2 = CloudStockShopCartModel::query()
            ->where('member_id', $this->memberId)
            ->where('tbl_product.status', '!=', Constants::Product_Status_Delete)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_cloudstock_shop_cart.product_id', '=', 'tbl_product.id');
            })->leftJoin('tbl_product_skus', function ($join) {
                $join->on('tbl_cloudstock_shop_cart.product_skus_id', '=', 'tbl_product_skus.id');
            })->select(['tbl_cloudstock_shop_cart.*', 'tbl_product.id as proid'])->get();
        if ($invalidProduct2) {
            //检测购买权限
            foreach ($invalidProduct2 as $product) {
                $obj = new Product($product->proid);
                $checkBuyPerm = $obj->checkBuyPerm();
                if ($checkBuyPerm == 0) {
                    $invalidProduct[] = $product->toArray();
                }
            }
        }*/
        return $invalidProduct;
    }

    /**
     * 返回购物车中的 商品种数，规格种数和商品的购买数量
     * @param int $refresh 是否刷新无效商品
     * @param int $detail 是否返回详细信息，不返回详细时，只返回商品总数，否则按返回数组
     * @return int|array ['product_num' => 1,'sku_num' => 2, 'all' => 123]
     */
    public function getShoppingCartNum($refresh = 0, $detail = 0)
    {
        $invalidIds = [];
        if ($refresh) {
            $invalidProduct = $this->refresh();
            if ($invalidProduct) {
                foreach ($invalidProduct as $p) {
                    $invalidIds[] = $p['product_skus_id'];
                }
            }
        }
        $num = CloudStockShopCartModel::query()
            ->where('member_id', $this->memberId)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_cloudstock_shop_cart.product_id', '=', 'tbl_product.id');
            })
            ->leftJoin('tbl_product_skus', function ($join) {
                $join->on('tbl_cloudstock_shop_cart.product_skus_id', '=', 'tbl_product_skus.id');
            })
            ->whereRaw('(tbl_product.status = ? and tbl_product_skus.id is not null)', [Constants::Product_Status_Sell]);
        if (count($invalidIds)) $num->whereNotIn('tbl_cloudstock_shop_cart.product_skus_id', $invalidIds);
        if (!$detail) return $num->sum('product_quantity');
        $list = $num->get();
        $productIds = [];
        $skuIds = [];
        $all = 0;
        foreach ($list as $item) {
            $all += $item->product_quantity;
            $productIds[$item->product_id] = 1;
            $skuIds[$item->product_skus_id] = 1;
        }
        return [
            'product_num' => count($productIds), //商品种数
            'sku_num' => count($skuIds), //规格种数
            'all' => $all //总购买数量
        ];
    }

    /**
     * 返回购物车内的产品列表
     * @param int $page
     * @param int $pageSize
     * @param int $refresh 是否同步刷新（刷新会检测失效的商品等）
     * @return array
     */
    public function getProductList($page = 1, $pageSize = 15, $refresh = 0)
    {
        $invalidIds = [];
        if ($refresh) {
            $invalidProduct = $this->refresh();
            if ($invalidProduct) {
                foreach ($invalidProduct as &$p) {
                    $p['product_skus_name'] = $p['product_skus_name'] ? json_decode($p['product_skus_name'], true) : [];
                    $invalidIds[] = $p['product_skus_id'];
                }
            }
        }
        //因为要分组显示，要分三步来处理，第一步，先找出所有的 SPU
        $query = CloudStockShopCartModel::query()->where('member_id', $this->memberId)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_cloudstock_shop_cart.product_id', '=', 'tbl_product.id');
            })->whereRaw('(tbl_product.status = ?)', [Constants::Product_Status_Sell])
            ->select(['tbl_cloudstock_shop_cart.product_id'])
            ->orderBy('tbl_cloudstock_shop_cart.created_at', 'desc');
        if (count($invalidIds)) $query->whereNotIn('tbl_cloudstock_shop_cart.product_skus_id', $invalidIds);
        $productList = $query->get();
        $productIds = array_unique($productList->pluck('product_id')->all());

        //第二步，获取相应的SPU记录
        if (count($productIds)) {
            $productList = ProductModel::query()->whereIn('id', $productIds);
            $productList->select(['id', 'name', 'small_images']);
            $productListCount = $productList->count();
            $productList->orderByRaw("FIELD(id," . implode(",", $productIds) . ")");
            $productList = $productList->offset(($page - 1) * $pageSize)
                ->limit($pageSize + 1)//多加一条的目的是为了判断有没有下一页，如果查询结果的数目比$pageSize大，说明有下一页
                ->get();
            // 是否有下一页
            if ($hasNextPage = $productList->count() > $pageSize) {
                // 最后一个去掉
                $productList->pop();
                $page += 1;
            } else {
                $page = 0;//没有下一页置为0
            }
        }

        //第三步，获取相应的SKU记录并进行组合
        if ($productIds) {
            $query = CloudStockShopCartModel::query()
                ->where('tbl_cloudstock_shop_cart.member_id', $this->memberId)->whereIn('tbl_cloudstock_shop_cart.product_id', $productIds)
                ->join('tbl_product_skus', 'tbl_cloudstock_shop_cart.product_skus_id', '=', 'tbl_product_skus.id')
                ->select([
                    'tbl_cloudstock_shop_cart.id',
                    'tbl_cloudstock_shop_cart.product_id',
                    'tbl_cloudstock_shop_cart.product_skus_id',
                    'tbl_cloudstock_shop_cart.product_quantity',
                    'tbl_cloudstock_shop_cart.updated_at',
                    'tbl_product_skus.sku_name',
                    'tbl_product_skus.price',
                    'tbl_product_skus.inventory',
                    'tbl_product_skus.cloud_stock_rule'
                ]);
            if (count($invalidIds)) $query->whereNotIn('tbl_cloudstock_shop_cart.product_skus_id', $invalidIds);
            $skuList = $query->get();
            $goodsNum = $query->count();
            $cartIds = [];
            foreach ($skuList as &$item) {
                $cartIds[] = $item->id;
                // 计算产品的会员价
                $price = $this->getCloudStock()->getProductPrice(
                    $item->price,
                    $this->_member->getModel()->dealer_level,
                    $this->_member->getModel()->dealer_hide_level,
                    $item->cloud_stock_rule
                );
                $item->price = moneyCent2Yuan($price);
                // sku name做下处理
                $item['sku_name'] = $item['sku_name'] ? json_decode($item['sku_name'], true) : [];
            }
            unset($item);
            // 更新时间
            CloudStockShopCartModel::query()->whereIn('id', $cartIds)->update(['updated_at' => Carbon::now()]);
            // 合并商品与SKU列表
            foreach ($productList as &$item) {
                $skus = $skuList->where('product_id', $item->id)->values()->all();
                $item->skus = $skus;
                // 取出来第一张图片
                $image = explode(',', $item->small_images);
                $item->small_images = $image[0];
            }
            unset($item);
        }
        return [
            'total' => intval($productListCount),
            'total_goods_num' => intval($goodsNum),
            'page_size' => intval($pageSize),
            'page' => $page,
            'productList' => $productList ? $productList : [],
            'invalidList' => $invalidProduct
        ];
    }

    /**
     * 计算购物车内所选产品的总价
     * @param array $product 选择的产品数据 格式为['product_id' => 222, 'sku_id'=>22,'num'=>1]
     * @return int|string 返回钱数
     */
    public function calCartMoney($product)
    {
        if ($product) {
            $money = 0;
            $collection = new Collection($product);
            $skuIds = $collection->pluck('sku_id')->all();
            $list = ProductSkusModel::query()->whereIn('id', $skuIds)->get();
            foreach ($list as $sku) {
                $price = $this->getCloudStock()->getProductPrice(
                    $sku->price,
                    $this->_member->getModel()->dealer_level,
                    $this->_member->getModel()->dealer_hide_level,
                    $sku->cloud_stock_rule
                );
                if (!$sku['num']) $num = $collection->where('product_id', $sku->product_id)->where('sku_id', $sku->id)->first()['num'];
                $money = bcadd($money, bcmul($price, $num));
            }
            return moneyCent2Yuan($money);
        } else {
            return 0;
        }
    }

    private function getCloudStock()
    {
        if (!$this->_cloudStock) $this->_cloudStock = new CloudStock($this->_member->getModel()->id, false);
        return $this->_cloudStock;
    }

    // 一键补货
    public function onceReplenish($orderId)
    {
        // 云仓购物车列表
        $shopcart = CloudStockShopCartModel::query()
            ->where('member_id', $this->memberId)
            ->where('site_id', $this->_site->getSiteId())
            ->get();

        $order = new FrontPurchaseOrder(true);
        $cloudStockOrMemberId = $order->getMemberCloudStockId($this->memberId);
        //
        $subOrderInfo = $order->getOrderInfo($cloudStockOrMemberId, $orderId, $this->memberId);
        $subOrderInfoProduct = $subOrderInfo['items'];
        $shopcartProduct = [];
        foreach ($shopcart as $scitem) {
            $shopcartProduct[$scitem->product_skus_id] = $scitem->product_quantity;
        }

        $addProduct = [];
        foreach ($subOrderInfoProduct as $item) {
            // $replenishNum = intval($shopcartProduct[$item->sku_id]) + $item->not_enough_num;
            if ($item->not_enough_num < 0) {
                $action = $shopcartProduct[$item->sku_id] ? 'set' : 'add';
                $addProduct[] = ['product_id' => $item->product_id, 'sku_id' => $item->sku_id, 'num' => abs($item->not_enough_num), 'action' => $action];
            }
        }
        $this->setSkus($addProduct);
    }
}