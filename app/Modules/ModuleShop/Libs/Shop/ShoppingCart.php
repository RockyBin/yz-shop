<?php
/**
 * 商城购物车业务类
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\ProductSkuValueModel;
use App\Modules\ModuleShop\Libs\Model\ShoppingCartModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use YZ\Core\Constants;
use YZ\Core\Member\Auth;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Product\Product;

class ShoppingCart implements IShopCart
{
    public $memberId = null;
    public $_site = null;

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
    }

    /**
     * 添加产品到购物车
     * @param IShopProduct $pro
     * @return array|mixed
     */
    public function addProduct(IShopProduct $pro)
    {
        // 检测产品是否已经添加过
        $cartProduct = ShoppingCartModel::query()
            ->where('member_id', $this->memberId)
            ->where('product_id', $pro->productId)
            ->where('product_skus_id', $pro->skuId)
            ->first();
        $quantity = $pro->num;
        $originalProductImage = '';
        if (empty($cartProduct)) {
            $cartProduct = new ShoppingCartModel();
        } else {
            // 数量相加
            $quantity = $cartProduct->product_quantity + $pro->num;
            // 更新创建时间
            $cartProduct->created_at = Carbon::now();
            $originalProductImage = $cartProduct->product_image;
        }
        // 检测是否可以购买
        //$quantity 1.如果这件物品在购物车没有的时候，即将要购买的数量，2.购物车的数量+即将要买的数量
        $pro->num = $quantity;
        $canBuy = $pro->canBuy($this->memberId, $quantity);
        $skus = $pro->getThisProductSkuModel();
        $onlyAgainAddProduct = intval($skus->inventory) - intval($cartProduct->product_quantity);//还能再购买多少件（需除去购物车数）=库存数量-购物车数量
        if ($canBuy['data']['noperm'] == 1) {
            throw new \Exception($canBuy['msg']);
        } elseif ($canBuy['code'] == 413) {
            return makeServiceResult(413, trans('shop-front.shop.inventory_not_enough') . ',' . trans('shop-front.shop.only_again_add_product') . $onlyAgainAddProduct . trans('shop-front.shop.item'));
        } elseif ($canBuy['code'] != 200) {
            return $canBuy;
        }
        $member = (new Member($this->memberId))->getInfo();
        // 更新快照
        $product = $pro->getThisProductModel();
        $cartProduct->member_id = $this->memberId;
        $cartProduct->product_id = $pro->productId;
        $cartProduct->site_id = $this->_site->getSiteId();
        $cartProduct->product_type = $product->type;
        $cartProduct->product_name = $product->name;
        $cartProduct->product_skus_id = $pro->skuId;
        $cartProduct->product_price = $skus->price;
        $cartProduct->product_member_price = $pro->getMemberPrice($member->level);
        $cartProduct->product_quantity = $quantity;

        // 获取第一张小图
        $smallImage = $product->small_images;
        // 要保存的图片
        $smallImage = explode(',', $smallImage)[0];
        // sku name
        if ($skus->sku_code != '0') {
            $values = explode(',', trim($skus->sku_code, ','));
            $skuInfo = ProductSkuValueModel::query()->whereIn('id', $values)->select(['value', 'small_image'])->get();
            $skuName = $skuInfo->implode('value', ' '); // sku的名称
            $skuImage = $skuInfo->pluck('small_image')->filter(); // 获取sku图片
            $smallImage = $skuImage->count() > 0 ? implode('', $skuImage->all()) : $smallImage;
            $cartProduct->product_skus_name = $skuName;
        } else {
            $cartProduct->product_skus_name = '';
        }
        $sitePath = Site::getSiteComdataDir($this->_site->getSiteId(), true);
        // 先删除旧的图片
        if ($originalProductImage) {
            $path = $sitePath . $originalProductImage;
            File::delete($path);
        }

        // 要保存的图片名称
        $saveImageName = time() . str_random(5) . strrchr($smallImage, '.');
        // 图片保存路径
        $saveImagePath = "/cart/image/";
        if (!is_dir($sitePath . $saveImagePath)) {
            File::makeDirectory($sitePath . $saveImagePath, 0777, true);
        }
        File::copy($sitePath . $smallImage, $sitePath . $saveImagePath . $saveImageName);
        $cartProduct->product_image = $saveImagePath . $saveImageName;
        $save = $cartProduct->save();
        if ($save) {
            return makeServiceResult(200, 'ok');
        } else {
            return makeServiceResult(400, trans('shop-front.shop.add_product_to_cart_fail'));
        }
    }

    /**
     * 删除购物车中的商品
     * @param int|array 商品ID（数据库中的主键） $productId
     * @return bool|mixed
     */
    public function removeProduct($productId)
    {
        $query = ShoppingCartModel::query()->where('member_id', $this->memberId);
        if (is_array($productId)) {
            $query->whereIn('id', $productId);
        } else {
            $query->where('id', $productId);
        }
        $images = $query->pluck('product_image');
        // 删除记录
        $delete = $query->delete();
        if (!$delete) {
            return false;
        }
        // 删除图片
        $sitePath = Site::getSiteComdataDir($this->_site->getSiteId(), true);
        $images->each(function ($img) use ($sitePath) {
            File::delete($sitePath . $img);
        });
        return true;
    }

    /**
     * 增加购物车中的产品数量
     * @param int 商品ID（数据库中的主键） $productId
     * @param int $num
     * @return int|mixed
     * @throws \Exception
     */
    public function increaseProductNum($productId, int $num)
    {
        $cartProduct = ShoppingCartModel::find($productId);
        if ($cartProduct) {
            $productNum = $num;
            $product = ShopProductFactory::createShopProduct(
                $cartProduct->product_id,
                $cartProduct->product_skus_id,
                $productNum
            );
            // 检测产品是否可以购买
            $canBuy = $product->canbuy($this->memberId, $productNum);
            if ($canBuy['code'] == 200) {
                $cartProduct->product_quantity = $productNum;
                $cartProduct->save();
                return makeServiceResult(200, 'ok');
            } elseif ($canBuy['data']['min']) {
                $cartProduct->product_quantity = $canBuy['data']['min'];
                $cartProduct->save();
                return $canBuy;
            } elseif ($canBuy['data']['max']) {
                $cartProduct->product_quantity = $canBuy['data']['max'];
                $cartProduct->save();
                return $canBuy;
            } else {
                return $canBuy;
            }
        } else {
            throw new \Exception(trans('shop-front.shop.product_not_exist_cart'));
        }
    }

    /**
     * 减少购物车内产品数量
     * @param int $productId 商品ID（数据库中的主键）
     * @param int $num
     * @return array|mixed
     * @throws \Exception
     */
    public function decreaseProductNum($productId, int $num)
    {
        $cartProduct = ShoppingCartModel::find($productId);
        if ($cartProduct) {
            $productNum = $num;
            // 减少后的数量必须大于0
            if ($productNum <= 0) {
                throw new \Exception(trans('shop-front.shop.product_number_more_than_0'));
            }
            $product = ShopProductFactory::createShopProduct(
                $cartProduct->product_id,
                $cartProduct->product_skus_id,
                $productNum
            );
            // 检测产品是否可以购买
            $canBuy = $product->canbuy($this->memberId, $productNum);
            if ($canBuy['code'] == 200) {
                $cartProduct->product_quantity = $productNum;
                $cartProduct->save();
                return makeServiceResult(200, 'ok');
            }elseif ($canBuy['data']['min']) {
                $cartProduct->product_quantity = $canBuy['data']['min'];
                $cartProduct->save();
                return $canBuy;
            } elseif ($canBuy['data']['max']) {
                $cartProduct->product_quantity = $canBuy['data']['max'];
                $cartProduct->save();
                return $canBuy;
            }  else {
                return $canBuy;
            }
        } else {
            throw new \Exception(trans('shop-front.shop.product_not_exist_cart'));
        }
    }

    /**
     * 刷新购物车 查找出来失效的产品
     * @return \Illuminate\Database\Eloquent\Collection|mixed|static[]
     */
    public function refresh()
    {
        // 失效的产品
        $invalidProduct = ShoppingCartModel::query()
            ->where('member_id', $this->memberId)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_shopping_cart.product_id', '=', 'tbl_product.id');
            })
            ->leftJoin('tbl_product_skus', function ($join) {
                $join->on('tbl_shopping_cart.product_skus_id', '=', 'tbl_product_skus.id');
            })
            ->whereRaw('(tbl_product.status <> ? OR tbl_product_skus.inventory <= ? OR  tbl_product_skus.inventory IS NULL)', [Constants::Product_Status_Sell, 0])
            ->select(['tbl_shopping_cart.*'])
            ->get();
        if ($invalidProduct) {
            foreach ($invalidProduct as &$product) {
                $product['price'] = moneyCent2Yuan($product['product_member_price']);
            }
            unset($product);
            $invalidProduct = $invalidProduct->toArray();
        }
        // 检测限购的商品 只查找未删除的
        $invalidProduct2 = ShoppingCartModel::query()
            ->where('member_id', $this->memberId)
            ->where('tbl_product.status', '!=', Constants::Product_Status_Delete)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_shopping_cart.product_id', '=', 'tbl_product.id');
            })->leftJoin('tbl_product_skus', function ($join) {
                $join->on('tbl_shopping_cart.product_skus_id', '=', 'tbl_product_skus.id');
            })->select(['tbl_shopping_cart.*', 'tbl_product.id as proid'])->get();
        if ($invalidProduct2) {
            //检测购买权限
            foreach ($invalidProduct2 as $product) {
                $obj = new Product($product->proid);
                $checkBuyPerm = $obj->checkBuyPerm();
                if ($checkBuyPerm == 0) {
                    $invalidProduct[] = $product->toArray();
                }
            }
        }

        return $invalidProduct;
    }

    /**
     * 返回购物车中商品的数量
     * @return int
     */
    public function getShoppingCartNum($refresh = 0)
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
        $num = ShoppingCartModel::query()
            ->where('member_id', $this->memberId)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_shopping_cart.product_id', '=', 'tbl_product.id');
            })
            ->leftJoin('tbl_product_skus', function ($join) {
                $join->on('tbl_shopping_cart.product_skus_id', '=', 'tbl_product_skus.id');
            })
            ->whereRaw('(tbl_product.status = ? and tbl_product_skus.inventory > ? )', [Constants::Product_Status_Sell, 0]);
        if (count($invalidIds)) $num->whereNotIn('tbl_shopping_cart.product_skus_id', $invalidIds);

        $num = $num->count("*");

        return $num;
    }

    /**
     * 返回购物车内的产品列表
     * @param int $page
     * @param int $pageSize
     * @param int $refresh 是否同步刷新（刷新会检测失效的商品等）
     * @return array
     */
    public function getShoppingCartProductList($page = 1, $pageSize = 15, $refresh = 0)
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
        $productList = ShoppingCartModel::query()
            ->where('member_id', $this->memberId)
            ->join('tbl_product', function ($join) {
                $join->on('tbl_shopping_cart.product_id', '=', 'tbl_product.id');
            })
            ->leftJoin('tbl_product_skus', function ($join) {
                $join->on('tbl_shopping_cart.product_skus_id', '=', 'tbl_product_skus.id');

            })
            ->whereRaw('(tbl_product.status = ? and tbl_product_skus.inventory > ? )', [Constants::Product_Status_Sell, 0])
            ->select([
                'tbl_shopping_cart.id',
                'tbl_shopping_cart.product_id',
                'tbl_shopping_cart.product_skus_id',
                'tbl_shopping_cart.product_quantity',
                'tbl_product.name as product_name',
                'tbl_product.small_images',
                'tbl_product_skus.sku_code',
                'tbl_product.change_at',
                'tbl_shopping_cart.updated_at',
                'tbl_product_skus.inventory'
            ])
            ->orderBy('tbl_shopping_cart.created_at', 'desc');
        if (count($invalidIds)) $productList->whereNotIn('tbl_shopping_cart.product_skus_id', $invalidIds);
        $productListCount = $productList->count();
        $productList = $productList->offset(($page - 1) * $pageSize)
            ->limit($pageSize + 1)
            ->get();
        // 是否有下一页
        if ($hasNextPage = $productList->count() > $pageSize) {
            // 最后一个去掉
            $productList->pop();
            $page += 1;
        } else {
            $page = 0;//没有下一页置为0
        }
        $productList = $productList->all();
        if ($productList) {
            $member = (new Member($this->memberId))->getInfo();
            $cartProductIds = [];
            foreach ($productList as &$item) {
                $cartProductIds[] = $item['id'];
                // 取出来第一张图片
                $image = explode(',', $item['small_images']);
                $item['image'] = $image[0];
                unset($item['small_images']);
                // 查找sku的name
                if ($item['sku_code'] != '0') {
                    $values = explode(',', trim($item['sku_code'], ','));
                    $skuInfo = ProductSkuValueModel::query()->whereIn('id', $values)->select(['value', 'small_image'])->get();
                    $skuName = $skuInfo->pluck('value')->all(); // sku的名称
                    $smallImage = $skuInfo->pluck('small_image')->filter(); // 获取sku图片
                    $smallImage = $smallImage->count() > 0 ? implode('', $smallImage->all()) : '';
                    $item['sku_name'] = implode(' ', $skuName);
                    $item['image'] = $smallImage ?: $item['image'];
                } else {
                    $item['sku_name'] = '';
                }
                // 计算产品的会员价
                $product = ShopProductFactory::createShopProduct($item['product_id'], $item['product_skus_id'], $item['product_quantity']);
                $item['price'] = moneyCent2Yuan($product->getMemberPrice($member->level));
                // 比对一下时间 看是否修改过产品
                if (strtotime($item['change_at']) > strtotime($item['updated_at'])) {
                    $item['is_change'] = true;
                } else {
                    $item['is_change'] = false;
                }
            }
            unset($item);
            // 更新时间
            ShoppingCartModel::query()->whereIn('id', $cartProductIds)->update(['updated_at' => Carbon::now()]);
        }
        return [
            'total' => $productListCount,
            'page_size' => intval($pageSize),
            'page' => $page,
            'productList' => $productList,
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
            foreach ($product as $pro) {
                $cartProduct = ShopProductFactory::createShopProduct($pro['product_id'], $pro['sku_id'], $pro['num']);
                $money += $cartProduct->calPrice($this->memberId);
            }
            return moneyCent2Yuan($money);
        } else {
            return 0;
        }
    }
}