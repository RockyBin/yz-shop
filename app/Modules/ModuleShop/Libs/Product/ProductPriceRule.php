<?php
/**
 * 产品sku业务类
 */

namespace App\Modules\ModuleShop\Libs\Product;


use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use Illuminate\Support\Carbon;
use YZ\Core\Site\Site;

class ProductPriceRule
{
    private $_site = null;

    public function __construct( $site = null)
    {
        $this->_site = $site;
        if (!$this->_site) {
            $this->_site = Site::getCurrentSite();
        }
    }

    public function getProductMinPrice($product_id){
        if($product_id==0){
           return false;
        }

    }

}