<?php
/**
 * 用来计算订单运费
 * User: liyaohui
 * Date: 2019/4/23
 * Time: 10:56
 */

namespace App\Modules\ModuleShop\Libs\CalFreight;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;
use App\Modules\ModuleShop\Libs\Shop\BaseShopProduct;

abstract class AbstractCalFreight
{
    public $cityId = 0; // 地址城市id
    public $calType = Constants::OrderFreightCal_Default; // 订单运费计算规则类型
    public $productList = []; // 订单中产品列表
    public $freightId = 0;
    /**
     * 获取当前订单运费
     * @return float|int
     * @throws \Exception
     */
    final function calFreight()
    {
        $freight = 0;
        $templateData = $this->getFreightTemplateData();
        foreach ($templateData as $key => $data) {
            // 计重
            if ($key == 0) {
                // 最大首重运费id
                $maxId = $data['max']['id'];
                foreach ($data['template'] as $temp) {
                    // 计算出当前模板的产品重量
                    $weight = array_map(function ($t) {
                        if($t['product_type']==1) return 0;
                        return floatval($t['weight'] * $t['num']);
                    }, $temp['proList']);
                    $weight = ceil(array_sum($weight));
                    // 重量为0 则运费为0
                    if ($weight > 0) {
                        // 如果是首重运费最大的模板 拿重量减1 乘以续重运费 加 首重运费
                        if ($temp['id'] == $maxId) {
                            $freight += $temp['money']['firstFee'] + $temp['money']['renewFee'] * ($weight - 1);
                        } else {
                            // 不是首重运费最大模板
                            $freight += $temp['money']['renewFee'] * $weight;
                        }
                    }
                }
            } // 计件
            elseif ($key == 1) {
                // 最大首件运费id
                $maxId = $data['max']['id'];
                foreach ($data['template'] as $temp) {
                    // 计算出当前模板的商品件数
                    $num = array_map(function ($t) {
                        if($t['product_type']==1) return 0;
                        return $t['num'];
                    }, $temp['proList']);
                    $num = array_sum($num);
                    // 如果是首件最大的运费模板 拿首件运费 加 总件数减1 乘 续件运费
                    if ($temp['id'] == $maxId) {
                        $freight += $temp['money']['firstFee'] + $temp['money']['renewFee'] * ($num - 1);
                    } else {
                        // 不是首件最大模板 直接拿总件数 乘 续件运费
                        $freight += $temp['money']['renewFee'] * $num;
                    }
                }
            } elseif ($key == 2) {
                // 固定运费 直接返回最大运费
                $freight += $data['max']['money'];
            } else {
                // 不存在的模板类型
                throw new \Exception(trans('shop-front.shop.freight_fee_type_error'));
            }
        }
        return $freight;
    }

    /**
     * 获取当前订单使用的运费模板数据
     * $templateInfo 所有用到的运费模板
     * @return array
     * @throws \Exception
     */
    private function getFreightTemplateData()
    {
        $templateInfo = $this->setTemplateInfo();
        $templateData = [];
        if ($templateInfo) {
            $templateIds = array_keys($templateInfo);
            $templateList = FreightTemplateModel::whereIn('id', $templateIds)->get();
            // 获取运费模板的相关数据
            foreach ($templateList as $temp) {
                // 当前模板运费金额
                $templateInfo[$temp['id']]['money'] = $this->getFreightMoneyData($temp);
                // 当前模板的id
                $templateInfo[$temp['id']]['id'] = $temp['id'];
                // 把按件 按重量 固定运费 分开存放 便于后面计算
                $templateData[$temp['fee_type']]['template'][] = $templateInfo[$temp['id']];
                // 求出最大首重/件 运费模板
                if (isset($templateData[$temp['fee_type']]['max'])) {
                    if ($templateInfo[$temp['id']]['money']['firstFee'] > $templateData[$temp['fee_type']]['max']['money']) {
                        $templateData[$temp['fee_type']]['max']['money'] = $templateInfo[$temp['id']]['money']['firstFee'];
                        $templateData[$temp['fee_type']]['max']['id'] = $temp['id'];
                    }
                } else {
                    $templateData[$temp['fee_type']]['max']['money'] = $templateInfo[$temp['id']]['money']['firstFee'];
                    $templateData[$temp['fee_type']]['max']['id'] = $temp['id'];
                }
            }
        }
        return $templateData;
    }

    private function setTemplateInfo()
    {
        // 获取所有用到的运费模板
        $templateInfo = [];
        // 获取订单中产品的数量和重量数据
        foreach ($this->productList as $pro) {
            if ($this->calType == Constants::OrderFreightCal_Default) {
                $freightId = $pro->getThisProductModel()->freight_id;
            } elseif ($this->calType == Constants::OrderFreightCal_Template) {
                $freightId = $this->freightId ? $this->freightId : 0;
            }
            if ($pro instanceof BaseShopProduct) {
                $weight = $pro->getThisProductSkuModel()->weight;
            } else {
                $weight = $pro->weight ? $pro->weight : 0;
            }
            if ($freightId != 0) {
                $templateInfo[$freightId]['proList'][] = [
                    'num' => $pro->num,
                    'weight' => $weight,
                    'product_type'=>$pro->product_type
                ];
            }
        }
        return $templateInfo;
    }

    /**
     * 设置运费类型
     * @throws \Exception
     */
    abstract function setCalType();

    /**
     * 获取当前运费模板的运费金额数据
     * @param $freightModel
     * @return array
     * @throws \Exception
     */
    private function getFreightMoneyData($freightModel)
    {
        $areas = json_decode($freightModel->delivery_area, true);
        $moneyData = ['firstFee' => 0, 'renewFee' => false];
        // 自定义配送区域
        if ($freightModel->delivery_type == 1) {
            foreach ($areas as $item) {
                if (strpos($item['area'], strval($this->cityId)) !== false) {
                    $moneyData['firstFee'] = moneyYuan2Cent($item['firstFee']);
                    $moneyData['renewFee'] = $freightModel->fee_type == 2 ? false : moneyYuan2Cent($item['renewFee']);
                    return $moneyData;
                }
            }
            // 不在配送范围 抛出错误
//            if($this->notDeliveryThorw){
//                throw new \Exception(trans('shop-front.shop.order_has_product_not_delivery'));
//            }
        } else {
            $moneyData['firstFee'] = moneyYuan2Cent($areas[0]['firstFee']);
            if ($freightModel->fee_type != 2) {
                $moneyData['renewFee'] = moneyYuan2Cent($areas[0]['renewFee']);
            }
            return $moneyData;
        }
    }
}