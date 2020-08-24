<?php
/**
 * 产品管理业务类
 */

namespace App\Modules\ModuleShop\Libs\Product;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\Model\CouponModel;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierBaseSettingModel;
use App\Modules\ModuleShop\Libs\Shop\NormalShopProduct;
use App\Modules\ModuleShop\Libs\Supplier\SupplierAdmin;
use App\Modules\ModuleShop\Libs\Supplier\SupplierConstants;
use App\Modules\ModuleShop\Libs\Supplier\SupplierShopProduct;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use YZ\Core\Common\Export;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\License\SNUtil;
use YZ\Core\Member\Auth;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use YZ\Core\Constants;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;
use App\Modules\ModuleShop\Libs\SiteConfig\FreightTemplate as Freight;
use App\Modules\ModuleShop\Libs\Coupon\Coupon;
use App\Modules\ModuleShop\Libs\Model\ProductClassModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use YZ\Core\Common\DataCache;
use YZ\Core\Member\Member;

class Product
{
    private $_site = null;
    private $_product = null;

    public function __construct($product = null, $site = null)
    {
        $this->_site = $site;
        if (!$this->_site) {
            $this->_site = Site::getCurrentSite();
        }
        if ($product) {
            if ($product instanceof ProductModel) {
                $this->_product = $product;
            } else {
                // 将产品对象放到全局变量中，避免当一个请求中需要多次使用此类时要多读数据库
                $this->_product = DataCache::getData(static::class . '_product_' . $product);
                if (!$this->_product) {
                    $this->_product = ProductModel::query()
                        ->where('id', $product)
                        ->where('status', '!=', Constants::Product_Status_Delete)
                        ->first();
                    DataCache::setData(static::class . '_product_' . $product, $this->_product);
                }
                if (!$this->_product) {
                    throw new \Exception('数据不存在');
                }
            }
        } else {
            $this->_product = new ProductModel();
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
    public static function getList($filter = null, $page = 1, $pageSize = 20, $selectRaw = null, $isProductManagerUse = false)
    {
        // 数据过滤
        $page = intval($page);
        $pageSize = intval($pageSize);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = ProductModel::query();
        if ($filter) {
            if ($filter['coupon_id']) {
                $couponModels = CouponModel::query()
                    ->where('site_id', getCurrentSiteId())
                    ->where('id', $filter['coupon_id'])
                    ->first();
                $classIds = $couponModels->product_info;
                if ($classIds) {
                    $classIds = trim($classIds, ',');
                    $classIds = explode(',', $classIds);
                    if ($couponModels->product_type == 2) {
                        $filter['product_ids'] = $classIds;
                    } else {
                        $filter['class'] = $classIds;
                    }
                }
            }
            // 如果传入了product_ids 说明是要查找特定的产品 其他搜索条件就不应该成立了
            if (empty($filter['product_ids'])) {
                // 产品状态
                if (isset($filter['status'])) {
                    if (is_array($filter['status'])) {
                        $query->whereIn('tbl_product.status', $filter['status']);
                    } else {
                        if ($filter['status'] == Constants::Product_Status_Sold_Out) {
                            $query->where('is_sold_out', Constants::Product_Sold_Out);
                            // 已售罄的 只查询上架的商品
                            $query->where('tbl_product.status', '=', Constants::Product_Status_Sell);
                        } else {
                            $query->where('tbl_product.status', $filter['status']);
                        }
                    }
                } else {
                    // 默认去查询出售中的
                    $query->where('tbl_product.status', '=', Constants::Product_Status_Sell);
                }

                // 产品类型
                if (isset($filter['type'])) {
                    $query->where('tbl_product.type', $filter['type']);
                } else {
                    // 默认显示所有类型产品 除了分销资格的产品
                    $query->where('tbl_product.type', '<', Constants::Product_Type_Fenxiao_Physical);
                }
                // 如果传入优惠券id 则去查询对应的分类

                // 查询分类下的产品
                if ($filter['class']) {
                    if (!is_array($filter['class'])) {
                        $filter['class'] = [$filter['class']];
                    }
                    // 如果查询的是父级分类 也需要查询该分类的所有下级分类的产品
                    $allClassIds = $filter['class'];
                    ProductClass::getChildClassIds($filter['class'], $allClassIds);
                    $allClassIds = array_unique($allClassIds);
                    $query->whereHas('productClass', function ($q) use ($allClassIds) {
                        return $q->whereIn('tbl_product_class.id', $allClassIds);
                    });

                }
                // TODO 查询标签下的产品 暂时没有
//            if ($filter['label']) {
//                $query->whereHas('productLabel', function($q) use ($filter) {
//                    return $q->whereIn('tbl_product_label.id', $filter['label']);
//                });
//            }

                // 是否启用云仓
                if (array_key_exists('cloud_stock_status', $filter)) {
                    $query->where('tbl_product.cloud_stock_status', $filter['cloud_stock_status']);
                }

                // 如果是供应商版本
                $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
                if ($sn->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_SUPPLIER)) {
                    // 查找是否是供应商商品 没有通过审核的商品不在此列表展示
                    if (isset($filter['is_supplier_product'])) {
                        $isSupplierProduct = intval($filter['is_supplier_product']);
                        if ($isSupplierProduct == 1) {
                            $query->where('tbl_product.supplier_member_id', '>', 0)
                                ->where('tbl_product.verify_status', Constants::Product_VerifyStatus_Active);
                        } else {
                            $query->where('tbl_product.supplier_member_id', 0);
                        }
                    } else {
                        $query->where(function ($q) {
                            $q->whereRaw('(tbl_product.supplier_member_id > 0 and tbl_product.verify_status = ' . Constants::Product_VerifyStatus_Active . ')')
                                ->orWhere('tbl_product.supplier_member_id', 0);
                        });
                    }
                    $query->leftJoin('tbl_supplier as sup', 'sup.member_id', 'tbl_product.supplier_member_id')
                        ->addSelect(['sup.name as supplier_name', 'sup.status as supplier_status']);
                    // 查询关键字匹配到的产品
                    if ($filter['keyword'] && $keyword = trim($filter['keyword'])) {
                        $keywordType = $filter['keyword_type'] ?: 1;
                        // 查询供应商平台
                        if ($keywordType == 2) {
                            $query->where('sup.name', 'like', '%' . $keyword . '%');
                        } else {
                            $query->where(function ($q) use ($keyword, $keywordType) {
                                return $q->where('tbl_product_skus.serial_number', 'like', '%' . $keyword . '%')
                                    ->orWhere('tbl_product.name', 'like', '%' . $keyword . '%');
                            });
                        }
                    }
                } else {
                    // 查询关键字匹配到的产品
                    if ($filter['keyword'] && $keyword = trim($filter['keyword'])) {
                        $query->where(function ($q) use ($keyword) {
                            return $q->orWhere('tbl_product_skus.serial_number', 'like', '%' . $keyword . '%')
                                ->orWhere('tbl_product.name', 'like', '%' . $keyword . '%');
                        });
                    }
                }
            } else {
                $query->whereIn('tbl_product.id', $filter['product_ids']);
                if (isset($filter['status'])) {
                    $status = myToArray($filter['status']);
                    $query->whereIn('tbl_product.status', $status);
                }
                $page = 0;
            }
            if (array_key_exists('supplier_member_id', $filter)) {
                $query->where('tbl_product.supplier_member_id', $filter['supplier_member_id']);
            }
            // 如果限制浏览权限
            if ($filter['view_perm']) {
                $query->leftJoin('tbl_product_relation_perm', 'tbl_product_relation_perm.product_id', '=', 'tbl_product.id');
                $whereOr = ['tbl_product.view_perm = 0'];
                $memberId = Auth::hasLogin();
                if($memberId){
                    $member = MemberModel::find($memberId);
                    $whereOr[] = 'tbl_product.view_perm = 1 or (tbl_product_relation_perm.level_id = \''.$member->level.'\' and tbl_product_relation_perm.type = 0)';
                }
                $query->whereRaw('('.implode(' OR ',$whereOr).')');
            }
        }
        // skus查询要使用leftJoin 不然排序会有问题
        $query->leftJoin('tbl_product_skus', 'tbl_product_skus.product_id', '=', 'tbl_product.id');
        if ($selectRaw === null) {
            $selectRaw = 'sum(tbl_product_skus.inventory) as inventory,tbl_product_skus.sku_code,tbl_product_skus.id as skus_id, tbl_product.*';
        }
        $query->selectRaw($selectRaw);
        $query->groupBy('tbl_product.id');
        // 查找预警产品
        if ($filter['is_inventory'] == 1) {
            $query->selectRaw('Min(tbl_product_skus.inventory) as skus_inventory');
            $query->havingRaw('tbl_product.warning_inventory >= skus_inventory');
        }

        // 因为查询中有 group by 和 having 所以count会有问题
        // 使用原生的sql去查询总记录数
        // sql 语句
        $sql = $query->toSql();
        $sql = "select count(*) as product_count from ({$sql}) as temp_count";
        $bindings = $query->getBindings();
        $total = ProductModel::runSql($sql, $bindings);
        $total = $total[0]->product_count;
        // 如果用于前台的查询，合并基础销量和真实销量
        if ($filter['merge_sold_count']) {
            $query->selectRaw('(sold_count + base_sold_count) as sold_count');
        }
        // 排序
        $query = self::buildProductListOrder($query, $filter['order_by']);
        $query->orderBy('id');
        // 加载分类
        if ($filter['show_class'] !== 0 && $filter['show_class'] !== false) {
            $query->with([
                'productClass' => function ($q) {
                    $q->where('tbl_product_class.status', 1);
                    $q->select(['class_name', 'tbl_product_class.id', 'parent_id']);
                }
            ]);
        }
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
            $discount = 100;
            // 查找出最优惠的会员价
            $memberDiscount = 100;
            foreach ($list as &$pro) {
                // 计算出最低会员价
                $pro['member_price'] = moneyMul($pro['price'], $memberDiscount / 100);
                // 商品原始销售价
                $pro['ori_price'] = $pro['price'];
                // 前台如果登录之后 需要计算会员价
                $pro['price'] = moneyMul($pro['price'], $discount / 100);
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
            'list' => $list
        ];
        return $result;
    }

    public static function getExportList($params)
    {
        $query = ProductModel::query()
            ->from('tbl_product')
            ->rightJoin('tbl_product_skus as ps', 'tbl_product.id', 'ps.product_id')
            ->leftJoin('tbl_supplier as sup', 'sup.member_id', 'tbl_product.supplier_member_id');
        if ($params['product_ids']) {
            $query->whereIn('tbl_product.id', $params['product_ids']);
        } else {
            // 产品状态
            if (isset($params['status'])) {
                if (is_array($params['status'])) {
                    $query->whereIn('tbl_product.status', $params['status']);
                } else {
                    if ($params['status'] == Constants::Product_Status_Sold_Out) {
                        $query->where('is_sold_out', Constants::Product_Sold_Out);
                        // 已售罄的 只查询上架的商品
                        $query->where('tbl_product.status', '=', Constants::Product_Status_Sell);
                    } else {
                        $query->where('tbl_product.status', $params['status']);
                    }
                }
            } else {
                // 默认去查询出售中的
                $query->where('tbl_product.status', '=', Constants::Product_Status_Sell);
            }
            // 产品类型
            if (isset($params['type'])) {
                $query->where('tbl_product.type', $params['type']);
            } else {
                // 默认显示所有类型产品 除了分销资格的产品
                $query->where('tbl_product.type', '<', Constants::Product_Type_Fenxiao_Physical);
            }

            // 如果是供应商版本
            $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
            if ($sn->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_SUPPLIER)) {
                // 查找是否是供应商商品 没有通过审核的商品不在此列表展示
                if (isset($params['is_supplier_product'])) {
                    $isSupplierProduct = intval($params['is_supplier_product']);
                    if ($isSupplierProduct == 1) {
                        $query->where('tbl_product.supplier_member_id', '>', 0)
                            ->where('tbl_product.verify_status', Constants::Product_VerifyStatus_Active);
                    } else {
                        $query->where('tbl_product.supplier_member_id', 0);
                    }
                } else {
                    $query->where(function ($q) {
                        $q->whereRaw('(tbl_product.supplier_member_id > 0 and tbl_product.verify_status = ' . Constants::Product_VerifyStatus_Active . ')')
                            ->orWhere('tbl_product.supplier_member_id', 0);
                    });
                }
                // 查询关键字匹配到的产品
                if ($params['keyword'] && $keyword = trim($params['keyword'])) {
                    $keywordType = $params['keyword_type'] ?: 1;
                    // 查询供应商平台
                    if ($keywordType == 2) {
                        $query->where('sup.name', 'like', '%' . $keyword . '%');
                    } else {
                        $query->where(function ($q) use ($keyword, $keywordType) {
                            return $q->where('ps.serial_number', 'like', '%' . $keyword . '%')
                                ->orWhere('tbl_product.name', 'like', '%' . $keyword . '%');
                        });
                    }
                }
            } else {
                // 查询关键字匹配到的产品
                if ($params['keyword'] && $keyword = trim($params['keyword'])) {
                    $query->where(function ($q) use ($keyword) {
                        return $q->orWhere('ps.serial_number', 'like', '%' . $keyword . '%')
                            ->orWhere('tbl_product.name', 'like', '%' . $keyword . '%');
                    });
                }
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
            // 查找预警产品
            if ($params['is_inventory'] == 1) {
                $query->whereRaw('tbl_product.warning_inventory >= ps.inventory');
            }
        }

        $query->addSelect(['tbl_product.id', 'tbl_product.supplier_member_id', 'ps.serial_number', 'tbl_product.name', 'ps.market_price', 'ps.price', 'ps.supply_price', 'ps.inventory', 'ps.weight', 'tbl_product.status', 'ps.sku_name', 'warning_inventory', 'sup.name as supplier_name', 'sup.status as supplier_status']);

        $query->with([
            'productClass' => function ($q) {
                $q->where('tbl_product_class.status', 1);
                $q->select(['class_name', 'tbl_product_class.id', 'parent_id']);
            }
        ]);
        if ($params['product_ids']) {
            $query->orderByRaw("find_in_set(tbl_product.id,'" . implode(',', $params['product_ids']) . "')");
        } else {
            // 排序
            $query = self::buildProductListOrder($query, $params['order_by']);
        }
        $list = $query->get();
        foreach ($list as &$item) {
            $item->supply_price = moneyCent2Yuan($item->supply_price);
            $item->price = moneyCent2Yuan($item->price);
            $classId = $item->productClass->where('parent_id', 0)->pluck('id')->toArray();
            $classArray = [];
            foreach ($item->productClass as $class) {
                if (!in_array($class->parent_id, $classId)) {
                    $classArray[] = $class;
                }
            }
            $item->newProductClass = $classArray;
        }
        return $list;
    }

    /**
     * @param array|int $productId 要修改的产品id 数组或数字
     * @param int $status 要修改为的状态
     * @return int
     * @throws \Exception
     */
    public static function editProductStatus($productId, $status)
    {
        if (empty($productId)) {
            throw new \Exception('缺少产品id');
        }
        // 允许修改的状态
        $statusList = [
            Constants::Product_Status_Sell,
            Constants::Product_Status_Warehouse,
            Constants::Product_Status_Delete
        ];
        $status = intval($status);
        if (in_array($status, $statusList, true)) {
            $update = ProductModel::query()
                ->where('tbl_product.site_id', getCurrentSiteId())
                ->where('tbl_product.status', '!=', Constants::Product_Status_Delete);
            if (!is_array($productId)) {
                $productId = [$productId];
            }
            $update->whereIn('id', $productId);
            $data = ['status' => $status];
            // 如果是上架 同时更新上架时间
            if ($status === Constants::Product_Status_Sell) {
                $data['sell_at'] = Carbon::now();
                $data['change_at'] = Carbon::now();
                // 如果是供应商版本 需要检测对应的供应商是否被禁用
                $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
                if ($sn->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_SUPPLIER)) {
                    $isCencel = (clone $update)->leftJoin('tbl_supplier as sup', 'sup.member_id', 'tbl_product.supplier_member_id')
                        ->where('sup.status', SupplierConstants::SupplierStatus_Cancel)
                        ->exists();
                    // 如果有禁用的供应商 不可以保存
                    if ($isCencel) {
                        throw new \Exception('供应商被禁用', 410);
                    }
                }
            } else {
                if ($status === Constants::Product_Status_Delete) {
                    // 删掉中间表的数据
                    DB::table('tbl_product_relation_class')
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->whereIn('product_id', $productId)
                        ->delete();
                }
            }
            return $update->update($data);
        } else {
            throw new \Exception('不能修改为该状态');
        }
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
                $orderColumn = 'tbl_product.created_at';
                break;
            // 更新时间排序
            case 'updated_at':
                $orderColumn = 'tbl_product.updated_at';
                break;
            // 更新时间排序
            case 'change_at':
                $orderColumn = 'tbl_product.change_at';
                break;
            // 按售后数排序
            case 'after_sale_count':
                $orderColumn = 'after_sale_count';
                break;
            // 按sku 预警库存排序
            case 'skus_inventory':
                $orderColumn = 'skus_inventory';
                break;
            // 按 sort 预警库存排序
            case 'sort':
                $orderColumn = 'sort';
                break;
            default:
                $orderColumn = 'sell_at';
                $orderBy['order'] = 'desc';
        }
        return $query->orderBy($orderColumn, $orderBy['order']);
    }

    /**
     * 根据时间 导出产品列表
     * @param array $list 要导出的数据
     * @param int $status
     * @return \Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public static function exportProductList($list, $status = 1)
    {
        $exportList = [];
        $exportFiled = self::formatExportFiled();
        $head = self::exportProductHead();
        // 不是供应商 不导出相关字段
        $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
        if (!$sn->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_SUPPLIER)) {
            array_pop($exportFiled);
            array_pop($head);
        }
        foreach ($list as $pro) {
            // 分类名称
            if (empty($pro['newProductClass'])) {
                $pro['class_name'] = '';
            } else {
                $pro['class_name'] = collect($pro['newProductClass'])->implode('class_name', ',');
            }
            $pro['status'] = $pro['status'] == 1 ? '上架' : ($pro['status'] == -1 ? '售罄' : '下架');
            $pro['sku'] = json_decode($pro['sku_name'], true);
            $pro['sku1'] = $pro['sku'][0];
            $pro['sku2'] = $pro['sku'][1];
            $pro['sku3'] = $pro['sku'][2];
            $pro['weight'] = $pro['weight'] ? $pro['weight'] : "\t0\t";
            $pro['inventory'] = $pro['inventory'] ? $pro['inventory'] : "\t0\t";
            $pro['market_price'] = $pro['market_price'] ? moneyCent2Yuan($pro['market_price']) : "";
            $pro['supplier_name'] = $pro['supplier_member_id'] ? $pro['supplier_name'] : "自营";
            $exportList[] = self::formatExportProductData($pro->toArray(), $exportFiled);
        }
        // dd($exportList);
        $export = new Export(collect($exportList), 'Shangpin-' . date("YmdHis") . '.xlsx', $head);
        return $export->export();
    }

    /**
     * 根据要查询的时间 获取要导出的字段
     * @param string $timeType
     * @return array
     */
    public static function formatExportFiled()
    {
        $exportFiled = [
            'serial_number' => '',
            'class_name' => '',
            'name' => '',
            'sku1' => '',
            'sku2' => '',
            'sku3' => '',
            'market_price' => '',
            'price' => '0',
            'supply_price' => '0',
            'inventory' => '0',
            'weight' => '0',
            'status' => '0',
            'supplier_name' => '自营'
        ];
        return $exportFiled;
    }

    /**
     * 把数据整理成要导出的顺序
     * @param $product
     * @param $format
     * @return array
     */
    public static function formatExportProductData($product, $format)
    {
        $product = array_replace($format, $product);
        $product = array_slice($product, 0, count($format));
        return $product;
    }

    /**
     * 根据时间类型去设置导出的文件头
     * @param string $timeType 数据库里面的时间字段名称
     * @return array
     */
    public static function exportProductHead()
    {
        $head = ['商品编号', '商品分类', '商品名称', '规格1', '规格2', '规格3', '市场价', '销售价', '成本价', '库存', '重量', '状态', '供应商/自营商品'];
        return $head;
    }

    /**
     * 产品价格分销数据同步旧数据
     * @return array
     */
    function synchroPriceRule()
    {
        $site = \DB::table('tbl_product_price_rule')->distinct('site_id')->select(['site_id'])->get();
        foreach ($site as $item) {
            $this->saveMemberRule(['member_rule' => 0], 'member_rule', $item->site_id);
            $this->saveMemberRule(['fenxiao_rule' => 0], 'fenxiao_rule', $item->site_id);
        }
    }

    function saveMemberRule($sku, $ruleString, $siteid = 0)
    {
        $productRuleModel = new ProductPriceRuleModel();
        $productRuleInfo = [];
        // 1为会员等级 0 为分销
        $productRuleModel->type = $ruleString == 'member_rule' ? 1 : 0;
        $productRuleModel->site_id = $siteid;
        if ($sku['member_rule'] == 0 && $ruleString == 'member_rule') {
            //如果member_rule为0的时候，说明此产品整个SKU都使用默认系统会员价，每个站只需要一条默认系统规则即可，不需要每个SKU都有一条
            $count = $productRuleModel->where(['site_id' => $this->_site->getSiteId(), 'type' => 1, 'rule_for' => 0])->count();
            if ($count > 0) {
                return 0;
            }
            // 读取此站点会员等级权重以及优惠
            $memberLevel = new MemberLevelModel();
            $memberLevelCollection = $memberLevel->where(['site_id' => $this->_site->getSiteId()])->get();
            // 0是通用的，使用默认系统会员价的 其他的时候存SKUID
            $productRuleModel->rule_for = 0;
            $productRuleInfo['rule_info']['type'] = 0;
            foreach ($memberLevelCollection as $value) {
                //type 折扣的时候为0 固定的时候为1
                $productRuleInfo['rule_info']['rule'][$value->id] = ['weight' => $value->weight, 'discount' => $value->discount];
            }
            $productRuleModel->rule_info = json_encode($productRuleInfo['rule_info']);
        } else if ($sku['fenxiao_rule'] == 0 && $ruleString == 'fenxiao_rule') {
            //如果member_rule为0的时候，说明此产品整个SKU都使用默认系统会员价，每个站只需要一条默认系统规则即可，不需要每个SKU都有一条
            $count = $productRuleModel->where(['site_id' => $this->_site->getSiteId(), 'type' => 0, 'rule_for' => 0])->count();
            if ($count > 0) {
                return 0;
            }
            // 读取此站点分销等级权重以及优惠
            $DistributionLevel = new DistributionLevelModel();
            $DistributionLevelCollection = $DistributionLevel->where(['site_id' => $this->_site->getSiteId()])->get();
            $productRuleModel->rule_for = 0;
            $productRuleInfo['rule_info']['type'] = 0;
            foreach ($DistributionLevelCollection as $value) {
                //type 折扣的时候为0 固定的时候为1
                $productRuleInfo['rule_info']['rule'][$value->id] = ['weight' => $value->weight, 'commission_rate' => $value->commission];
            }
            $productRuleModel->rule_info = json_encode($productRuleInfo['rule_info']);
        }
        $productRuleModel->save();
        return $productRuleModel->id;
    }

    /**
     * 获取产品数据
     * @return array
     */
    public function getProductData()
    {
        $product = $this->_product;
        $productData = $product->toArray();
        $class = $product->productClass()->pluck('tbl_product_class.id');
        $skus = $product->productSkus()->select(['id', 'product_id', 'price', 'market_price', 'supply_price', 'supplier_price', 'member_rule', 'fenxiao_rule', 'agent_order_commission_rule', 'agent_sale_reward_rule', 'dealer_sale_reward_rule', 'cloud_stock_rule', 'area_agent_rule', 'serial_number', 'inventory', 'sku_sold_count', 'weight', 'sku_code'])->get();
        $skuIds = $skus->pluck('id')->toArray();
        $skus = $skus->toArray();
        $skuValue = $product->productSkuValue()->select(['id', 'product_id', 'sku_name_id', 'value', 'small_image', 'big_image'])->get()->toArray();
        $skuName = $product->productSkuName()->select(['id', 'product_id', 'has_image', 'name'])->get()->toArray();
        $viewLevels = $product->viewLevels()->select(['tbl_member_level.id', 'tbl_member_level.name'])->get()->toArray();
        $buyLevels = $product->buyLevels()->select(['tbl_member_level.id', 'tbl_member_level.name'])->get()->toArray();

        // 自定义积分规则
        if ($product->point_status > 0) {
            $productData['point_rule'] = ProductPriceRuleModel::query()->where('id', $product->point_status)->first();
            $productData['point_rule']['rule_info'] = json_decode($productData['point_rule']['rule_info'], true);
            $productData['point_status'] = 1; //重设积分状态为1(表示自定义)，方便前台处理
        } else {
            $productData['point_rule'] = ['id' => 0, 'rule_info' => ['out_order_pay_point' => '', 'out_order_pay_max_percent' => '']];
        }

        // 分转元
        $productData = self::productPriceCent2Yuan($productData);
        $productRulePrice = ProductPriceRuleModel::query()
            ->where('site_id', $this->_site->getSiteId())
            ->whereIn('rule_for', $skuIds)->get();
        foreach ($skus as &$sku) {
            $sku = self::productPriceCent2Yuan($sku);
            if ($sku['member_rule']) {
                $sku['member_level_info'] = $productRulePrice->where('rule_for', $sku['id'])
                    ->where('type', LibsConstants::ProductPriceRuleType_MemberLevel)->first();
                if ($sku['member_level_info'] && $sku['member_level_info']->rule_info) {
                    $sku['member_level_info'] = json_decode($sku['member_level_info']->rule_info);
                    if ($sku['member_level_info']->amountType == 1) {
                        foreach ($sku['member_level_info']->rule as &$v) {
                            $v->discount = moneyCent2Yuan($v->discount);
                        }
                    }
                }
            }
            if ($sku['fenxiao_rule']) {
                $commission = $productRulePrice->where('rule_for', $sku['id'])
                    ->where('type', LibsConstants::ProductPriceRuleType_Distribution)->first();
                if ($commission && $commission->rule_info) {
                    $commission = json_decode($commission->rule_info);
                    foreach ($commission->rule as &$v) {
                        if (is_string($v->commission_rate)) {
                            $v->commission_rate = json_decode($v->commission_rate);
                        }
                        if ($commission->amountType == 1) {
                            foreach ($v->commission_rate as &$item) {
                                $item = moneyCent2Yuan($item);
                            }
                        }
                    }
                    $sku['commission_info'] = $commission;
                }
            }
            // 订单分红规则
            if ($sku['agent_order_commission_rule']) {
                $agentOrderCommissionInfo = $productRulePrice->where('rule_for', $sku['id'])
                    ->where('type', LibsConstants::ProductPriceRuleType_AgentOrderCommision)->first();
                if ($agentOrderCommissionInfo && $agentOrderCommissionInfo->rule_info) {
                    $agentOrderCommissionInfo = json_decode($agentOrderCommissionInfo->rule_info, true);
                    if ($agentOrderCommissionInfo['rule']) {
                        if (intval($agentOrderCommissionInfo['amountType']) == 1) {
                            foreach ($agentOrderCommissionInfo['rule']['commission'] as $key => &$value) {
                                $value = moneyCent2Yuan($value);
                            }
                        }
                        $sku['agent_order_commission_info'] = $agentOrderCommissionInfo;
                    }
                }
            }
            // 销售奖规则
            if ($sku['agent_sale_reward_rule']) {
                $agentSaleRewardInfo = $productRulePrice->where('rule_for', $sku['id'])
                    ->where('type', LibsConstants::ProductPriceRuleType_AgentSaleReward)->first();
                if ($agentSaleRewardInfo && $agentSaleRewardInfo->rule_info) {
                    $agentSaleRewardInfo = json_decode($agentSaleRewardInfo->rule_info, true);
                    if ($agentSaleRewardInfo['rule']) {
                        if (intval($agentSaleRewardInfo['amountType']) == 1) {
                            foreach ($agentSaleRewardInfo['rule']['commission'] as $key => &$value) {
                                $value = moneyCent2Yuan($value);
                            }
                            $agentSaleRewardInfo['rule']['low_commission'] = intval($agentSaleRewardInfo['rule']['low_commission']) / 100;
                        }
                        $sku['agent_sale_reward_info'] = $agentSaleRewardInfo;
                    }
                }
            }
            // 云仓规则
            if ($sku['cloud_stock_rule']) {
                $cloudStockRule = $productRulePrice->where('rule_for', $sku['id'])
                    ->where('type', LibsConstants::ProductPriceRuleType_CloudStock)->first();
                if ($cloudStockRule && $cloudStockRule->rule_info) {
                    $cloudStockRule = json_decode($cloudStockRule->rule_info, true);
                    if ($cloudStockRule['rule']) {
                        if (intval($cloudStockRule['amountType']) == 1) {
                            foreach ($cloudStockRule['rule']['commission'] as $key => &$value) {
                                $value = moneyCent2Yuan($value);
                            }
                        }
                        $sku['cloud_stock_rule_info'] = $cloudStockRule;
                    }
                }
            }

            // 经销商销售奖规则
            if ($sku['dealer_sale_reward_rule']) {
                $dealerSaleRewardInfo = $productRulePrice->where('rule_for', $sku['id'])
                    ->where('type', LibsConstants::ProductPriceRuleType_DealerSaleReward)->first();
                if ($dealerSaleRewardInfo && $dealerSaleRewardInfo->rule_info) {
                    $dealerSaleRewardInfo = json_decode($dealerSaleRewardInfo->rule_info, true);
                    if ($dealerSaleRewardInfo['rule']) {
                        if (intval($dealerSaleRewardInfo['amountType']) == 1) {
                            foreach ($dealerSaleRewardInfo['rule']['commission'] as $key => &$value) {
                                $value = moneyCent2Yuan($value);
                            }
                            // $dealerSaleRewardInfo['rule']['low_commission'] = intval($dealerSaleRewardInfo['rule']['low_commission']) / 100;
                        }
                        $sku['dealer_sale_reward_info'] = $dealerSaleRewardInfo;
                    }
                }
            }

            // 区域代理规则
            if ($sku['area_agent_rule']) {
                $commission = $productRulePrice->where('rule_for', $sku['id'])
                    ->where('type', LibsConstants::ProductPriceRuleType_AreaAgent)->first();
                if ($commission && $commission->rule_info) {
                    $commission = json_decode($commission->rule_info);
                    foreach ($commission->rule as &$v) {
                        if (is_string($v->commission_rate)) {
                            $v->commission_rate = json_decode($v->commission_rate);
                        }
                        if ($commission->amountType == 1) {
                            foreach ($v->commission_rate as &$item) {
                                $item = moneyCent2Yuan($item);
                            }
                        }
                    }
                    $sku['area_agent_rule_info'] = $commission;
                }
            }
        }
        unset($sku);
        return [
            'productData' => $productData,
            'class' => $class,
            'skus' => $skus,
            'skuValue' => $skuValue,
            'skuName' => $skuName,
            'viewLevels' => $viewLevels,
            'buyLevels' => $buyLevels,
            'memberLevelList' => (new MemberLevel())->getList()["list"],
            'distributionLevelList' => (new DistributionLevel())->getList(),
            'distributionLevel' => (new DistributionSetting())->getCurrentSiteSetting()->level,
            'classList' => self::getClassList(), // 分类列表
            'freightTemplateList' => self::getFreightTemplateList(), // 运费模板列表
            'imagePath' => Site::getSiteComdataDir()
        ];
    }

    /**
     * 获取分销选择的商城商品详情
     * @return array
     */
    public function getFenxiaoShopProductData()
    {
        $product = $this->_product;
        $class = $product->load(['productClass' => function ($query) {
            $query->select([
                'tbl_product_class.id',
                'parent_id',
                'class_name',
                'product_id'
            ]);
        }]);
        return [
            'product_name' => $product->name,
            'image' => $product->small_images,
            'price' => moneyCent2Yuan($product->price),
            'class' => $class->productClass,
            'weight' => $product->weight
        ];
    }

    /**
     * 检测商品编码是否重复
     * @param string $serialNumber
     * @param int $productId
     * @return bool|int
     */
    public static function checkSerialNumber($serialNumber, $productId = 0)
    {
        if (!$serialNumber) {
            return true;
        } else {
            if (mb_strlen($serialNumber) > 20) {
                return 0;
            } else {
                // 查找是否重复
                $has = ProductSkusModel::query()->where('serial_number', $serialNumber);
                if ($productId) {
                    $has->where('product_id', '<>', $productId);
                }
                $has = $has->count();
                if ($has > 0) {
                    return -1;
                } else {
                    return true;
                }
            }
        }
    }

    /**
     * 获取分销新建的产品数据
     * @return array
     */
    public function getFenxiaoProductData()
    {
        $product = $this->_product;
        if ($product->id) {
            $productData = $product->toArray();
            $skus = $product->productSkus()->get()->toArray();

            // 分转元
            $productData = self::productPriceCent2Yuan($productData);
            foreach ($skus as &$sku) {
                $sku = self::productPriceCent2Yuan($sku);
            }
            unset($sku);
        } else {
            $productData = [];
            $skus = [];
        }
        return [
            'productData' => $productData,
            'skus' => $skus,
            'freightTemplateList' => self::getFreightTemplateList() // 运费模板列表
        ];
    }

    /**
     * 获取产品的url
     * @param $id
     * @return string
     */
    public static function getProductUrl($id)
    {
        return url('/') . '/shop/front/#/product/product-detail?id=' . $id;
    }

    /**
     * 获取所有分类列表
     * @return array
     */
    public static function getClassList()
    {
        // 分类列表
        $classList = ProductClassModel::query()
            ->where('status', 1)
            ->select(['id', 'class_name', 'parent_id'])
            ->orderBy('order')
            ->get()->toArray();
        return $classList;
    }

    /**
     * 获取所有运费模板
     * @return array
     */
    public static function getFreightTemplateList()
    {
        // 运费模板列表
        $freightTemplateList = FreightTemplateModel::query()
            ->where('status', 1)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->select(['template_name', 'id'])
            ->orderBy('id', 'desc')
            ->get()->toArray();
        return $freightTemplateList;
    }

    /**
     * 保存供应商商品前的处理
     * @param $productData
     * @throws \Exception
     */
    public function beforeSaveSupplierProduct(&$productData, &$skus)
    {
        // 审核的处理
        if ($productData['is_verify']) {
            $verifyStatus = $productData['verify_status'] == 1
                ? Constants::Product_VerifyStatus_Active
                : Constants::Product_VerifyStatus_Refuse;
            $productData['status'] = $productData['status']
                ? Constants::Product_Status_Sell
                : Constants::Product_Status_Warehouse;
            $rejectReason = trim($productData['reject_reason']);
            if (Constants::Product_VerifyStatus_Refuse === $verifyStatus) {
                if (!$rejectReason) {
                    throw new \Exception('请输入拒绝原因');
                }
                $productData['verify_reject_reason'] = $rejectReason;
            }
            unset($productData['is_verify']);
        } else {
            unset($productData['verify_status']);
        }
        // 供应商被禁用 不能上架
        if ($productData['status'] == Constants::Product_Status_Sell) {
            $supplier = SupplierAdmin::getSupplierInfo($this->_product['supplier_member_id']);
            if ($supplier['status'] !== SupplierConstants::SupplierStatus_Active) {
                throw new \Exception('供应商被禁用', 410);
            }
        }

        // 不参加云仓
        $productData['cloud_stock_status'] = 0;
        // 供货价不能修改
        unset($productData['supplier_price']);
        if ($skus && is_array($skus)) {
            foreach ($skus as &$item) {
                unset($item['supplier_price']);
            }
            unset($item);
        }

    }

    /**
     * 保存产品相关数据
     * @param array $productData 产品主要数据
     * @param array $skus 产品skus数据
     * @param array $skuData 产品sku的name value关系数组
     * @return ProductModel|null
     * @throws \Exception
     */
    public function store($productData, $skus = [], $skuData = [])
    {
        if (empty($productData)) {
            throw new \Exception('数据为空');
        }
        DB::beginTransaction();
        try {
            $product = $this->_product;
            // 如果是供应商的商品
            if ($product->supplier_member_id > 0) {
                $this->beforeSaveSupplierProduct($productData, $skus);
            }
            // 没有sku记录时  直接保存一条新的记录
            if (empty($skus)) {
                $skus = [
                    [
                        'fenxiao_rule' => $productData['fenxiao_rule'],
                        'member_rule' => $productData['member_rule'],
                        'member_level_info' => $productData['member_level_info'],
                        'commission_info' => $productData['commission_info'],
                        'price' => $productData['price'],
                        'market_price' => $productData['market_price'],
                        'supply_price' => $productData['supply_price'],
                        'inventory' => $productData['inventory'],
                        'weight' => $productData['weight'],
                        'sku_code' => '0',
                        'serial_number' => $productData['serial_number'],
                        'site_id' => $this->_site->getSiteId(),
                        'agent_order_commission_rule' => $productData['agent_order_commission_rule'],
                        'agent_order_commission_info' => $productData['agent_order_commission_info'],
                        'agent_sale_reward_rule' => $productData['agent_sale_reward_rule'],
                        'agent_sale_reward_info' => $productData['agent_sale_reward_info'],
                        'dealer_sale_reward_rule' => $productData['dealer_sale_reward_rule'],
                        'dealer_sale_reward_info' => $productData['dealer_sale_reward_info'],
                        'cloud_stock_rule' => $productData['cloud_stock_rule'],
                        'cloud_stock_rule_info' => $productData['cloud_stock_rule_info'],
                        'area_agent_rule' => $productData['area_agent_rule'],
                        'area_agent_rule_info' => $productData['area_agent_rule_info'],
                    ]
                ];
            } else {
                // 获取skus的最低售价和市场价、成本价 保存到产品表
                $productData['price'] = ProductSku::getSkusMinPrice($skus, 'price');
                $productData['market_price'] = ProductSku::getSkusMinPrice($skus, 'market_price');
                $productData['supply_price'] = ProductSku::getSkusMinPrice($skus, 'supply_price');
            }

            $isNew = $productData['id'] ? false : true;
            $collectSkus = collect($skus);
            $this->beforeSaveProduct($productData, $collectSkus, $isNew);
            // 转换元为分
            $productData = self::productPriceYuan2Cent($productData);
            foreach ($skus as &$sku) {
                $sku = self::productPriceYuan2Cent($sku);
            }
            unset($sku);

            // 如果是已售罄的商品 检查一下 如果增加了库存 则把售罄状态取消
            $inventory = $collectSkus->sum('inventory');
            if ($inventory > 0 && $product->is_sold_out == Constants::Product_Sold_Out) {
                $product->is_sold_out = Constants::Product_No_Sold_Out;
            } elseif ($inventory <= 0) {
                // 如果修改了库存为0 更新售罄时间
                if ($product->is_sold_out != Constants::Product_Sold_Out) {
                    $product->sold_out_at = Carbon::now();
                    $product->is_sold_out = Constants::Product_Sold_Out;
                }
            }
            $newBigImages = $productData['big_images'];
            $originalBigImages = $product->big_images;
            $productData['big_images'] = implode(',', $productData['big_images']);
            $productData['small_images'] = implode(',', $productData['small_images']);

            // 自营商品的审核状态默认为1
            if ($isNew && $product->supplier_member_id < 1) {
                $productData['verify_status'] = Constants::Product_VerifyStatus_Active;
            }
            if ($productData['verify_status'] && $product->supplier_member_id > 1) {
                $productData['verify_at'] = Carbon::now();
            }
            // 产品参数
            if (is_array($productData['params']) && count($productData['params']) > 0) {
                $productData['params'] = json_encode($productData['params']);
            } else {
                $productData['params'] = null;
            }
            $productData['site_id'] = $this->_site->getSiteId();
            // 如果是直接上架  需要更新一下最新上架时间
            if (intval($productData['status']) === Constants::Product_Status_Sell) {
                $productData['sell_at'] = Carbon::now();
            }
            unset($productData['serial_number']);

            //积分规则
            if ($productData['point_status'] > 0) {
                $productData['point_status'] = $this->savePointRule($productData['point_rule']);
            }
            if ($productData['point_status'] < 1 && $product->point_status > 0) {
                //如果积分规则由自定义切换为默认或关闭，删除原来的自定义规则
                ProductPriceRuleModel::query()->where('id', $product->point_status)->delete();
            }

            $product->fill($productData)->save();
            //商品浏览权限
            if (is_array($productData['viewLevels'])) {
                $this->saveProductViewLevels($productData['viewLevels']);
            }

            //商品的购买权限
            if (is_array($productData['buyLevels'])) {
                $this->saveProductBuyLevels($productData['buyLevels']);
            }
            $this->saveProductClass($productData['class_ids']); // 保存分类
            $productSku = new ProductSku($product, $this->_site);
            $productSku->editProductSkuInfo($skus, $skuData, $isNew); // 保存sku
            // 保存最新的修改时间
            $product->fill(['change_at' => Carbon::now()])->save();
            DB::commit();
            if (!$isNew) {
                // 产品图片 做下处理 如果有不需要的图片要删掉
                beforeSaveImage(explode(',', $originalBigImages), $newBigImages);
                // 小图暂时不删除了
//                beforeSaveImage(explode(',', $product->small_images), $productData['small_images']);
            }
            return $product;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 保存积分规则
     * @param $ruleInfo
     * @return mixed
     */
    private function savePointRule($ruleInfo)
    {
        // 查找数据是否已经存在
        $productRuleModel = null;
        if (intval($ruleInfo['id']) > 0) {
            $productRuleModel = ProductPriceRuleModel::query()
                ->where('id', $ruleInfo['id'])->where('site_id', $this->_product->site_id)
                ->first();
        }
        $now = date('Y-m-d H:i:s');
        if (!$productRuleModel) {
            // 新建
            $productRuleModel = new ProductPriceRuleModel();
            $productRuleModel->type = \App\Modules\ModuleShop\Libs\Constants::ProductPriceRuleType_Point;
            $productRuleModel->site_id = $this->_site->getSiteId();
            $productRuleModel->rule_for = $this->_product->id;
            $productRuleModel->created_at = $now;
        }
        $productRuleModel->updated_at = $now;
        $productRuleModel->rule_info = json_encode($ruleInfo['rule_info']);
        // 保存数据
        $productRuleModel->save();
        return $productRuleModel->id;
    }

    /**
     * 保存商品数据前的操作
     * @param $productData
     * @param $collectSkus
     * @param $isNew
     * @throws \Exception
     */
    public function beforeSaveProduct($productData, $collectSkus, $isNew)
    {
        // 价格是否合法
        if ($productData['price'] <= 0) {
            throw new \Exception('商品价格必须大于0');
        }
        if ($productData['supply_price'] < 0) {
            throw new \Exception('商品成本价不能小于0');
        }
        if ($productData['inventory'] < 0) {
            throw new \Exception('商品库存不能小于0');
        }
        // 检测商品编码是否重复
        $serialNumber = $collectSkus
            ->pluck('serial_number')
            ->filter(function ($value) {
                return mb_strlen($value) > 0;
            });
        // 判断是否有重复的
        if ($serialNumber->count() != $serialNumber->unique()->count()) {
            throw new \Exception('商品编码重复');
        }
        $serialNumber = $serialNumber->all();
        if ($serialNumber) {
            $isExist = ProductSkusModel::query()->whereIn('serial_number', $serialNumber);
            if (!$isNew) {
                $isExist->where('product_id', '<>', $productData['id']);
            }
            $isExist = $isExist->select(['id'])->first();
            if ($isExist) {
                throw new \Exception('商品编码已存在');
            }
        }
    }

    /**
     * 保存用来分销的产品
     * @param $productData
     * @return ProductModel|null
     * @throws \Exception
     */
    public function saveFenxiaoProduct($productData)
    {
        if (empty($productData)) {
            throw new \Exception('数据为空');
        }

        DB::beginTransaction();
        try {
            $product = $this->_product;
            // 元转分
            $productData['market_price'] = 0; // 市场价不展示 先把保存为售价
            $productData['supply_price'] = $productData['price'];
            $productData['point_status'] = 0; // 默认积分抵扣规则为系统默认值
            $productData = self::productPriceYuan2Cent($productData);
            $skus = [
                [
                    'fenxiao_rule' => 0,
                    'agent_order_commission_rule' => 0,
                    'agent_sale_reward_rule' => 0,
                    'member_rule' => 0,
                    'cloud_stock_rule' => 0,
                    'price' => $productData['price'],
                    'market_price' => 0,
                    'supply_price' => $productData['supply_price'],
                    'inventory' => 99999999,
                    'weight' => $productData['weight'] ?: 0,
                    'sku_code' => '0',
                    'site_id' => $this->_site->getSiteId()
                ]
            ];
            $isNew = $productData['id'] ? false : true;
            // 产品图片
            $productData['big_images'] = implode(',', $productData['big_images']);
            $productData['small_images'] = implode(',', $productData['small_images']);
            $productData['site_id'] = $this->_site->getSiteId();
            // 如果是直接上架  需要更新一下最新上架时间
            if (intval($productData['status']) === Constants::Product_Status_Sell) {
                $productData['sell_at'] = Carbon::now();
            }
            $product->fill($productData)->save();
            $productSku = new ProductSku($product, $this->_site);
            $productSku->editProductSkuInfo($skus, [], $isNew); // 保存sku
            // 内容修改时间
            // 获取最新的修改时间
            // $time = ProductModel::where('id', $product->id)->value('updated_at');
            // 保存最新的修改时间
            // $product->fill(['change_at' => $time])->save();

            DB::commit();
            return $product;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 把相关价格转换为分
     * @param $priceData
     * @return mixed
     */
    public static function productPriceYuan2Cent($priceData)
    {
        $filed = ['price', 'market_price', 'supply_price'];
        foreach ($filed as $item) {
            if (isset($priceData[$item])) {
                $priceData[$item] = moneyYuan2Cent($priceData[$item]);
            }
        }
        return $priceData;
    }

    /**
     * 把相关价格转换为元
     * @param $priceData
     * @return mixed
     */
    public static function productPriceCent2Yuan($priceData)
    {
        $filed = ['price', 'ori_price', 'sale_price', 'market_price', 'supply_price', 'member_price', 'supplier_price'];
        foreach ($filed as $item) {
            if (isset($priceData[$item])) {
                $priceData[$item] = moneyCent2Yuan($priceData[$item]);
            }
        }
        return $priceData;
    }

    /**
     * 上传产品图片
     * @param UploadedFile $image
     * @return array                大图和小图的路径
     * @throws \Exception
     */
    public static function uploadProductImage(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/product/image/';
        // 保存名称
        $saveName = 'product' . time() . str_random(5);
        $bigImage = $saveName . '_b';
        $smallImage = $saveName . '_s';
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(1500, $bigImage);
        $img->reduceImageSize(400, $smallImage);
        return [
            'bigImage' => $savePath . $bigImage . '.' . $extension,
            'smallImage' => $savePath . $smallImage . '.' . $extension
        ];
    }

    /**
     * 上传产品sku图片
     * @param UploadedFile $image
     * @return array                大图和小图的路径
     * @throws \Exception
     */
    public static function uploadProductSkuImage(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/product/sku/';
        // 保存名称
        $saveName = 'product-sku' . time() . str_random(5);
        $bigImage = $saveName . '_b';
        $smallImage = $saveName . '_s';
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(1500, $bigImage);
        $img->reduceImageSize(400, $smallImage);
        return [
            'bigImage' => $savePath . $bigImage . '.' . $extension,
            'smallImage' => $savePath . $smallImage . '.' . $extension
        ];
    }

    /**
     * 上传产品视频封面
     * @param UploadedFile $image
     * @return 封面图的路径
     * @throws \Exception
     */
    public static function uploadProductVideoPoster(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/product/image/';
        // 保存名称
        $saveName = 'video_poster' . time() . str_random(5);
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 限制图片的宽度
        $img->reduceImageSize(1500, $saveName);
        return $savePath . $saveName . '.' . $extension;
    }

    /**
     * 保存产品分类关系
     * @param array $classIds
     */
    public function saveProductClass($classIds = [])
    {
        if (!empty($classIds)) {
            $product = $this->_product;
            $saveClass = [];
            foreach ($classIds as $id) {
                $saveClass[$id] = ['site_id' => $this->_site->getSiteId()];
            }
            $product->productClass()->sync($saveClass);
        }
    }

    /**
     * 保存产品浏览权限
     * @param array $levelIds
     */
    public function saveProductViewLevels($levelIds)
    {
        if (!empty($levelIds)) {
            if (is_string($levelIds)) $levelIds = explode(",", $levelIds);
            $product = $this->_product;
            $saveData = [];
            foreach ($levelIds as $id) {
                $saveData[$id] = ['site_id' => $this->_site->getSiteId(), 'type' => 0];
            }
            $product->viewLevels()->sync($saveData);
        }
    }

    /**
     * 保存产品购买权限
     * @param array $levelIds
     */
    public function saveProductBuyLevels($levelIds)
    {
        if (!empty($levelIds)) {
            if (is_string($levelIds)) $levelIds = explode(",", $levelIds);
            $product = $this->_product;
            $saveData = [];
            foreach ($levelIds as $id) {
                $saveData[$id] = ['site_id' => $this->_site->getSiteId(), 'type' => 1];
            }
            $product->buyLevels()->sync($saveData);
        }
    }

    /**
     * 判断会员是否有浏览商品的权限
     *
     * @param integer $memberId 会员ID
     * @return integer 0 = 没有权限, -1 = 需要先登录, 1 = 有权限
     */
    public function checkViewPerm($memberId = 0)
    {
        $cacheKey = static::class . '_checkViewPerm_' . $this->_product->id;
        // 先尝试读全局变量，减少当一个请求中需要多次使用此方法时的数据查询量
        if (!DataCache::has($cacheKey)) {
            DataCache::setData($cacheKey, $this->checkViewPermAct($memberId));
        }
        return DataCache::getData($cacheKey);
    }

    /**
     * 判断会员是否有浏览商品的权限
     *
     * @param integer $memberId 会员ID
     * @return integer 0 = 没有权限, -1 = 需要先登录, 1 = 有权限
     */
    private function checkViewPermAct($memberId = 0)
    {
        if (!$memberId) $memberId = \YZ\Core\Member\Auth::hasLogin();
        $product = $this->_product;
        if ($product->view_perm == 0) return 1; //限制条件为分开时，直接返回有权限
        if ($product->view_perm == 1 && $memberId) return 1; //限制条件为所有登录会员并且有会员ID时，直接返回有权限
        if ($product->view_perm > 0) {
            if (!$memberId) return -1;
            $member = new Member($memberId);
            $viewLevels = $product->viewLevels()->select(['tbl_member_level.id'])->get();
            foreach ($viewLevels as $level) {
                if ($level->id == $member->getModel()->level) return 1;
            }
        }
        return 0;
    }

    /**
     * 判断会员是否有购买商品的权限
     *
     * @param integer $memberId
     * @return integer 0 = 没有权限, -1 = 需要先登录, 1 = 有权限
     */
    public function checkBuyPerm($memberId = 0)
    {
        $cacheKey = static::class . '_checkBuyPerm_' . $this->_product->id;
        // 先尝试读全局变量，减少当一个请求中需要多次使用此方法时的数据查询量
        if (!DataCache::has($cacheKey)) {
            DataCache::setData($cacheKey, $this->checkBuyPermAct($memberId));
        }
        return DataCache::getData($cacheKey);
    }

    /**
     * 判断会员是否有购买商品的权限
     *
     * @param integer $memberId
     * @return integer 0 = 没有权限, -1 = 需要先登录, 1 = 有权限
     */
    private function checkBuyPermAct($memberId = 0)
    {
        if (!$memberId) $memberId = \YZ\Core\Member\Auth::hasLogin();
        if (!$memberId) return -1;
        $product = $this->_product;
        if ($product->buy_perm <= 1) return 1; //限制条件为所有登录会员时，直接返回有权限
        $member = new Member($memberId);
        $buyLevels = $product->buyLevels()->select(['tbl_member_level.id'])->get();
        foreach ($buyLevels as $level) {
            if ($level->id == $member->getModel()->level) return 1;
        }
        return 0;
    }

    /**
     * 获取产品详情数据
     * $front_params 前端传输数据
     * @return array
     */
    public function getProducDetail($params, $front_show = false)
    {
        $supplierBaseSetting = SupplierBaseSettingModel::find($this->_product->site_id);
        $product = $this->_product;
        $productData = $product->toArray();
        $skus = $product->productSkus()->get()->toArray();
        // 分转元
        $productData = self::productPriceCent2Yuan($productData);
        //输出SKU最大值以及SKU最小值
        $productData['max_price'] = 0;
        $productData['min_price'] = 999999999; // 设置一个足够的值
        $discount = 100;
        if ($params['member_id']) {
            $memberLevel = new MemberLevel();
            // 输出会员折扣
            $discount = $memberLevel->getMemberDiscount($params['member_id']);
            if (!$discount) $discount = 100;
            if ($this->_product->supplier_member_id && !$supplierBaseSetting->open_member_price) {
                $discount = 100; //供应商关闭了会员价
            }
        }
        foreach ($skus as &$sku) {
            $sku = self::productPriceCent2Yuan($sku);
            $productData['max_price'] = max($productData['max_price'], $sku['price']) * ($discount / 100);
            $productData['min_price'] = min($productData['min_price'], $sku['price']) * ($discount / 100);
        };
        unset($sku);
        //如果有
        if ($front_show) {
            //输出可领取的优惠券，有效的，在有效期内的，产品ID，该网站ID，数量大于0的
            $coupon = new Coupon();
            $param = [];
            $param['product_id'] = $productData['id'];
            $param['status'] = 1;
            if ($this->_product->supplier_member_id && !$supplierBaseSetting->open_coupon) {
                $couponData = [];
            } else {
                $couponData = $coupon->couponProduct($param);
            }
            $productData['couponData'] = $couponData;
            //输出运费模板的运费
            if ($product['freight_id']) {
                $freight = new Freight();
                $freightData = $freight->getAreaFreight($product['freight_id'], $params['city']);
                $productData['freight_fee'] = $freightData;
            } else {
                $productData['freight_fee'] = 0;
            }
            //输出这个产品是否被收藏了
            if ($params['member_id']) {
                $collection = new ProductCollection();
                $collection->findByMemberProduct($params['member_id'], $param['product_id']);
                $productData['have_collection'] = $collection->getModel();
            }
        }
        $productData['detail'] = LinkHelper::replaceHtmlLink($productData['detail']);
        // 通用设置
        $config = $configModel = Site::getCurrentSite()->getConfig()->getModel();
        $productData['product_list_show_sale_num'] = $config->product_list_show_sale_num;
        return [
            'productData' => $productData,
            'imagePath' => Site::getSiteComdataDir()
        ];
    }

    public function getSku($memberId = 0, $forFront = 0)
    {
        if ($memberId) {
            $member = new Member($memberId);
        }
        $product = $this->_product;
        $skus = $product->productSkus()->get();
        $skuValue = $product->productSkuValue()->get()->toArray();
        $skuName = $product->productSkuName()->get()->toArray();
        $baseShopProduct = null;
        $expression = ProductPriceRuleModel::query()->from('tbl_product_price_rule');
        //寻找自定义规则
        $expression->whereIn('id', $skus->pluck('member_rule')->toArray());
        $rules = $expression->get()->keyBy('member_rule');
        //找出最低权重的会员ID
        $minMemberLevel = 0;
        if ($forFront) {
            $levelModel = MemberLevelModel::query()->where('site_id', getCurrentSiteId())->orderBy('weight')->first();
            if ($levelModel) $minMemberLevel = $levelModel->id;
        }
        foreach ($skus as &$sku) {
            if ($forFront) $sku->sale_price = $sku->price;
            $rule = $rules[$sku->member_rule];
            if (!$baseShopProduct) {
                if ($product->supplier_member_id) $baseShopProduct = new SupplierShopProduct($product, $sku);
                else $baseShopProduct = new NormalShopProduct($product, $sku);
            }
            $baseShopProduct->setSku($sku);
            if ($member) {
                $sku->price = $baseShopProduct->getMemberPrice($member->getModel()->level, $rule);
            } else {
                $sku->price = $baseShopProduct->getMemberPrice($minMemberLevel, $rule);
            }
            $sku = self::productPriceCent2Yuan($sku);
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

    /**
     * 返回当前数据模型
     * @return mixed
     */
    public function getModel()
    {
        if ($this->checkExist()) {
            return $this->_product;
        } else {
            return false;
        }
    }

    /**
     * 当前数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_product && $this->_product->id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取产品数量统计
     * @return array
     */
    public static function getProductCount()
    {
        $productQuery = ProductModel::query()
            ->where('type', '<', Constants::Product_Type_Fenxiao_Physical)
            ->where('status', '!=', Constants::Product_Status_Delete)
            // 供应商的商品必须要通过审核
            ->where(function ($q) {
                $q->whereRaw('supplier_member_id > 0 and verify_status = ' . Constants::Product_VerifyStatus_Active)
                    ->orWhere('supplier_member_id', 0);
            });
        $productWarningCountQuery = clone $productQuery;
        // 统计出出售中 仓库中 和 已售罄的产品数量
        $productCount = $productQuery->selectRaw(
            "SUM(CASE status WHEN ? THEN 1 ELSE 0 END) AS sell,
                SUM(CASE status WHEN ? THEN 1 ELSE 0 END) AS warehouse,
                SUM(CASE WHEN is_sold_out=? and status=? THEN 1 ELSE 0 END) AS sold_out",
            [Constants::Product_Status_Sell, Constants::Product_Status_Warehouse, Constants::Product_Sold_Out, Constants::Product_Status_Sell]
        )
            ->first();

        $productWarningCount = $productWarningCountQuery
            ->where('status', Constants::Product_Status_Sell)
            ->where('type', '<', Constants::Product_Type_Fenxiao_Physical)
            ->leftJoin('tbl_product_skus', 'tbl_product_skus.product_id', '=', 'tbl_product.id')
            ->selectRaw('sum(tbl_product_skus.inventory) as inventory, tbl_product.*')
            ->selectRaw('Min(tbl_product_skus.inventory) as skus_inventory')
            ->groupBy('tbl_product.id')
//            ->havingRaw('tbl_product.warning_inventory >= inventory');
            ->havingRaw('tbl_product.warning_inventory >= skus_inventory');
        $sql = $productWarningCount->toSql();
        $sql = "select count(*) as product_count from ({$sql}) as temp_count";
        $bindings = $productWarningCount->getBindings();
        $total = ProductModel::runSql($sql, $bindings);
        $total = $total[0]->product_count;
        $count = $productCount->toArray();
        $count['warning'] = $total;

        // 总的商品数
        $productTotalQuery = clone $productQuery;
        $count['total'] = $productTotalQuery->count();

        return $count;
    }

    public static function getMaxDistributionCommission()
    {
        $query = DistributionLevelModel::query();
        $query->where(['site_id' => Site::getCurrentSite()->getSiteId()])->where('status', 1);
        $list = $query->get();
        $max = 0;
        foreach ($list as $item) {
            $commission = $item->commission = json_decode($item->commission, true);
            if (!empty($commission) && is_array($commission)) {
                $itemMax = max($commission);
                $max = $max < $itemMax ? $itemMax : $max;
            }
        }
        return $max;
    }

    /**
     * 检测产品是否删除或下架,或者此产品SKU被删除了
     * @param $productId 产品ID
     * @param $skuId 产品SkuId
     * @return Boolean
     */
    public static function checkProductOffShelvesOrDelete(int $productId, int $skuId)
    {
        $count = ProductModel::query()
            ->where('id', $productId)
            ->where('status', 1)
            ->where(['site_id' => Site::getCurrentSite()->getSiteId()])
            ->count();
        $skuCount = ProductSkusModel::query()
            ->where('id', $skuId)
            ->where(['site_id' => Site::getCurrentSite()->getSiteId()])
            ->count();
        return ($count > 0 && $skuCount > 0) ? true : false;
    }

    /**
     * 是否有生效的商品
     * @param $productIds
     * @return bool
     */
    public static function hasActiveProduct($productIds)
    {
        if (!$productIds || !is_array($productIds)) {
            return false;
        }
        $count = ProductModel::query()->where('site_id', getCurrentSiteId())
            ->where('status', Constants::Product_Status_Sell)
            ->whereIn('id', $productIds)
            ->select('id')
            ->first();
        return !!$count;
    }

    public function getSkuInfo()
    {
        $product = $this->_product;
        $productData = $product->toArray();
        $skus = $product->productSkus()->select(['id', 'product_id', 'price', 'market_price', 'supply_price', 'member_rule', 'fenxiao_rule', 'agent_order_commission_rule', 'agent_sale_reward_rule', 'dealer_sale_reward_rule', 'cloud_stock_rule', 'area_agent_rule', 'serial_number', 'inventory', 'sku_sold_count', 'weight', 'sku_code'])->get();
        $skus = $skus->toArray();
        $skuValue = $product->productSkuValue()->select(['id', 'product_id', 'sku_name_id', 'value', 'small_image', 'big_image'])->get()->toArray();
        $skuName = $product->productSkuName()->select(['id', 'product_id', 'has_image', 'name'])->get()->toArray();
        return [
            'productData' => $productData,
            'skus' => $skus,
            'skuValue' => $skuValue,
            'skuName' => $skuName,
        ];
    }

    public function saveWarningInventory($warningInventory)
    {
        $this->_product->warning_inventory = intval($warningInventory);
        $this->_product->change_at = Carbon::now();
        $this->_product->save();
    }

    /**
     * 累加商品浏览量，一般在前台商品详情接口中调用
     */
    public function incrementHits()
    {
        $this->_product->increment('hits');
        $this->_product->save();
    }

    /**
     * 修改商品排序值，一般在后台商品列表中调用
     * @param $sort 排序值
     */
    public function editSort($sort)
    {
        $this->_product->sort = $sort;
        $this->_product->change_at = Carbon::now();
        $this->_product->save();
    }

    public static function import($importFile)
    {
        // 错误数据
        $errorData = [];
        // 正确可更新的数据
        $rightData = [];
        $skus = ProductSkusModel::query()->where('site_id', getCurrentSiteId())
            ->whereNotNull('serial_number')
            ->select(['serial_number', 'id'])
            ->get();
        $newSkusArr = [];
        foreach ($skus as $value) {
            $newSkusArr[$value->serial_number] = $value->id;
        }
        foreach ($importFile as &$item) {
            if (!$item['serial_number']) {
                $item['error_data'] = "商品编码为空";
                $errorData[] = $item;
            } elseif (!$newSkusArr[$item['serial_number']]) {
                $item['error_data'] = "商品编码不正确";
                $errorData[] = $item;
            } elseif (!$item['inventory']) {
                $item['error_data'] = "库存为空";
                $errorData[] = $item;
            } else {
                $updateData['id'] = $newSkusArr[$item['serial_number']];
                $updateData['inventory'] = $item['inventory'];
                $rightData[] = $updateData;
            }
        }
        if ($rightData) {
            (new ProductSkusModel())->updateBatch($rightData);
        };
        return ['error_data' => $errorData, 'error_data_count' => count($errorData), 'right_data_count' => count($rightData)];
    }
}