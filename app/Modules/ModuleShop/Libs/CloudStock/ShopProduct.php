<?php
namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use YZ\Core\Site\Site;

/**
 * 云仓进货时用的商品类，用来计算价格，检测商品状态等
 */
class ShopProduct
{
    public $productId = 0; //产品ID
    public $skuId = 0; //规格ID
    public $price = 0; //最终进货单价
    public $num = 1; //订购数量
    public $name = ''; //产品名称
    public $image = ''; //小图路径
    public $skuName = []; //规格名称
    protected $_productModel = null;
    protected $_productSku = null;
    protected $_cloudStock = null;
    protected $_member = null;
    protected $_site = null;

    /**
     * 云仓进货时用的商品类构造函数
     *
     * @param int|App\Modules\ModuleShop\Libs\Member\Member $memberIdOrMemberClass 会员ID或会员类实例
     * @param int $productId 商品ID 或 数据模型
     * @param integer $skuId 规格ID 或 数据模型
     * @param integer $num 定购数量
     */
    public function __construct($memberIdOrMemberClass, $productIdOrModel, $skuIdOrModel = 0, $num = 1)
    {
        $this->_site = Site::getCurrentSite();
        if(is_numeric($memberIdOrMemberClass)) $this->_member = new Member($memberIdOrMemberClass);
        else $this->_member = $memberIdOrMemberClass;
        $this->_cloudStock = new CloudStock($this->_member->getModel()->id, false);
        $this->initProductModel($productIdOrModel);
        $this->initProductSkuModel($skuIdOrModel);
        $this->num = $num;
    }

    private function initProductModel($productIdOrModel){
        if(is_numeric($productIdOrModel)){
            $this->_productModel = ProductModel::where(['id' => $productIdOrModel,'site_id' => $this->_site->getSiteId()])->select(['id','name','small_images','price','status'])->first();
        }else{
            $this->_productModel = $productIdOrModel;
        }
        if(!$this->_productModel){
            throw new \Exception('商品不存在');
        }
        $this->productId = $this->_productModel->id;
        $this->name = $this->_productModel->name;
        $this->image = explode(',', $this->_productModel->small_images)[0];
    }

    protected function initProductSkuModel($skuIdOrModel){
        if(is_numeric($skuIdOrModel)) {
            if ($skuIdOrModel) {
                $this->_productSku = $this->_productModel->productSkus()->where(['id' => $skuIdOrModel, 'site_id' => $this->_site->getSiteId()])->first();
            } else {
                $this->_productSku = $this->_productModel->productSkus()->where(['sku_code' => '0', 'site_id' => $this->_site->getSiteId()])->first();
            }
        }else{
            $this->_productSku = $skuIdOrModel;
        }
        if(!$this->_productSku){
	        throw new \Exception('商品规格不存在');
        }
        if ($this->_productSku->sku_image) $this->image = $this->_productSku->sku_image;
        if ($this->_productSku->sku_name) $this->skuName = json_decode($this->_productSku->sku_name, true);
        $this->skuId = $this->_productSku->id;
        $this->price = $this->_cloudStock->getProductPrice(
            $this->_productSku->price,
            $this->_member->getModel()->dealer_level,
            $this->_member->getModel()->dealer_hide_level,
            $this->_productSku->cloud_stock_rule
        );
    }

    /**
     * 计算产品价格
     * @return mixed
     */
    public function calMoney()
    {
        return moneyMul($this->price * $this->num);
    }

    /**
     * 判断会员能否购买此商品，要检测会员限制规则和库存
     * @return mixed
     */
    public function canBuy()
    {
        //检测购买权限
        /*$obj = new Product($this->_productModel);
        $checkBuyPerm = $obj->checkBuyPerm();
        if ($checkBuyPerm == 0) {
            return makeServiceResult(401, trans('shop-front.shop.product_noperm'), ['noperm' => 1]);
        }*/
        //检测是否已经下架
        if ($this->_productModel->status != 1) {
            return makeServiceResult(410, trans('shop-front.shop.product_noactive'), ['noactive' => 1]);
        }
        return makeServiceResult(200,'ok');
    }

    /**
     * 获取此产品在订购时需要记录的快照信息
     * @return mixed
     */
    public function getSnapShotInfo()
    {
        return [];
    }

    /**
     * 获取当前的产品model
     * @return \LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection|null
     */
    public function getThisProductModel()
    {
        return $this->_productModel;
    }

    /**
     * 获取当前的sku model
     * @return null
     */
    public function getThisProductSkuModel()
    {
        return $this->_productSku;
    }

    /**
     * 获取当前产品的分类信息
     * @return mixed
     */
    public function getThisProductClass()
    {
        return $this->_productModel->productClass;
    }

    /**
     * 获取当前产品的购买会员
     * @return mixed
     */
    public function getThisMember()
    {
        return $this->_member;
    }
}
