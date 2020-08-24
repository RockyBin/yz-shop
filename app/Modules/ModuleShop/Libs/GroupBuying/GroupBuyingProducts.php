<?php
/**
 * 拼团产品逻辑
 * User: pangwenke
 * Date: 2020/4/3
 * Time: 10:17
 */

namespace App\Modules\ModuleShop\Libs\GroupBuying;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingProductsModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Product\ProductClass;
use App\Modules\ModuleShop\Libs\Product\ProductCollection;
use App\Modules\ModuleShop\Libs\Product\ProductSku;
use App\Modules\ModuleShop\Libs\Shop\NormalShopProduct;
use Carbon\Carbon;
use YZ\Core\Constants;
use YZ\Core\Member\Auth;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;

class GroupBuyingProducts
{
    private $_model = null;
    private $_siteId = 0;

    /**
     * GroupBuyingSetting constructor.
     * @param int $idOrModel
     * @throws \Exception
     */
    public function __construct($idOrModel = 0)
    {
        $this->_siteId = getCurrentSiteId();
        if (is_numeric($idOrModel) && $idOrModel > 0) {
            $model = GroupBuyingProductsModel::query()->find($idOrModel);
            if (!$model) {
                throw new \Exception('找不到该活动产品');
            }
        } elseif ($idOrModel instanceof GroupBuyingProductsModel) {
            $model = $idOrModel;
        } else {
            $model = new GroupBuyingProductsModel();
            $model->site_id = $this->_siteId;
        }
        $this->_model = $model;
    }

