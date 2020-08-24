<?php

namespace App\Modules\ModuleShop\Libs\SmallShop;

use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockApplySetting;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceModel;
use App\Modules\ModuleShop\Libs\Model\SmallShopModel;
use App\Modules\ModuleShop\Libs\Model\SmallShopProductModel;
use App\Modules\ModuleShop\Libs\Product\ProductClass;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberAuth;
use YZ\Core\Member\Member;
use YZ\Core\Constants;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use App\Modules\ModuleShop\Libs\Model\AgentParentsModel;
use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Member\Member as LibsMember;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * 小店
 * @author Administrator
 */
class SmallShopProduct
{
    use DispatchesJobs;
    private $siteId = 0;
    private $shop = null;

    function __construct($memberIdOrModel)
    {
        if (is_numeric($memberIdOrModel)) {
            $this->shop = SmallShopModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('member_id', $memberIdOrModel)
                ->first();
        } else {
            $this->shop = $memberIdOrModel;
        }

        if (!$this->shop) {
            throw new \Exception('无此小店，先申请小店');
        }
    }

    public function editSmallShopProduct($params)
    {
        try {
            DB::beginTransaction();
            $this->shop->optional_product_status = $params['optional_product_status'];
            $this->shop->save();
            SmallShopProductModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('shop_id', $this->shop->id)
                ->delete();
            foreach ($params['product_list'] as $item) {
                $data['product_id'] = $item['product_id'];
                $data['shop_id'] = $this->shop->id;
                $data['site_id'] = Site::getCurrentSite()->getSiteId();
                $data['show_status'] = $item['show_status'];
                $data['created_at'] = date('Y-m-d H:i:s');
                (new SmallShopProductModel())->fill($data)->save();
            }


            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

    }


    /**
     * @param array $filter 要筛选的条件 status,type,class 数组, label 数组, keyword
     * @param int $page
     * @param int $pageSize
     * @param string $selectRaw 要查找的字段
     * @param bool $isProductManagerUse 是否是选择器使用
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     * @throws \Exception
     */
    public function getSmallShopProductList($filter = null, $page = 1, $pageSize = 20, $selectRaw = null, $isProductManagerUse = false)
    {
        // 数据过滤
        $page = intval($page);
        $pageSize = intval($pageSize);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = SmallShopProductModel::query()->from('tbl_small_shop_product as ssp');
        $query->leftJoin('tbl_product', 'ssp.product_id', '=', 'tbl_product.id');
        $query->where('ssp.shop_id', $this->shop->id);
        if ($filter) {
            // 如果传入了product_ids 说明是要查找特定的产品 其他搜索条件就不应该成立了
            if (empty($filter['product_ids'])) {
                // 产品状态
                if (isset($filter['status'])) {
                    if (is_array($filter['status'])) {
                        $query->whereIn('status', $filter['status']);
                    } else {
                        if ($filter['status'] == Constants::Product_Status_Sold_Out) {
                            $query->where('is_sold_out', Constants::Product_Sold_Out);
                            // 已售罄的 只查询上架的商品
                            $query->where('status', '=', Constants::Product_Status_Sell);
                        } else {
                            $query->where('status', $filter['status']);
                        }
                    }
                } else {
                    // 默认去查询出售中的
                    $query->where('status', '=', Constants::Product_Status_Sell);
                }

                // 产品类型
                if (isset($filter['type'])) {
                    $query->where('type', $filter['type']);
                } else {
                    // 默认显示所有类型产品 除了分销资格的产品
                    $query->where('type', '<', Constants::Product_Type_Fenxiao_Physical);
                }
                // 查询分类下的产品
//                if ($filter['class']) {
//                    if (!is_array($filter['class'])) {
//                        $filter['class'] = [$filter['class']];
//                    }
//                    // 如果查询的是父级分类 也需要查询该分类的所有下级分类的产品
//                    $allClassIds = $filter['class'];
//                    ProductClass::getChildClassIds($filter['class'], $allClassIds);
//                    $allClassIds = array_unique($allClassIds);
//                    $query->whereHas('productClass', function ($q) use ($allClassIds) {
//                        return $q->whereIn('tbl_product_class.id', $allClassIds);
//                    });
//                }

                // 查询关键字匹配到的产品
                if ($filter['keyword']) {
                    $query->where(function ($q) use ($filter) {
                        return $q->orWhere('tbl_product_skus.serial_number', 'like', '%' . $filter['keyword'] . '%')
                            ->orWhere('tbl_product.name', 'like', '%' . $filter['keyword'] . '%');
                    });
                }

                // 是否展示在首页
                if (isset($filter['show_status'])) {
                    $query->where('show_status', $filter['show_status']);
                }
            } else {
                $query->whereIn('tbl_product.id', $filter['product_ids']);
                $page = 0;
            }
        }
        // skus查询要使用leftJoin 不然排序会有问题
        $query->leftJoin('tbl_product_skus', 'tbl_product_skus.product_id', '=', 'tbl_product.id');
        if ($selectRaw === null) {
            $selectRaw = 'sum(tbl_product_skus.inventory) as inventory,tbl_product_skus.sku_code,tbl_product_skus.id as skus_id, tbl_product.*,ssp.show_status';
        }
        $query->selectRaw($selectRaw);
        $query->groupBy('tbl_product.id');
        // 查找预警产品
        if ($filter['is_inventory'] == 1) {
//            $query->whereRaw('tbl_product.warning_inventory >= tbl_product_skus.inventory');
            $query->selectRaw('Min(tbl_product_skus.inventory) as skus_inventory');
            $query->havingRaw('tbl_product.warning_inventory >= skus_inventory');
        }

        // 因为查询中有 group by 和 having 所以count会有问题
        // 使用原生的sql去查询总记录数
        // sql 语句
        $sql = $query->toSql();
        $sql = "select count(*) as product_count from ({$sql}) as temp_count";
        $bindings = $query->getBindings();
        $total = SmallShopProductModel::runSql($sql, $bindings);
        $total = $total[0]->product_count;
        // 排序
        $query = self::buildProductListOrder($query, $filter['order_by']);
        // 加载分类
//        if ($filter['show_class'] !== 0 && $filter['show_class'] !== false) {
//            $query->with([
//                'productClass' => function ($q) {
//                    $q->where('tbl_product_class.status', 1);
//                    $q->select(['class_name', 'tbl_product_class.id', 'parent_id']);
//                }
//            ]);
//        }
        // 加载SKU
        if ($filter['show_sku']) {
            $query->with([
                'productSkus'
            ]);
        }
        $last_page = ceil($total / $pageSize);
        // 没有传page 代表获取所有
        if ($page > 0) {
            $query->forPage($page, $pageSize);
        }
        $list = $query->get()->toArray();
        // 产品选择器不需要这些逻辑
        if (!$isProductManagerUse) {
            // 前台获取列表
            $discount = 10;

            // 查找出最优惠的会员价
            $memberDiscount = 10;
            foreach ($list as &$pro) {
                // 计算出最低会员价
                $pro['member_price'] = moneyMul($pro['price'], $memberDiscount / 10);
                // 商品原始销售价
                $pro['ori_price'] = $pro['price'];
                // 前台如果登录之后 需要计算会员价
                $pro['price'] = moneyMul($pro['price'], $discount / 10);
                // 价格转换为元
                if ($filter['price_unit'] != 'cent') $pro = self::productPriceCent2Yuan($pro);
                // 售后量
                $pro['after_sale_count'] = $pro['after_sale_count'] ?: '0';
                $pro['sold_count'] = $pro['sold_count'] ?: '0';
                if ($pro['product_skus']) {
                    foreach ($pro['product_skus'] as &$skuItem) {
                        $skuItem['sku_name'] = $skuItem['sku_name'] ? json_decode($skuItem['sku_name'], true) : [];
                    }
                }
            }
        }

        $result = [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list,
            'optional_product_status' => $this->shop->optional_product_status
        ];
        return $result;
    }

    /**
     * 构建产品列表的排序
     * @param $query
     * @param $orderBy
     * @return mixed
     * @throws \Exception
     */
    public static function buildProductListOrder($query, $orderBy)
    {
        //使用原生SQL排序的情况
        if ($orderBy['raworder']) {
            return $query->orderByRaw($orderBy['raworder']);
        }
        switch ($orderBy['column']) {
            // 按价格或会员价排序
            case 'price':
                $orderColumn = 'price';
                break;
            // 库存排序
            case 'inventory':
                $orderColumn = 'inventory';
                break;
            // 销量排序
            case 'sold_count':
                $orderColumn = 'sold_count';
                break;
            // 售后量排序
            case 'after_sold_count':
                $orderColumn = 'after_sold_count';
                break;
            // 售罄时间排序
            case 'sold_out_at':
                $orderColumn = 'sold_out_at';
                break;
            // 上架时间排序
            case 'sell_at':
                $orderColumn = 'sell_at';
                break;
            // 创建时间排序
            case 'created_at':
                $orderColumn = 'created_at';
                break;
            // 更新时间排序
            case 'updated_at':
                $orderColumn = 'updated_at';
                break;
            // 按售后数排序
            case 'after_sale_count':
                $orderColumn = 'after_sale_count';
                break;
            // 按sku 预警库存排序
            case 'skus_inventory':
                $orderColumn = 'skus_inventory';
                break;
            default:
                $orderColumn = 'sell_at';
                $orderBy['order'] = 'desc';
        }
        return $query->orderBy($orderColumn, $orderBy['order']);
    }

    /**
     * 把相关价格转换为元
     * @param $priceData
     * @return mixed
     */
    public static function productPriceCent2Yuan($priceData)
    {
        $filed = ['price', 'ori_price', 'market_price', 'supply_price', 'member_price'];
        foreach ($filed as $item) {
            if (isset($priceData[$item])) {
                $priceData[$item] = moneyCent2Yuan($priceData[$item]);
            }
        }
        return $priceData;
    }
}
