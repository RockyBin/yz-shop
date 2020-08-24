<?php
namespace App\Modules\ModuleShop\Libs\Shop;

/**
 * 购物车接口
 * Interface ShopCart
 * @package App\Modules\ModuleShop\Libs\Shop
 */
interface IShopCart{
    /**
     * 添加商品到购物车
     * @param IShopProduct $pro 商品类
     * @return mixed
     */
    public function addProduct(IShopProduct $pro);

    /**
     * 删除购物车内的商品
     * @param int|array $productId 商品ID（数据库中的主键）
     * @return mixed
     */
    public function removeProduct($productId);

    /**
     * 修改商品数量
     * @param int $productId 商品ID（数据库中的主键）
     * @param int $num
     * @return mixed
     */
    public function increaseProductNum($productId,int $num);

    /**
     * 减少商品数量
     * @param $productId 商品ID（数据库中的主键）
     * @param int $num
     * @return mixed
     */
    public function decreaseProductNum($productId,int $num);

    /**
     * 刷新商品商品信息，比如将失效商品放到失效区等
     * @return mixed
     */
    public function refresh();
}
?>