    public static function getFrontList($params)
    {
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        $now = date('Y-m-d H:i:s', time());
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;

        $query = GroupBuyingProductsModel::query()
            ->from('tbl_group_buying_products')
            ->where('tbl_group_buying_products.site_id', getCurrentSiteId());

        if (isset($params['group_buying_id'])) {
            $query->where('gb.id', $params['group_buying_id']);
        }
        if (isset($params['group_buying_setting_id'])) {
            $query->where('tbl_group_buying_products.group_buying_setting_id', $params['group_buying_setting_id']);
        }
        if (isset($params['keyword'])) {
            $query->where(function ($query) use ($params) {
                $query->where('p.name', 'like', '%' . $params['keyword'] . '%');
                $query->orWhere('ps.serial_number', 'like', '%' . $params['keyword'] . '%');
            });
        }
        if (isset($params['status'])) {
            switch (true) {
                case $params['status'] == GroupBuyingConstants::GroupBuyingStatus_Ready:
                    $query->where('setting.start_time', '<', $now);
                    break;
                case $params['status'] == GroupBuyingConstants::GroupBuyingStatus_Processing:
                    $query->where('setting.start_time', '>=', $now);
                    $query->where('setting.end_time', '<=', $now);
                    break;
                case $params['status'] == GroupBuyingConstants::GroupBuyingStatus_End:
                    $query->where('setting.end_time', '>', $now);
                    break;
            }
        }

        if (isset($params['product_status'])) {
            $query->where('p.status', $params['product_status']);
        }

        if (isset($params['product_id'])) {
            $product_id = myToArray($params['product_id']);
            $query->whereIn('tbl_group_buying_products.master_product_id', $product_id);
        }

        if (isset($params['id'])) {
            $groupbuying_product_id = myToArray($params['id']);
            $query->whereIn('tbl_group_buying_products.id', $groupbuying_product_id);
        }

        if (isset($params['is_delete'])) {
            $query->where('setting.is_delete', $params['is_delete']);
        }

        // 查询分类下的产品
        if ($params['class']) {
            if (!is_array($params['class'])) {
                $params['class'] = [$params['class']];
            }
            // 如果查询的是父级分类 也需要查询该分类的所有下级分类的产品
            $allClassIds = $params['class'];
            ProductClass::getChildClassIds($params['class'], $allClassIds);
            $allClassIds = array_unique($allClassIds);
            $query->whereHas('productClass', function ($q) use ($allClassIds) {
                return $q->whereIn('tbl_product_class.id', $allClassIds);
            });
        }

        $query->leftJoin('tbl_product as p', 'p.id', 'tbl_group_buying_products.master_product_id');
        $query->leftJoin('tbl_group_buying_setting as setting', 'setting.id', 'tbl_group_buying_products.group_buying_setting_id');
        $query->leftJoin('tbl_group_buying as gb', 'setting.id', 'gb.group_buying_setting_id');

        //  判断产品是否规格进行单变双，双变单的操作
        $query->leftJoin('tbl_group_buying_skus as gbs', 'gbs.group_product_id', 'tbl_group_buying_products.id');
        $query->leftJoin('tbl_product_skus as ps', 'gbs.sku_id', 'ps.id');
        $query->whereNotNull('ps.id');
        $query->where('p.status', Constants::Product_Status_Sell);
        $query->with([
            'productClass' => function ($q) {
                $q->where('tbl_product_class.status', 1);
                $q->select(['class_name', 'tbl_product_class.id', 'parent_id']);
            }
        ]);


        if (isset($params['order_by'])) {
            if ($params['id']) {
                $query->orderByRaw("find_in_set(tbl_group_buying_products.id,'" . implode(',', myToArray($params['id'])) . "')");
            }
        } else {
            $query->orderBy('tbl_group_buying_products.id', 'desc');
        }
        $query->groupBy('tbl_group_buying_products.master_product_id');
        $query->select(['tbl_group_buying_products.id', 'tbl_group_buying_products.master_product_id', 'p.name', 'p.small_images', 'p.price', 'tbl_group_buying_products.order_base_num', 'tbl_group_buying_products.total_sold_num', 'tbl_group_buying_products.min_price', 'setting.start_time', 'setting.end_time', 'p.status as status', 'setting.people_num', 'setting.open_order_base_num']);

        $total = $query->count();
        $query = $query->forPage($page, $page_size);
        //输出-最后页数
        $last_page = ceil($total / $page_size);

        $list = $query->get();
        if ($list) {
            foreach ($list as $key => &$item) {
                // 0 : 未开始 1 ：进行中 2：已结束
                if ($item->start_time > $now) {
                    $item->groupbuying_status = GroupBuyingConstants::GroupBuyingStatus_Ready; // 未开始;
                } elseif ($item->start_time <= $now && $item->end_time >= $now) {
                    $item->groupbuying_status = GroupBuyingConstants::GroupBuyingStatus_Processing; // 进行中;
                } elseif ($item->end_time < $now) {
                    $item->groupbuying_status = GroupBuyingConstants::GroupBuyingStatus_End; // 已结束;
                }
                if ($item->small_images) {
                    $item->small_images = explode(',', $item->small_images)[0];
                }
                $start_time = strtotime($item->start_time);
                $item->start_time = date('m', $start_time) . '月' . date('j', $start_time) . '日' . date('H', $start_time) . ':' . date('i', $start_time);
                $item->price = moneyCent2Yuan($item->price);
                $item->min_price = moneyCent2Yuan($item->min_price);
                $item->order_base_num = $item->open_order_base_num == 1 ? $item->order_base_num : 0;
            }
        }

        $result = [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
        return $result;
    }

    static public function getDetail($params)
    {
        //判断活动是否结束了
        $query = GroupBuyingProductsModel::query()
            ->from('tbl_group_buying_products as gbp')
            ->where('gbp.site_id', getCurrentSiteId())
            ->where('gbp.id', $params['group_product_id']);
        $query->leftJoin('tbl_product as p', 'p.id', 'gbp.master_product_id');
        $query->select(['gbp.id', 'gbp.master_product_id', 'gbp.order_base_num', 'p.name as product_name', 'p.big_images', 'p.price as product_price', 'gbp.total_sold_num', 'gbp.min_price', 'p.status as product_status', 'gbp.group_buying_setting_id', 'p.detail', 'p.params']);
        $productInfo = $query->first();

        $config = (new GroupBuyingSetting($productInfo->group_buying_setting_id))->getModel();
        if ($productInfo) {
            //获取会员价
            $productInfo->member_price = $productInfo->product_price;
            $memberId = Auth::hasLogin();
            $productInfo->have_collection = false;
            if ($memberId) {
                //计算SKU最低价
                $productSku = ProductSku::getMinSku($productInfo->master_product_id);
                $baseShopProduct = new NormalShopProduct($productInfo->master_product_id, $productSku->id);
                $member = new Member($memberId);
                $productInfo->member_price = $baseShopProduct->getMemberPrice($member->getModel()->level);
                //输出这个产品是否被收藏了
                $collection = new ProductCollection();
                $collection->findByMemberProduct($memberId, $productInfo->master_product_id);
                $productInfo->have_collection = $collection->getModel();

            }

            $productInfo->member_price = moneyCent2Yuan($productInfo->member_price);
            $productInfo->product_price = moneyCent2Yuan($productInfo->product_price);
            $productInfo->min_price = moneyCent2Yuan($productInfo->min_price);
            $productInfo->order_base_num = $config->open_order_base_num == 1 ? $productInfo->order_base_num : 0;
        }


        if ($config) {
            $now = time();
            //计算剩余时间
            if ($now > strtotime($config->start_time)) {
                $remainingTime = abs(strtotime($config->end_time) - $now);
            } else {
                $remainingTime = abs($now - strtotime($config->start_time));
            }
            $day = $remainingTime < 86400 ? 0 : intval($remainingTime / 86400);//天
            $config->remaining_day = $day;
            $hour = intval((($remainingTime / 86400) - $day) * 24);//小时
            $config->remaining_hour = $hour;
            $minute = intval((((($remainingTime / 86400) - $day) * 24) - $hour) * 60);//分钟
            $config->remaining_minute = $minute;
            $second = intval(((((((($remainingTime / 86400) - $day) * 24) - $hour) * 60) - $minute) * 60));//秒
            $config->remaining_second = $second;
            $config->status = GroupBuyingSetting::getStatus($config->start_time, $config->end_time);
            $start_time = strtotime($config->start_time);
            $config->start_time = date("n", $start_time) . "月" . date("j", $start_time) . "日" . date("H", $start_time) . ":" . date("i", $start_time);
        }
//        if ($config->end_time > Carbon::now()) {
//            throw new \Exception('活动已结束');
//        }
        if (!static::checkProduct($params['group_product_id']) || !$config || !$productInfo->id) {
            throw new \Exception('商品已下架或者已删除或者已修改');
        }

        //拼团设置类
        $data['groupbuying_setting'] = $config;
        $data['product_info'] = $productInfo;
        return $data;
    }


    public function getSku($memberId = 0)
    {
        if ($memberId) {
            $member = new Member($memberId);
        }
        $groupProduct = $this->_model;
        $skus = GroupBuyingSkusModel::query()
            ->from('tbl_group_buying_skus as gbs')
            ->where('gbs.site_id', getCurrentSiteId())
            ->where('gbs.group_product_id', $this->_model->id)
            ->whereNotNull('ps.id')
            ->leftJoin('tbl_product_skus as ps', 'gbs.sku_id', 'ps.id')
            ->select(['gbs.*', 'ps.price as master_sku_price', 'ps.sku_code', 'ps.sku_image', 'ps.inventory'])
            ->get()
            ->toArray();
        $skuValue = $groupProduct->productSkuValue()->get()->toArray();
        $skuName = $groupProduct->productSkuName()->get()->toArray();

        foreach ($skus as &$sku) {
            if ($member) {
                $baseShopProduct = new NormalShopProduct($sku['master_product_id'], $sku['sku_id']);
                $sku['master_sku_price'] = $baseShopProduct->getMemberPrice($member->getModel()->level);
            } else {
                $sku['master_sku_price'] = $sku['master_sku_price'];
            };
            $sku['group_price'] = moneyCent2Yuan($sku['group_price']);
            $sku['head_price'] = moneyCent2Yuan($sku['head_price']);
            $sku['master_sku_price'] = moneyCent2Yuan($sku['master_sku_price']);
        };
        foreach ($skuName as $k => &$v) {
            foreach ($skuValue as $k1 => $v1) {
                if ($v['id'] == $v1['sku_name_id']) {
                    $v['children'][] = $v1;
                }
            }
        }
        return ['skuName' => $skuName, 'skus' => $skus];
    }


    public function getModel()
    {
        return $this->_model;
    }

    static public function checkProduct($groupBuyingProductId)
    {
        $GroupBuyingPorduct = GroupBuyingProductsModel::query()->find($groupBuyingProductId);
        if ($GroupBuyingPorduct) {
            $product = ProductModel::query()
                ->where('id', $GroupBuyingPorduct->master_product_id)
                ->where('status', Constants::Product_Status_Sell)
                ->first();
            // 判断产品状态
            if (!$product) return false;
            // 判断产品是否规格进行单变双，双变单的操作
            $GroupBuyingPorductSku = GroupBuyingSkusModel::query()
                ->where('group_product_id', $GroupBuyingPorduct->id)
                ->pluck('sku_id')
                ->all();
            $count = ProductSkusModel::query()->whereIn('id', $GroupBuyingPorductSku)->count();

            if ($count <= 0) return false;
        }
        return true;
    }

    public function checkActivityStatus()
    {
        $setting = (new GroupBuyingSetting($this->_model->group_buying_setting_id))->getModel();
        $settingStatus = GroupBuyingSetting::getStatus($setting->start_time, $setting->end_time);
        if ($settingStatus == GroupBuyingConstants::GroupBuyingStatus_End) {
            return false;
        }
        return true;
    }
}