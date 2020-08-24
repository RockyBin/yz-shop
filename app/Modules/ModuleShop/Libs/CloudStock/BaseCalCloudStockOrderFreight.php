<?php
/**
 * 用来计算订单运费
 * User: liyaohui
 * Date: 2019/4/23
 * Time: 10:56
 */

namespace App\Modules\ModuleShop\Libs\CloudStock;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;
use  App\Modules\ModuleShop\Libs\CalFreight\AbstractCalFreight;

class BaseCalCloudStockOrderFreight extends AbstractCalFreight
{

    public function __construct($cityId, $productList)
    {
        $this->cityId = $cityId;
        $this->productList = $productList;
        $dealerBaseSettingModel = DealerBaseSetting::getCurrentSiteSetting();
        $this->freightId = $dealerBaseSettingModel->freight_id;
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
        return $this->calFreight();

    }

    public function canDelivery()
    {
        if (!$this->freightId) return true;
        $mFreight = FreightTemplateModel::find($this->freightId);
        if ($mFreight && $mFreight->delivery_type != 1) return true;
        $areas = json_decode($mFreight->delivery_area, true);
        foreach ($areas as $item) {
            if (strpos($item['area'], strval($this->cityId)) !== false) return true;
        }
        return false;
    }

    public function setCalType()
    {
        $this->calType = Constants::OrderFreightCal_Template;
    }

    public function setNotDelivery()
    {
        $this->calType = true;
    }

}