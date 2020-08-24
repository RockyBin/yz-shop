<?php
/**
 * 用来计算订单运费
 * User: liyaohui
 * Date: 2019/4/23
 * Time: 10:56
 */

namespace App\Modules\ModuleShop\Libs\Shop;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;
use  App\Modules\ModuleShop\Libs\CalFreight\AbstractCalFreight;

class BaseCalOrderFreight extends AbstractCalFreight
{
    public function __construct($cityId, $productList)
    {
        $this->cityId = $cityId;
        $this->productList = $productList;
        $this->setCalType();
    }

    /**
     * 获取当前订单运费
     * @return float|int
     * @throws \Exception
     */
    public function getOrderFreight()
    {
        if (!$this->cityId || !$this->productList) {
            return 0;
        }
        if ($this->calType == Constants::OrderFreightCal_Default) {
            return $this->calFreight();
        }
    }

    public function setCalType()
    {
        $this->calType= Constants::OrderFreightCal_Default;
    }

}